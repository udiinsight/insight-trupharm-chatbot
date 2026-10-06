<?php
/**
 * Plugin Name: Insight - Sugar360 - Chatbot Analytics
 * Description: Monthly performance report for the Sugar360 chatbot (Noa) — conversations, handoffs to customer service, orders after a chat, topics. Stores no message text.
 * Version: 1.0.0
 * Author: Insight Marketing
 * Author URI: https://insight-marketing.co.il
 * Text Domain: sugar360-chat-analytics
 * Requires at least: 6.0
 * Requires PHP: 8.0
 *
 * @package S360Analytics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'S360CA_VERSION', '1.0.0' );
define( 'S360CA_FILE', __FILE__ );
define( 'S360CA_PATH', __DIR__ );

spl_autoload_register(
	static function ( $class ) {
		if ( strpos( $class, 'S360Analytics\\' ) !== 0 ) {
			return;
		}
		$file = S360CA_PATH . '/includes/' . substr( $class, strlen( 'S360Analytics\\' ) ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

register_activation_hook(
	__FILE__,
	static function () {
		\S360Analytics\Installer::maybe_upgrade();
	}
);

register_deactivation_hook(
	__FILE__,
	static function () {
		wp_clear_scheduled_hook( \S360Analytics\Sync::CRON_HOOK );
		wp_clear_scheduled_hook( \S360Analytics\Mailer::CRON_HOOK );
	}
);

add_action(
	'plugins_loaded',
	static function () {
		\S360Analytics\Installer::maybe_upgrade();
		\S360Analytics\Tracker::register();
		\S360Analytics\Attribution::register();
		\S360Analytics\Sync::register();
		\S360Analytics\Mailer::register();
		if ( is_admin() ) {
			\S360Analytics\Admin::register();
		}
	}
);
