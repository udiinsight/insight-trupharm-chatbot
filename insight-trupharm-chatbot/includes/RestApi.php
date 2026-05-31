<?php
/**
 * Insight Chat REST API.
 *
 * Namespace: insight-chat/v1
 *
 *   GET  /starter-questions?lang=he|en       — public
 *   POST /events                              — public, nonce-protected
 *   GET  /health                              — admin only
 *
 * @package InsightChat
 */

namespace InsightChat;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RestApi {

	public const NAMESPACE  = 'insight-chat/v1';
	public const NONCE_NAME = 'insight_chat_rest';

	public static function register(): void {
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
	}

	public static function register_routes(): void {
		register_rest_route( self::NAMESPACE, '/starter-questions', [
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
			'callback'            => [ __CLASS__, 'starter_questions' ],
			'args'                => [
				'lang' => [
					'type'              => 'string',
					'default'           => 'he',
					'enum'              => [ 'he', 'en' ],
					'sanitize_callback' => 'sanitize_text_field',
				],
			],
		] );

		register_rest_route( self::NAMESPACE, '/events', [
			'methods'             => 'POST',
			'permission_callback' => [ __CLASS__, 'verify_nonce' ],
			'callback'            => [ __CLASS__, 'events' ],
			'args'                => [
				'event_type' => [
					'type'     => 'string',
					'required' => true,
					'enum'     => [ 'open', 'close', 'action_clicked', 'starter_clicked', 'error' ],
				],
				'session_id' => [ 'type' => 'string' ],
				'lang'       => [ 'type' => 'string', 'enum' => [ 'he', 'en' ] ],
				'meta'       => [ 'type' => 'object' ],
			],
		] );

		register_rest_route( self::NAMESPACE, '/health', [
			'methods'             => 'GET',
			'permission_callback' => [ __CLASS__, 'admin_only' ],
			'callback'            => [ __CLASS__, 'health' ],
		] );
	}

	// ------------------------------------------------------------------ permissions

	public static function verify_nonce( \WP_REST_Request $request ): bool {
		$nonce = $request->get_header( 'x-wp-nonce' );
		if ( ! $nonce ) {
			$nonce = $request->get_param( '_wpnonce' );
		}
		// Same nonce used by AI Engine's UI (`wp_rest`) is fine; we keep a separate
		// action so the widget can refresh independently if needed.
		return (bool) ( wp_verify_nonce( (string) $nonce, 'wp_rest' )
			|| wp_verify_nonce( (string) $nonce, self::NONCE_NAME ) );
	}

	public static function admin_only(): bool {
		return current_user_can( 'manage_options' );
	}

	// ------------------------------------------------------------------ endpoints

	public static function starter_questions( \WP_REST_Request $request ): \WP_REST_Response {
		$lang  = (string) $request->get_param( 'lang' );
		$path  = INSIGHT_CHAT_PATH . '/data/starter-questions.json';
		$store = is_readable( $path ) ? json_decode( (string) file_get_contents( $path ), true ) : null;

		$fallback = self::default_starters();
		$data     = is_array( $store ) ? $store : $fallback;
		$list     = $data[ $lang ] ?? $fallback[ $lang ] ?? $fallback['he'];

		$response = new \WP_REST_Response( [
			'lang'  => $lang,
			'items' => $list,
		], 200 );
		$response->header( 'Cache-Control', 'public, max-age=300' );
		return $response;
	}

	public static function events( \WP_REST_Request $request ): \WP_REST_Response {
		$event = (string) $request->get_param( 'event_type' );
		QueryLog::record( $event, [
			'session_id' => (string) $request->get_param( 'session_id' ),
			'lang'       => (string) $request->get_param( 'lang' ),
			'ip'         => QueryLog::client_ip(),
			'meta'       => (array) ( $request->get_param( 'meta' ) ?? [] ),
		] );
		return new \WP_REST_Response( [ 'logged' => true ], 202 );
	}

	public static function health( \WP_REST_Request $request ): \WP_REST_Response {
		unset( $request );
		global $wpdb;

		// Knowledge embeddings sync status — AI Engine keeps one row per vector in this table.
		$stats_by_status = (array) $wpdb->get_results(
			"SELECT status, COUNT(*) AS n FROM {$wpdb->prefix}mwai_vectors GROUP BY status",
			ARRAY_A
		);

		$total_logs = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM " . Installer::table_logs()
		);

		$prune_next = wp_next_scheduled( QueryLog::CRON_HOOK );

		return new \WP_REST_Response( [
			'schema_version'    => Installer::SCHEMA_VERSION,
			'vectors_by_status' => $stats_by_status,
			'log_rows'          => $total_logs,
			'next_log_prune'    => $prune_next ? gmdate( 'c', $prune_next ) : null,
		], 200 );
	}

	// ------------------------------------------------------------------ helpers

	/**
	 * @return array<string, array<int, array{label:string, prompt:string}>>
	 */
	private static function default_starters(): array {
		return [
			'he' => [
				[ 'label' => 'מה זה SUGAR360 ואיך החיישן עובד?', 'prompt' => 'מה זה SUGAR360 ואיך מערכת הניטור הרציף של הסוכר עובדת?' ],
				[ 'label' => 'איך מתקינים ומפעילים את החיישן?',   'prompt' => 'איך מתקינים ומפעילים את חיישן SUGAR360 ומחברים אותו לאפליקציה?' ],
				[ 'label' => 'כמה זמן החיישן מחזיק ומה הדיוק?',   'prompt' => 'כמה זמן חיישן SUGAR360 מחזיק ומה רמת הדיוק שלו?' ],
				[ 'label' => 'איך מזמינים או יוצרים קשר?',         'prompt' => 'איך אפשר להזמין SUGAR360 או ליצור קשר עם שירות הלקוחות?' ],
			],
			'en' => [
				[ 'label' => 'What is SUGAR360 and how does the sensor work?',        'prompt' => 'What is SUGAR360 and how does the continuous glucose monitoring system work?' ],
				[ 'label' => 'How do I apply and activate the sensor?',               'prompt' => 'How do I apply and activate the SUGAR360 sensor and pair it with the app?' ],
				[ 'label' => 'How long does the sensor last and how accurate is it?', 'prompt' => 'How long does a SUGAR360 sensor last and how accurate is it?' ],
				[ 'label' => 'How do I order or contact support?',                    'prompt' => 'How can I order SUGAR360 or contact customer support?' ],
			],
		];
	}
}
