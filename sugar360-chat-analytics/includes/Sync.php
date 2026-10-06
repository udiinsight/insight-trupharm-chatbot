<?php
/**
 * Copies conversation metrics from AI Engine's wp_mwai_chats into s360ca_chats (AI Engine deletes
 * its discussions after 90 days; our rows keep counts only, never text), then asks a small model
 * to tag each finished conversation with a topic and whether the bot answered it.
 *
 * @package S360Analytics
 */

namespace S360Analytics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Sync {

	public const CRON_HOOK = 's360ca_sync';

	private const CURSOR_OPTION    = 's360ca_sync_cursor';
	private const CLASSIFY_MODEL   = 'claude-haiku-4-5-20251001';
	private const CLASSIFY_BATCH   = 40;
	private const IDLE_MINUTES     = 30;
	private const MAX_ATTEMPTS     = 3;

	public const TOPICS = [
		'product_info'      => 'מידע על המוצר ואופן פעולתו',
		'price_purchase'    => 'מחיר, רכישה ומבצעים',
		'shipping_order'    => 'משלוח, הזמנה וזמינות',
		'setup_app'         => 'התקנה, הפעלה ואפליקציה',
		'troubleshooting'   => 'תקלות ובעיות בשימוש',
		'accuracy_readings' => 'דיוק ומדידות',
		'coverage'          => 'קופות חולים, כיסוי והחזרים',
		'returns_warranty'  => 'החזרות ואחריות',
		'medical'           => 'שאלה רפואית אישית',
		'service_request'   => 'בקשה לדבר עם נציג',
		'other'             => 'אחר',
	];

	private static bool $classifying = false;

	public static function register(): void {
		add_action( 'init', [ __CLASS__, 'schedule' ] );
		add_action( self::CRON_HOOK, [ __CLASS__, 'run' ] );
		add_filter( 'mwai_ai_allowed', [ __CLASS__, 'allow_own_queries' ], 20, 3 );
	}

