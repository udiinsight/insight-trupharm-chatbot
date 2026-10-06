<?php
/**
 * Front-end tracker + the public endpoint it reports to.
 *
 * The tracker observes the chatbot widget from the outside (it wraps window.fetch and listens for
 * clicks inside #insight-chat-root), so the chatbot plugin itself needs no changes.
 *
 * @package S360Analytics
 */

namespace S360Analytics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Tracker {

	public const HANDLE    = 's360ca-tracker';
	public const NAMESPACE = 's360-analytics/v1';

	public const EVENTS   = [ 'open', 'message', 'starter', 'handoff', 'product_click', 'link_click', 'error' ];
	public const CHANNELS = [ 'whatsapp', 'phone', 'contact' ];
	public const DEVICES  = [ 'mobile', 'tablet', 'desktop' ];

	/** Per-IP cap on tracked events, to keep a script from inflating the numbers. */
	private const RATE_LIMIT_PER_HOUR = 300;

	public static function register(): void {
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue' ], 5 );
		add_action( 'rest_api_init', [ __CLASS__, 'routes' ] );
		add_filter( 'script_loader_tag', [ __CLASS__, 'mark_untouchable' ], 10, 2 );
		add_filter( 'rocket_delay_js_exclusions', [ __CLASS__, 'rocket_exclusions' ] );
		add_filter( 'rocket_minify_excluded_external_js', [ __CLASS__, 'rocket_exclusions' ] );
		add_filter( 'rocket_excluded_inline_js_content', [ __CLASS__, 'rocket_exclusions' ] );
	}

	public static function enqueue(): void {
		if ( is_admin() || ( isset( $_GET['bricks'] ) && in_array( $_GET['bricks'], [ 'run', 'editor' ], true ) ) ) {
			return;
		}
		$file = S360CA_PATH . '/assets/tracker.js';
		wp_register_script(
			self::HANDLE,
			plugins_url( 'assets/tracker.js', S360CA_FILE ) . '?v=' . filemtime( $file ),
			[],
			null,
			false
		);
		wp_enqueue_script( self::HANDLE );
		wp_add_inline_script(
			self::HANDLE,
			'window.S360CA = ' . wp_json_encode(
				[
					'endpoint'   => esc_url_raw( rest_url( self::NAMESPACE . '/track' ) ),
					'windowDays' => Attribution::WINDOW_DAYS,
				]
			) . ';',
			'before'
		);
	}

	public static function mark_untouchable( string $tag, string $handle ): string {
		if ( $handle !== self::HANDLE ) {
			return $tag;
		}
		return str_replace( '<script src=', '<script data-no-minify="1" data-no-defer="1" data-cfasync="false" data-no-optimize="1" src=', $tag );
	}

	public static function rocket_exclusions( $excluded ): array {
		$excluded   = is_array( $excluded ) ? $excluded : [];
		$excluded[] = 'sugar360-chat-analytics';
		$excluded[] = 'S360CA';
		return $excluded;
	}

	public static function routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/track',
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'track' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	/**
	 * The tracker posts with navigator.sendBeacon (text/plain, no nonce), so the body is parsed
	 * by hand and every field is whitelisted or pattern-checked.
	 */
	public static function track( \WP_REST_Request $request ): \WP_REST_Response {
		$body = json_decode( (string) $request->get_body(), true );
		if ( ! is_array( $body ) ) {
			return new \WP_REST_Response( [ 'ok' => false ], 400 );
		}

		$event = (string) ( $body['event'] ?? '' );
		if ( ! in_array( $event, self::EVENTS, true ) ) {
			return new \WP_REST_Response( [ 'ok' => false ], 400 );
		}

		$channel = (string) ( $body['channel'] ?? '' );
		if ( $event === 'handoff' && ! in_array( $channel, self::CHANNELS, true ) ) {
			return new \WP_REST_Response( [ 'ok' => false ], 400 );
		}
		if ( $event !== 'handoff' ) {
			$channel = '';
		}

		if ( self::rate_limited() ) {
			return new \WP_REST_Response( [ 'ok' => false ], 429 );
		}

		$chat_id = self::id_or_empty( $body['chat_id'] ?? '', 64 );
		$device  = (string) ( $body['device'] ?? '' );

		self::insert(
			[
				'event'      => $event,
				'chat_id'    => $chat_id,
				'visitor_id' => self::id_or_empty( $body['visitor_id'] ?? '', 32 ),
				'channel'    => $channel,
				'page'       => self::clean_path( $body['page'] ?? '' ),
				'device'     => in_array( $device, self::DEVICES, true ) ? $device : '',
			]
		);

		$response = new \WP_REST_Response( [ 'ok' => true ], 200 );
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}

	public static function insert( array $row ): void {
		global $wpdb;
		$wpdb->insert(
			Installer::table_events(),
			array_merge( [ 'created_at' => gmdate( 'Y-m-d H:i:s' ) ], $row )
		);
	}

	public static function id_or_empty( $value, int $max ): string {
		$value = (string) $value;
		return preg_match( '/^[A-Za-z0-9_-]{8,' . $max . '}$/', $value ) ? $value : '';
	}

	private static function clean_path( $value ): string {
		$path = (string) wp_parse_url( (string) $value, PHP_URL_PATH );
		if ( $path === '' || $path[0] !== '/' ) {
			return '';
		}
		return substr( sanitize_text_field( rawurldecode( $path ) ), 0, 191 );
	}

	private static function rate_limited(): bool {
		// Behind Cloudways Varnish REMOTE_ADDR is the proxy, so prefer the first forwarded address.
		$ip = (string) ( $_SERVER['REMOTE_ADDR'] ?? '' );
		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = trim( explode( ',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'] )[0] );
		}
		$key = 's360ca_rl_' . substr( hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) ), 0, 20 );
		$n   = (int) get_transient( $key );
		if ( $n >= self::RATE_LIMIT_PER_HOUR ) {
			return true;
		}
		set_transient( $key, $n + 1, HOUR_IN_SECONDS );
		return false;
	}
}
