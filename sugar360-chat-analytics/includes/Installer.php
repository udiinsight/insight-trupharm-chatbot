<?php
/**
 * Schema for the two analytics tables. Neither stores message text or IP addresses.
 *
 * - s360ca_events: front-end events from the tracker (open, message, handoff click, product click…).
 * - s360ca_chats:  one row per AI Engine conversation, synced from wp_mwai_chats (which AI Engine
 *                  prunes after 90 days) plus the AI-assigned topic.
 *
 * @package S360Analytics
 */

namespace S360Analytics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Installer {

	public const SCHEMA_VERSION = '1.0.0';
	private const SCHEMA_OPTION = 's360ca_schema_version';
	public const STARTED_OPTION = 's360ca_tracking_started';

	public static function maybe_upgrade(): void {
		if ( get_option( self::SCHEMA_OPTION ) === self::SCHEMA_VERSION ) {
			return;
		}
		self::install();
		update_option( self::SCHEMA_OPTION, self::SCHEMA_VERSION, false );
		add_option( self::STARTED_OPTION, gmdate( 'Y-m-d H:i:s' ), '', false );
	}

	public static function table_events(): string {
		global $wpdb;
		return $wpdb->prefix . 's360ca_events';
	}

	public static function table_chats(): string {
		global $wpdb;
		return $wpdb->prefix . 's360ca_chats';
	}

	private static function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$events  = self::table_events();
		$chats   = self::table_chats();

		// dbDelta requires this exact whitespace pattern, do not reformat.
		dbDelta(
			"CREATE TABLE {$events} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			created_at DATETIME NOT NULL,
			event VARCHAR(24) NOT NULL DEFAULT '',
			chat_id VARCHAR(64) NOT NULL DEFAULT '',
			visitor_id VARCHAR(32) NOT NULL DEFAULT '',
			channel VARCHAR(24) NOT NULL DEFAULT '',
			page VARCHAR(191) NOT NULL DEFAULT '',
			device VARCHAR(8) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY created_at (created_at),
			KEY event (event),
			KEY chat_id (chat_id)
		) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$chats} (
			chat_id VARCHAR(64) NOT NULL,
			started_at DATETIME NOT NULL,
			last_at DATETIME NOT NULL,
			user_messages INT UNSIGNED NOT NULL DEFAULT 0,
			bot_referred TINYINT(1) NOT NULL DEFAULT 0,
			disclaimer VARCHAR(16) NOT NULL DEFAULT '',
			topic VARCHAR(24) NULL,
			answered TINYINT(1) NULL,
			classify_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY  (chat_id),
			KEY started_at (started_at),
			KEY topic (topic)
		) {$charset};"
		);
	}
}
