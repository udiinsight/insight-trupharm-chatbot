<?php
/**
 * Admin report page: period picker, the report, CSV export, and email settings.
 *
 * @package S360Analytics
 */

namespace S360Analytics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin {

	public const SLUG = 's360-chat-report';

	public static function register(): void {
		add_action( 'admin_menu', [ __CLASS__, 'menu' ] );
		add_action( 'admin_post_s360ca_csv', [ __CLASS__, 'csv' ] );
		add_action( 'admin_post_s360ca_settings', [ __CLASS__, 'save_settings' ] );
		add_action( 'admin_post_s360ca_send_test', [ __CLASS__, 'send_test' ] );
	}

	public static function capability(): string {
		return (string) apply_filters( 's360ca/capability', 'manage_woocommerce' );
	}

	public static function menu(): void {
		add_menu_page( 'דו"ח צ\'אטבוט', 'דו"ח צ\'אטבוט', self::capability(), self::SLUG, [ __CLASS__, 'page' ], 'dashicons-chart-bar', 57 );
	}

	/**
	 * @return array{0:string,1:string}
	 */
	private static function period(): array {
		$valid = static fn( $d ) => is_string( $d ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ? $d : null;
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters.
		$from = $valid( $_GET['from'] ?? null );
		$to   = $valid( $_GET['to'] ?? null );
		// phpcs:enable
		if ( $from && $to && $from <= $to ) {
			return [ $from, $to ];
		}
		$now = new \DateTimeImmutable( 'now', wp_timezone() );
		return [ $now->format( 'Y-m-01' ), $now->format( 'Y-m-d' ) ];
	}

	public static function page(): void {
		if ( ! current_user_can( self::capability() ) ) {
			return;
		}
		Sync::sync_chats();
		[ $from, $to ] = self::period();
		$data          = Report::build( $from, $to );
		$base          = admin_url( 'admin.php?page=' . self::SLUG );
		$tz            = wp_timezone();

		echo '<div class="wrap" dir="rtl"><h1 style="margin-bottom:12px">דו"ח צ\'אטבוט</h1>';

		// Period picker: quick month links + custom range.
		echo '<div style="background:#fff;border:1px solid #dcdcde;padding:12px 14px;border-radius:6px;margin-bottom:16px">';
		echo '<div style="margin-bottom:8px"><strong>חודש: </strong>';
		for ( $i = 0; $i < 12; $i++ ) {
			$m      = ( new \DateTimeImmutable( 'first day of this month', $tz ) )->modify( "-{$i} month" );
			$mfrom  = $m->format( 'Y-m-01' );
			$mto    = $i === 0 ? ( new \DateTimeImmutable( 'now', $tz ) )->format( 'Y-m-d' ) : $m->format( 'Y-m-t' );
			$active = $mfrom === $from && $mto === $to;
			printf(
				'<a href="%s" style="margin-left:10px;%s">%s</a>',
				esc_url( add_query_arg( [ 'from' => $mfrom, 'to' => $mto ], $base ) ),
				$active ? 'font-weight:700;text-decoration:none;color:#01615F' : '',
				esc_html( $m->format( 'm/Y' ) )
			);
		}
		echo '</div>';
		printf(
			'<form method="get" style="display:inline-flex;gap:8px;align-items:center"><input type="hidden" name="page" value="%s">מתאריך <input type="date" name="from" value="%s"> עד <input type="date" name="to" value="%s"> <button class="button">הצג</button></form>',
			esc_attr( self::SLUG ),
			esc_attr( $from ),
			esc_attr( $to )
		);
		printf(
			' <a class="button button-primary" style="margin-right:12px" href="%s">הורדת נתוני השיחות (CSV)</a>',
			esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=s360ca_csv&from=' . $from . '&to=' . $to ), 's360ca_csv' ) )
		);
		echo '</div>';

		echo '<div style="background:#fff;border:1px solid #dcdcde;padding:20px;border-radius:6px">';
		echo Report::render( $data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside render().
		echo '</div>';

		self::settings_box();
		echo '</div>';
	}

	private static function settings_box(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$notice = isset( $_GET['s360ca_msg'] ) ? sanitize_key( $_GET['s360ca_msg'] ) : '';
		$texts  = [
			'saved'     => 'ההגדרות נשמרו.',
			'sent'      => 'דו"ח בדיקה נשלח לכתובת שלך.',
			'send_fail' => 'שליחת המייל נכשלה.',
		];

		echo '<div style="background:#fff;border:1px solid #dcdcde;padding:16px 20px;border-radius:6px;margin-top:16px;max-width:760px">';
		echo '<h2 style="margin-top:0">דו"ח חודשי במייל</h2>';
		if ( isset( $texts[ $notice ] ) ) {
			echo '<div class="notice notice-info inline"><p>' . esc_html( $texts[ $notice ] ) . '</p></div>';
		}
		echo '<p>בתחילת כל חודש יישלח דו"ח של החודש הקודם לכתובות הבאות (מופרדות בפסיק). ריק = לא נשלח.</p>';
		printf(
			'<form method="post" action="%s"><input type="hidden" name="action" value="s360ca_settings">%s<input type="text" name="recipients" value="%s" style="width:100%%;max-width:520px" dir="ltr"> <button class="button button-primary">שמירה</button></form>',
			esc_url( admin_url( 'admin-post.php' ) ),
			wp_nonce_field( 's360ca_settings', '_wpnonce', true, false ),
			esc_attr( (string) get_option( Mailer::RECIPIENTS_OPTION, '' ) )
		);
		printf(
			'<form method="post" action="%s" style="margin-top:10px"><input type="hidden" name="action" value="s360ca_send_test">%s<button class="button">שליחת דו"ח החודש הקודם אליי (%s)</button></form>',
			esc_url( admin_url( 'admin-post.php' ) ),
			wp_nonce_field( 's360ca_send_test', '_wpnonce', true, false ),
			esc_html( wp_get_current_user()->user_email )
		);
		echo '</div>';
	}

	public static function save_settings(): void {
		if ( ! current_user_can( self::capability() ) || ! check_admin_referer( 's360ca_settings' ) ) {
			wp_die( 'Forbidden', 403 );
		}
		$raw = isset( $_POST['recipients'] ) ? sanitize_text_field( wp_unslash( $_POST['recipients'] ) ) : '';
		update_option( Mailer::RECIPIENTS_OPTION, implode( ', ', array_filter( array_map( 'sanitize_email', preg_split( '/[\s,;]+/', $raw ) ), 'is_email' ) ), false );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '&s360ca_msg=saved' ) );
		exit;
	}

	public static function send_test(): void {
		if ( ! current_user_can( self::capability() ) || ! check_admin_referer( 's360ca_send_test' ) ) {
			wp_die( 'Forbidden', 403 );
		}
		[ $from, $to ] = Report::previous_month();
		$ok            = Mailer::send( [ wp_get_current_user()->user_email ], $from, $to );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '&s360ca_msg=' . ( $ok ? 'sent' : 'send_fail' ) ) );
		exit;
	}

	public static function csv(): void {
		if ( ! current_user_can( self::capability() ) || ! check_admin_referer( 's360ca_csv' ) ) {
			wp_die( 'Forbidden', 403 );
		}
		Sync::sync_chats();
		[ $from, $to ] = self::period();
		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="chatbot-' . $from . '_' . $to . '.csv"' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // BOM so Excel opens the Hebrew correctly.
		foreach ( Report::csv_rows( $from, $to ) as $row ) {
			fputcsv( $out, $row );
		}
		fclose( $out );
		exit;
	}
}
