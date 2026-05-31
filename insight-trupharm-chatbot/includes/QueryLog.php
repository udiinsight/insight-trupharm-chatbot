<?php
/**
 * Privacy-first chat logging.
 *
 * Stores ONLY metrics — message length, retrieved chunk count, response time,
 * disclaimer kind, error code. Never the message text, never the IP. The IP is
 * salted and SHA-256'd so we can rate-limit and dedupe abuse without retaining PII.
 *
 * Retention: rows older than {@see QueryLog::RETENTION_DAYS} are pruned daily by
 * the `insight_chat/prune_logs` cron event.
 *
 * @package InsightChat
 */

namespace InsightChat;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class QueryLog {

	public const CRON_HOOK     = 'insight_chat/prune_logs';
	public const RETENTION_DAYS = 30;

	/** @var string[] */
	private const ALLOWED_EVENT_TYPES = [
		'query',
		'reply',
		'open',
		'close',
		'action_clicked',
		'starter_clicked',
		'rate_limited',
		'error',
	];

	public static function register(): void {
		add_action( 'init', [ __CLASS__, 'schedule_pruner' ] );
		add_action( self::CRON_HOOK, [ __CLASS__, 'prune' ] );
	}

	public static function schedule_pruner(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Insert a single log row. Never throws — logging must not interfere with chat.
	 *
	 * @param string                                                        $event_type
	 * @param array{
	 *   session_id?: string,
	 *   ip?: string,
	 *   lang?: string,
	 *   meta?: array,
	 * } $args
	 */
	public static function record( string $event_type, array $args = [] ): void {
		if ( ! in_array( $event_type, self::ALLOWED_EVENT_TYPES, true ) ) {
			return;
		}

		global $wpdb;
		$table = Installer::table_logs();

		$data = [
			'ts'         => gmdate( 'Y-m-d H:i:s' ),
			'session_id' => self::clean_session_id( $args['session_id'] ?? '' ),
			'ip_hash'    => self::hash_ip( $args['ip'] ?? '' ),
			'lang'       => substr( (string) ( $args['lang'] ?? '' ), 0, 8 ),
			'event_type' => $event_type,
			'meta'       => wp_json_encode( self::sanitize_meta( $args['meta'] ?? [] ) ),
		];

		$wpdb->insert(
			$table,
			$data,
			[ '%s', '%s', '%s', '%s', '%s', '%s' ]
		);
	}

	/**
	 * Delete log rows older than the retention window.
	 */
	public static function prune(): void {
		global $wpdb;
		$table  = Installer::table_logs();
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - self::RETENTION_DAYS * DAY_IN_SECONDS );
		$wpdb->query(
			$wpdb->prepare( "DELETE FROM {$table} WHERE ts < %s", $cutoff )
		);
	}

	public static function hash_ip( string $ip ): string {
		if ( $ip === '' ) {
			return '';
		}
		// Per-site salt prevents cross-site correlation if multiple Insight installs share a server.
		$salt = wp_salt( 'auth' );
		return hash( 'sha256', $salt . '|' . $ip );
	}

	public static function client_ip(): string {
		// Cloudways sits behind their loadbalancer; trust X-Forwarded-For first hop.
		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$xff   = explode( ',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'] );
			$first = trim( $xff[0] );
			if ( filter_var( $first, FILTER_VALIDATE_IP ) !== false ) {
				return $first;
			}
		}
		return (string) ( $_SERVER['REMOTE_ADDR'] ?? '' );
	}

	/**
	 * @param mixed $meta
	 * @return array
	 */
	private static function sanitize_meta( $meta ): array {
		if ( ! is_array( $meta ) ) {
			return [];
		}
		// Drop anything that smells like PII. The only allowed keys are listed below.
		$allowed = [
			'chars', 'retrieved', 'response_time_ms', 'disclaimer_kind',
			'action', 'starter_label', 'error_code', 'event_source',
			'rate_limit_window_seconds', 'rate_limit_count', 'http_status',
			'tokens_in', 'tokens_out',
		];
		$out = [];
		foreach ( $allowed as $k ) {
			if ( array_key_exists( $k, $meta ) ) {
				$v = $meta[ $k ];
				if ( is_scalar( $v ) ) {
					$out[ $k ] = $v;
				}
			}
		}
		return $out;
	}

	private static function clean_session_id( string $sid ): string {
		// Random alphanumeric tokens up to 64 chars; coerce anything else to empty.
		if ( preg_match( '/^[A-Za-z0-9_-]{1,64}$/', $sid ) ) {
			return $sid;
		}
		return '';
	}
}
