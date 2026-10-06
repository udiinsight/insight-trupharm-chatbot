<?php
/**
 * Emails the previous month's report once, on or after the 1st of each month.
 * Nothing is sent until recipients are set on the report page.
 *
 * @package S360Analytics
 */

namespace S360Analytics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Mailer {

	public const CRON_HOOK         = 's360ca_monthly_report';
	public const RECIPIENTS_OPTION = 's360ca_recipients';
	private const LAST_SENT_OPTION = 's360ca_last_report_month';

	public static function register(): void {
		add_action( 'init', [ __CLASS__, 'schedule' ] );
		add_action( self::CRON_HOOK, [ __CLASS__, 'maybe_send_monthly' ] );
	}

	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			// Daily check at ~08:00 site time; sends only when last month has not been sent yet.
			$next = new \DateTimeImmutable( 'tomorrow 08:00', wp_timezone() );
			wp_schedule_event( $next->getTimestamp(), 'daily', self::CRON_HOOK );
		}
	}

	public static function recipients(): array {
		$list = preg_split( '/[\s,;]+/', (string) get_option( self::RECIPIENTS_OPTION, '' ) );
		return array_values( array_filter( array_map( 'sanitize_email', $list ), 'is_email' ) );
	}

	public static function maybe_send_monthly(): void {
		[ $from, $to ] = Report::previous_month();
		$month         = substr( $from, 0, 7 );
		if ( get_option( self::LAST_SENT_OPTION ) === $month || ! self::recipients() ) {
			return;
		}
		Sync::run();
		if ( self::send( self::recipients(), $from, $to ) ) {
			update_option( self::LAST_SENT_OPTION, $month, false );
		}
	}

	public static function send( array $to_emails, string $from, string $to ): bool {
		$data    = Report::build( $from, $to );
		$subject = sprintf( 'דו"ח צ\'אטבוט Sugar360 – %s', wp_date( 'm/Y', strtotime( $from . ' 12:00:00' ) ) );
		$link    = admin_url( 'admin.php?page=' . Admin::SLUG . '&from=' . $from . '&to=' . $to );
		$body    = '<html><body style="margin:0;padding:20px;background:#ffffff">' . Report::render( $data )
			. '<p dir="rtl" style="font-family:Arial,sans-serif;font-size:13px;margin-top:18px"><a href="' . esc_url( $link ) . '">לדו"ח המלא ולהורדת הנתונים באתר</a></p></body></html>';

		return wp_mail( $to_emails, $subject, $body, [ 'Content-Type: text/html; charset=UTF-8' ] );
	}
}
