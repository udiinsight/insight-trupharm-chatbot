<?php
/**
 * Per-IP rate limiter.
 *
 * Complements AI Engine's user/spend-based limits with a coarse abuse cap:
 *   {@see RateLimiter::DEFAULT_MAX} messages / {@see RateLimiter::DEFAULT_WINDOW_SECONDS} seconds / IP.
 *
 * Hooks AI Engine's `mwai_chatbot_takeover` filter — returning a non-empty string
 * short-circuits the LLM call and the user sees the configured Hebrew message.
 *
 * Storage: WordPress transients. With Object Cache Pro / Redis active these stay
 * in-memory; otherwise they fall back to the options table. Either way they expire
 * automatically so no manual cleanup is needed.
 *
 * @package InsightChat
 */

namespace InsightChat;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RateLimiter {

	public const DEFAULT_MAX             = 10;
	public const DEFAULT_WINDOW_SECONDS  = 600; // 10 minutes
	private const TRANSIENT_PREFIX       = 'insight_chat_rl_';

	public static function register(): void {
		// Priority 5 so we run before AI Engine's moderation and any other takeover hooks.
		add_filter( 'mwai_chatbot_takeover', [ __CLASS__, 'maybe_block' ], 5, 3 );
	}

	/**
	 * @param mixed  $current  Existing takeover answer (null by default).
	 * @param object $query    AI Engine query object.
	 * @param array  $params   Chatbot params.
	 * @return mixed string to short-circuit, or unchanged $current to pass through.
	 */
	public static function maybe_block( $current, $query, $params ) {
		if ( ! empty( $current ) ) {
			return $current;
		}

		// Capability bypass: logged-in admins and editors are not rate-limited.
		if ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) {
			return $current;
		}

		$ip      = QueryLog::client_ip();
		$ip_hash = QueryLog::hash_ip( $ip );
		if ( $ip_hash === '' ) {
			return $current;
		}

		$max    = (int) apply_filters( 'insight_chat/rate_limit/max', self::DEFAULT_MAX );
		$window = (int) apply_filters( 'insight_chat/rate_limit/window', self::DEFAULT_WINDOW_SECONDS );

		$state = self::tick( $ip_hash, $window );

		if ( $state['count'] > $max ) {
			$lang = self::lang_from_query( $query );
			QueryLog::record( 'rate_limited', [
				'ip'         => $ip,
				'lang'       => $lang,
				'session_id' => self::session_from_query( $query ),
				'meta'       => [
					'rate_limit_count'           => $state['count'],
					'rate_limit_window_seconds'  => $window,
				],
			] );
			return self::message( $lang, $state['retry_after'] );
		}

		return $current;
	}

	/**
	 * Increment-and-fetch the current count for an IP-hash within the window.
	 *
	 * @return array{count:int, retry_after:int}
	 */
	private static function tick( string $ip_hash, int $window ): array {
		$key   = self::TRANSIENT_PREFIX . $ip_hash;
		$state = get_transient( $key );

		if ( ! is_array( $state ) || empty( $state['expires_at'] ) || $state['expires_at'] < time() ) {
			$state = [
				'count'      => 1,
				'expires_at' => time() + $window,
			];
		} else {
			$state['count'] = (int) $state['count'] + 1;
		}

		$ttl = max( 1, $state['expires_at'] - time() );
		set_transient( $key, $state, $ttl );

		return [
			'count'       => (int) $state['count'],
			'retry_after' => $ttl,
		];
	}

	private static function message( string $lang, int $retry_after ): string {
		$minutes = max( 1, (int) ceil( $retry_after / 60 ) );

		if ( $lang === 'en' ) {
			return wp_json_encode( [
				'response'           => "You have asked many questions in a short time. Please try again in about {$minutes} minutes.",
				'sources'            => [],
				'suggested_actions'  => [],
				'disclaimer_needed'  => false,
				'disclaimer_kind'    => null,
			], JSON_UNESCAPED_UNICODE );
		}

		// Hebrew default. Number rendering uses digits for clarity in RTL.
		return wp_json_encode( [
			'response'           => "שלחת הרבה שאלות בזמן קצר. אפשר לנסות שוב בעוד {$minutes} דקות.",
			'sources'            => [],
			'suggested_actions'  => [],
			'disclaimer_needed'  => false,
			'disclaimer_kind'    => null,
		], JSON_UNESCAPED_UNICODE );
	}

	private static function lang_from_query( $query ): string {
		// Heuristic: any Hebrew character → he; otherwise en.
		$msg = is_object( $query ) && method_exists( $query, 'get_message' ) ? (string) $query->get_message() : '';
		return preg_match( '/\p{Hebrew}/u', $msg ) ? 'he' : 'en';
	}

	private static function session_from_query( $query ): string {
		if ( is_object( $query ) && property_exists( $query, 'session' ) && is_string( $query->session ) ) {
			return $query->session;
		}
		return '';
	}
}
