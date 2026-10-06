<?php
/**
 * Links WooCommerce orders to a chatbot conversation.
 *
 * An order counts as "after a chat" when the buyer sent the bot a message (or clicked a product
 * link it offered) within WINDOW_DAYS before placing the order. The tracker passes the last chat
 * id + timestamp in a hidden checkout field, with a first-party cookie as the fallback.
 *
 * @package S360Analytics
 */

namespace S360Analytics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Attribution {

	public const WINDOW_DAYS = 7;

	public const META_CHAT    = '_s360ca_chat_id';
	public const META_CHAT_AT = '_s360ca_chat_at';
	public const META_VISITOR = '_s360ca_visitor_id';

	public static function register(): void {
		add_action( 'woocommerce_checkout_create_order', [ __CLASS__, 'from_classic_checkout' ], 10, 1 );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', [ __CLASS__, 'from_cookie' ], 10, 1 );
	}

	public static function from_classic_checkout( $order ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verified the checkout nonce.
		$raw = isset( $_POST['s360ca_attr'] ) ? (string) wp_unslash( $_POST['s360ca_attr'] ) : '';
		if ( ! self::apply( $order, $raw ) ) {
			self::from_cookie( $order );
		}
	}

	public static function from_cookie( $order ): void {
		$raw = isset( $_COOKIE['s360ca_attr'] ) ? (string) wp_unslash( $_COOKIE['s360ca_attr'] ) : '';
		self::apply( $order, $raw );
	}

	/**
	 * @param \WC_Order $order
	 * @param string    $raw   "chatId|unixTime|visitorId"
	 */
	public static function apply( $order, string $raw, ?int $now = null ): bool {
		if ( ! $order instanceof \WC_Order || $raw === '' ) {
			return false;
		}
		$parts   = explode( '|', $raw );
		$chat_id = Tracker::id_or_empty( $parts[0] ?? '', 64 );
		$ts      = (int) ( $parts[1] ?? 0 );
		$visitor = Tracker::id_or_empty( $parts[2] ?? '', 32 );
		$now     = $now ?? time();

		// Client clocks drift, so allow a small skew into the future.
		if ( $chat_id === '' || $ts > $now + 600 || $ts < $now - self::WINDOW_DAYS * DAY_IN_SECONDS ) {
			return false;
		}

		$order->update_meta_data( self::META_CHAT, $chat_id );
		$order->update_meta_data( self::META_CHAT_AT, gmdate( 'Y-m-d H:i:s', $ts ) );
		if ( $visitor !== '' ) {
			$order->update_meta_data( self::META_VISITOR, $visitor );
		}
		return true;
	}
}
