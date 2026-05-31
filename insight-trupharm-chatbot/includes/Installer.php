<?php
/**
 * Schema management for Insight Chat.
 *
 * Owns one custom table (wp_insight_chat_logs) used for chat-quality auditing.
 * The schema is created/upgraded idempotently on every page load when the stored
 * version is older than {@see Installer::SCHEMA_VERSION}, so the mu-plugin works
 * without an activation hook (mu-plugins do not have one).
 *
 * @package InsightChat
 */

namespace InsightChat;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Installer {

	public const SCHEMA_VERSION       = '1.0.0';
	private const SCHEMA_OPTION       = 'insight_chat_schema_version';
	public const TABLE_LOGS_BASE      = 'insight_chat_logs';

	public static function maybe_upgrade(): void {
		if ( get_option( self::SCHEMA_OPTION ) === self::SCHEMA_VERSION ) {
			return;
		}
		self::install();
		update_option( self::SCHEMA_OPTION, self::SCHEMA_VERSION, false );
	}

	public static function table_logs(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_LOGS_BASE;
	}

	private static function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table_logs();
		$charset = $wpdb->get_charset_collate();

		// dbDelta requires this exact whitespace pattern, do not reformat.
		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			ts DATETIME NOT NULL,
			session_id VARCHAR(64) NOT NULL DEFAULT '',
			ip_hash VARCHAR(64) NOT NULL DEFAULT '',
			lang VARCHAR(8) NOT NULL DEFAULT '',
			event_type VARCHAR(32) NOT NULL DEFAULT '',
			meta TEXT NULL,
			PRIMARY KEY  (id),
			KEY ts (ts),
			KEY session_id (session_id),
			KEY event_type (event_type)
		) {$charset};";

		dbDelta( $sql );
	}

	/**
	 * Optional uninstall hook. Called from a top-level uninstall.php if/when
	 * the project is converted from a mu-plugin to a regular plugin.
	 */
	public static function uninstall(): void {
		global $wpdb;
		$table = self::table_logs();
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		delete_option( self::SCHEMA_OPTION );
	}
}