	/**
	 * Cron runs as a "guest", so AI Engine's per-guest daily limit (meant for visitors) blocks the
	 * classifier after a few calls. Lift only that limit, only for our own queries; the system-wide
	 * spend cap still applies.
	 */
	public static function allow_own_queries( $allowed, $query = null, $limits = null ) {
		if ( ! self::$classifying || ! is_wp_error( $allowed ) || $allowed->get_error_code() !== 'mwai_over_limit' ) {
			return $allowed;
		}
		$system_message = is_array( $limits ) ? (string) ( $limits['system']['overLimitMessage'] ?? '' ) : '';
		if ( $system_message !== '' && $allowed->get_error_message() === $system_message ) {
			return $allowed;
		}
		return true;
	}

	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 300, 'hourly', self::CRON_HOOK );
		}
	}

	public static function run(): void {
		self::sync_chats();
		self::classify_pending();
	}

	/**
	 * Upsert every AI Engine conversation updated since the last run. Cheap: no AI calls.
	 *
	 * @return int rows synced
	 */
	public static function sync_chats(): int {
		global $wpdb;
		$source = $wpdb->prefix . 'mwai_chats';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $source ) ) !== $source ) {
			return 0;
		}

		$cursor = (string) get_option( self::CURSOR_OPTION, '1970-01-01 00:00:00' );
		$count  = 0;
		$max    = $cursor;

		do {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT chatId, messages, created, updated FROM {$source}
					WHERE updated >= %s AND botId = %s ORDER BY updated ASC, id ASC LIMIT 200 OFFSET %d",
					$cursor,
					self::bot_id(),
					$count
				),
				ARRAY_A
			);
			foreach ( $rows as $row ) {
				self::upsert( $row );
				$max = max( $max, (string) $row['updated'] );
				++$count;
			}
		} while ( count( $rows ) === 200 );

		update_option( self::CURSOR_OPTION, $max, false );
		return $count;
	}

	private static function upsert( array $row ): void {
		global $wpdb;
		$chat_id = Tracker::id_or_empty( $row['chatId'] ?? '', 64 );
		if ( $chat_id === '' ) {
			return;
		}

		$m = self::summarize( json_decode( (string) $row['messages'], true ) ?: [] );
		if ( $m['user_messages'] === 0 ) {
			return;
		}

		$table    = Installer::table_chats();
		$existing = $wpdb->get_var( $wpdb->prepare( "SELECT user_messages FROM {$table} WHERE chat_id = %s", $chat_id ) );

		if ( $existing === null ) {
			$wpdb->insert(
				$table,
				[
					'chat_id'       => $chat_id,
					'started_at'    => $row['created'],
					'last_at'       => $row['updated'],
					'user_messages' => $m['user_messages'],
					'bot_referred'  => $m['bot_referred'],
					'disclaimer'    => $m['disclaimer'],
				]
			);
			return;
		}

		$data = [
			'last_at'       => $row['updated'],
			'user_messages' => $m['user_messages'],
			'bot_referred'  => $m['bot_referred'],
			'disclaimer'    => $m['disclaimer'],
		];
		// The conversation continued after it was tagged: tag it again from the full transcript.
		if ( (int) $existing !== $m['user_messages'] ) {
			$data['topic']             = null;
			$data['answered']          = null;
			$data['classify_attempts'] = 0;
		}
		$wpdb->update( $table, $data, [ 'chat_id' => $chat_id ] );
	}

	/**
	 * Counts only; the transcript itself is never stored by this plugin.
	 */
	public static function summarize( array $messages ): array {
		$rank         = [ '' => 0, 'general' => 1, 'personal' => 2, 'emergency' => 3 ];
		$user         = 0;
		$referred     = 0;
		$disclaimer   = '';
		foreach ( $messages as $msg ) {
			$role = $msg['role'] ?? '';
			if ( $role === 'user' ) {
				++$user;
				continue;
			}
			if ( $role !== 'assistant' ) {
				continue;
			}
			$reply = self::parse_reply( $msg['content'] ?? '' );
			if ( ! $reply ) {
				continue;
			}
			$kind = is_string( $reply['disclaimer_kind'] ?? null ) ? $reply['disclaimer_kind'] : '';
			if ( isset( $rank[ $kind ] ) && $rank[ $kind ] > $rank[ $disclaimer ] ) {
				$disclaimer = $kind;
			}
			foreach ( (array) ( $reply['suggested_actions'] ?? [] ) as $action ) {
				if ( self::is_service_action( (array) $action ) ) {
					$referred = 1;
				}
			}
		}
		return [ 'user_messages' => $user, 'bot_referred' => $referred, 'disclaimer' => $disclaimer ];
	}

	private static function is_service_action( array $action ): bool {
		$type = $action['type'] ?? '';
		if ( $type === 'whatsapp' ) {
			return true;
		}
		$url = (string) ( $action['url'] ?? '' );
		return $type === 'navigate' && ( stripos( $url, 'tel:' ) === 0 || preg_match( '#/(customer-service|contact)/?#i', $url ) );
	}

	private static function parse_reply( $content ): ?array {
		if ( is_array( $content ) ) {
			return $content;
		}
		$content = trim( (string) $content );
		$content = preg_replace( '/^```(?:json)?\s*|\s*```$/', '', $content );
		$decoded = json_decode( $content, true );
		if ( is_array( $decoded ) ) {
			return $decoded;
		}
		// The model sometimes writes a sentence before the JSON object; the widget tolerates that too.
		$start = strpos( $content, '{' );
		$end   = strrpos( $content, '}' );
		if ( $start === false || $end === false || $end <= $start ) {
			return null;
		}
		$decoded = json_decode( substr( $content, $start, $end - $start + 1 ), true );
		return is_array( $decoded ) ? $decoded : null;
	}

	/**
	 * Tag conversations idle for IDLE_MINUTES. Failures are retried up to MAX_ATTEMPTS, then the
	 * conversation is left as "other" so it never blocks the queue.
	 *
	 * @return array{classified:int, failed:int}
	 */
	public static function classify_pending( int $limit = self::CLASSIFY_BATCH ): array {
		global $wpdb, $mwai;
		$out = [ 'classified' => 0, 'failed' => 0 ];
		if ( ! isset( $mwai ) || ! is_object( $mwai ) || ! method_exists( $mwai, 'simpleTextQuery' ) ) {
			return $out;
		}

		$table  = Installer::table_chats();
		$source = $wpdb->prefix . 'mwai_chats';
		$idle   = gmdate( 'Y-m-d H:i:s', time() - self::IDLE_MINUTES * MINUTE_IN_SECONDS );
		$ids    = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT chat_id FROM {$table} WHERE topic IS NULL AND last_at < %s ORDER BY last_at DESC LIMIT %d",
				$idle,
				$limit
			)
		);

		foreach ( $ids as $chat_id ) {
			$messages = $wpdb->get_var( $wpdb->prepare( "SELECT messages FROM {$source} WHERE chatId = %s ORDER BY id DESC LIMIT 1", $chat_id ) );
			$result   = $messages ? self::classify( json_decode( (string) $messages, true ) ?: [] ) : null;

			if ( $result ) {
				$wpdb->update( $table, [ 'topic' => $result['topic'], 'answered' => $result['answered'] ], [ 'chat_id' => $chat_id ] );
				++$out['classified'];
				continue;
			}

			++$out['failed'];
			$attempts = 1 + (int) $wpdb->get_var( $wpdb->prepare( "SELECT classify_attempts FROM {$table} WHERE chat_id = %s", $chat_id ) );
			$data     = [ 'classify_attempts' => $attempts ];
			// Source discussion already pruned by AI Engine, or the model keeps failing.
			if ( ! $messages || $attempts >= self::MAX_ATTEMPTS ) {
				$data['topic'] = 'other';
			}
			$wpdb->update( $table, $data, [ 'chat_id' => $chat_id ] );
		}
		return $out;
	}

	/**
	 * @return array{topic:string, answered:int}|null
	 */
	private static function classify( array $messages ): ?array {
		global $mwai;
		$lines = [];
		foreach ( $messages as $msg ) {
			$role = $msg['role'] ?? '';
			if ( $role === 'user' ) {
				$lines[] = 'VISITOR: ' . mb_substr( wp_strip_all_tags( (string) ( $msg['content'] ?? '' ) ), 0, 600 );
			} elseif ( $role === 'assistant' ) {
				$reply = self::parse_reply( $msg['content'] ?? '' );
				if ( $reply && isset( $reply['response'] ) ) {
					$lines[] = 'BOT: ' . mb_substr( wp_strip_all_tags( (string) $reply['response'] ), 0, 600 );
				}
			}
		}
		if ( ! $lines ) {
			return null;
		}
		$transcript = mb_substr( implode( "\n", $lines ), 0, 5000 );

		$prompt = "You label customer-support chats for an online store that sells a continuous glucose monitor (CGM) in Israel. "
			. "The chat is in Hebrew. Pick the ONE topic that best describes what the visitor mainly wanted, from these keys:\n"
			. "product_info (what the product is, how it works, features, sensor duration)\n"
			. "price_purchase (price, how to buy, discounts, payment)\n"
			. "shipping_order (delivery, order status, stock)\n"
			. "setup_app (applying the sensor, pairing, the phone app, compatible phones)\n"
			. "troubleshooting (errors, sensor fell off, connection loss, something not working)\n"
			. "accuracy_readings (accuracy, readings vs finger-prick, calibration)\n"
			. "coverage (health fund / kupat holim coverage, reimbursement)\n"
			. "returns_warranty (returns, refunds, warranty, replacement)\n"
			. "medical (the visitor's personal medical situation, their glucose values, treatment)\n"
			. "service_request (mainly wants a human representative or to be contacted)\n"
			. "other (greetings only, off-topic, anything else)\n\n"
			. "Also decide \"answered\": false ONLY if the visitor asked a real question and the bot said it does not know, "
			. "could not help, or only referred the visitor elsewhere without answering it. Otherwise true "
			. "(including chats with no real question, e.g. a greeting only; a personal medical question the bot correctly "
			. "referred to a doctor; or a visitor who asked for a human and was given the contact details).\n\n"
			. "Reply with JSON only, no prose: {\"topic\":\"<key>\",\"answered\":true|false}\n\n"
			. "CHAT:\n" . $transcript;

		self::$classifying = true;
		try {
			$reply = $mwai->simpleTextQuery(
				$prompt,
				[
					'envId'     => self::env_id(),
					'model'     => self::CLASSIFY_MODEL,
					'maxTokens' => 60,
				]
			);
		} catch ( \Throwable $e ) {
			return null;
		} finally {
			self::$classifying = false;
		}

		if ( ! preg_match( '/\{.*\}/s', (string) $reply, $m ) ) {
			return null;
		}
		$json = json_decode( $m[0], true );
		if ( ! is_array( $json ) || ! isset( self::TOPICS[ $json['topic'] ?? '' ] ) ) {
			return null;
		}
		return [ 'topic' => $json['topic'], 'answered' => empty( $json['answered'] ) ? 0 : 1 ];
	}

	private static function bot_id(): string {
		return (string) apply_filters( 'insight_chat/widget/bot_id', 'default' );
	}

	/** The chatbot's own Anthropic environment, so classification uses the key already in use. */
	private static function env_id(): string {
		foreach ( (array) get_option( 'mwai_chatbots', [] ) as $bot ) {
			if ( is_array( $bot ) && ( $bot['botId'] ?? '' ) === self::bot_id() && ! empty( $bot['envId'] ) ) {
				return (string) $bot['envId'];
			}
		}
		$options = (array) get_option( 'mwai_options', [] );
		return (string) ( $options['ai_default_env'] ?? '' );
	}
}
