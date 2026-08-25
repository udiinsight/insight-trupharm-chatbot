<?php
/**
 * Plugin Name: Insight - Trupharm - Chatbot
 * Plugin URI: https://github.com/udiinsight/insight-trupharm-chatbot
 * Description: Hebrew RTL AI support chatbot for SUGAR360 (Tropharm) — document-grounded answers via AI Engine Pro + Pinecone, with a custom React widget, lead routing, and medical safeguards.
 * Version: 1.0.12
 * Author: Insight Marketing
 * Author URI: https://insight-marketing.co.il
 * Text Domain: insight-trupharm-chatbot
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 *
 * @package InsightChat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'INSIGHT_TRUPHARM_CHATBOT_VERSION', '1.0.12' );

// Internal constants reused by the bundled modules (carried over from the shared Insight Chat codebase).
define( 'INSIGHT_CHAT_VERSION', INSIGHT_TRUPHARM_CHATBOT_VERSION );
define( 'INSIGHT_CHAT_PATH', __DIR__ );
define( 'INSIGHT_CHAT_FILE', __FILE__ );

/**
 * PSR-style autoloader for the InsightChat\ namespace → /includes/.
 */
spl_autoload_register(
	static function ( $class ) {
		if ( strpos( $class, 'InsightChat\\' ) !== 0 ) {
			return;
		}
		$relative = str_replace( [ 'InsightChat\\', '\\' ], [ '', '/' ], $class );
		$file     = INSIGHT_CHAT_PATH . '/includes/' . $relative . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

/**
 * Activation — create the chat-logs table.
 */
register_activation_hook(
	__FILE__,
	static function () {
		\InsightChat\Installer::maybe_upgrade();
	}
);

/**
 * Deactivation — clear our scheduled cron events.
 */
register_deactivation_hook(
	__FILE__,
	static function () {
		wp_clear_scheduled_hook( \InsightChat\QueryLog::CRON_HOOK );
		wp_clear_scheduled_hook( \InsightChat\AiEngineGuard::CRON_HOOK );
	}
);

/**
 * Bootstrap the chatbot once all plugins are loaded.
 */
add_action(
	'plugins_loaded',
	static function () {
		// Keep the logs-table schema current. WP Pusher updates do not fire the activation hook,
		// so this idempotent check covers schema changes shipped in an update.
		\InsightChat\Installer::maybe_upgrade();

		\InsightChat\AiEngineGuard::register();
		\InsightChat\ContextEnricher::register();
		\InsightChat\QueryLog::register();
		\InsightChat\RateLimiter::register();
		\InsightChat\RestApi::register();
		\InsightChat\WidgetEnqueue::register();

		// Non-blocking dependency hint: retrieval + the LLM call run through AI Engine. The widget
		// still loads without it, but answers will fail until AI Engine is installed and configured.
		if ( is_admin() ) {
			$ai_engine_present = defined( 'MWAI_VERSION' )
				|| class_exists( 'Meow_MWAI_Core' )
				|| class_exists( 'MeowPro_MWAI_Core' )
				|| (bool) get_option( 'mwai_options' );

			if ( ! $ai_engine_present ) {
				add_action(
					'admin_notices',
					static function () {
						if ( ! current_user_can( 'activate_plugins' ) ) {
							return;
						}
						echo '<div class="notice notice-warning"><p>';
						echo esc_html__(
							'Insight - Trupharm - Chatbot: AI Engine (or AI Engine Pro) was not detected. The chat widget will load, but answers require AI Engine to be installed and configured.',
							'insight-trupharm-chatbot'
						);
						echo '</p></div>';
					}
				);
			}
		}
	}
);
