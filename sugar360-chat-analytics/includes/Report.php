<?php
/**
 * Builds the report numbers for a date range (site timezone) and renders them as email-safe HTML.
 *
 * Definitions (also printed at the bottom of the report):
 * - Conversation: an AI Engine discussion with at least one visitor message.
 * - Passed to customer service: the visitor clicked a WhatsApp / phone / contact-page link the bot offered.
 * - Closed by the bot: a conversation with no such click.
 * - Order after a chat: a paid order whose buyer messaged the bot (or clicked a product link it
 *   offered) within Attribution::WINDOW_DAYS days before ordering.
 *
 * @package S360Analytics
 */

namespace S360Analytics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Report {

	public const CHANNEL_LABELS = [
		'whatsapp' => 'וואטסאפ',
		'phone'    => 'טלפון',
		'contact'  => 'דף שירות לקוחות',
	];

	public const DEVICE_LABELS = [
		'mobile'  => 'נייד',
		'tablet'  => 'טאבלט',
		'desktop' => 'מחשב',
		''        => 'לא ידוע',
	];

	/**
	 * @param string $from Y-m-d (site timezone, inclusive)
	 * @param string $to   Y-m-d (site timezone, inclusive)
	 */
	public static function build( string $from, string $to ): array {
		global $wpdb;
		[ $start, $end ] = self::utc_bounds( $from, $to );
		$chats  = Installer::table_chats();
		$events = Installer::table_events();

		$conv = $wpdb->get_results(
			$wpdb->prepare( "SELECT chat_id, started_at, user_messages, bot_referred, disclaimer, topic, answered FROM {$chats} WHERE started_at >= %s AND started_at < %s", $start, $end ),
			ARRAY_A
		);
		$n_conv = count( $conv );

		// Clicks and orders are only known for conversations that started after the tracker went live.
		$tracking_start = (string) get_option( Installer::STARTED_OPTION, '' );
		$tracked_set    = [];
		foreach ( $conv as $c ) {
			if ( $c['started_at'] >= $tracking_start ) {
				$tracked_set[ $c['chat_id'] ] = true;
			}
		}
		$n_tracked = count( $tracked_set );

		$ev = $wpdb->get_results(
			$wpdb->prepare( "SELECT created_at, event, chat_id, visitor_id, channel, page, device FROM {$events} WHERE created_at >= %s AND created_at < %s", $start, $end ),
			ARRAY_A
		);

		$opens         = 0;
		$tracked_msgs  = 0;
		$visitors      = [];
		$handoff_chats = [];
		$handoff_by    = array_fill_keys( array_keys( self::CHANNEL_LABELS ), [] );
		$product_chats = [];
		$devices       = [];
		$pages         = [];
		$hours         = array_fill( 0, 24, 0 );
		$errors        = 0;
		$tz            = wp_timezone();

		foreach ( $ev as $e ) {
			$cid = $e['chat_id'];
			switch ( $e['event'] ) {
				case 'open':
					++$opens;
					break;
				case 'error':
					++$errors;
					break;
				case 'message':
					++$tracked_msgs;
					if ( $e['visitor_id'] !== '' ) {
						$visitors[ $e['visitor_id'] ] = true;
					}
					if ( $cid !== '' ) {
						$devices[ $cid ] = $devices[ $cid ] ?? $e['device'];
						$pages[ $cid ]   = $pages[ $cid ] ?? $e['page'];
					}
					$local = ( new \DateTimeImmutable( $e['created_at'], new \DateTimeZone( 'UTC' ) ) )->setTimezone( $tz );
					++$hours[ (int) $local->format( 'G' ) ];
					break;
				case 'handoff':
					// A click without a chat id (should not happen) still counts as its own handoff.
					$key                               = $cid !== '' ? $cid : 'evt' . count( $handoff_chats );
					$handoff_chats[ $key ]             = true;
					$handoff_by[ $e['channel'] ][ $key ] = true;
					break;
				case 'product_click':
					$product_chats[ $cid !== '' ? $cid : 'evt' . count( $product_chats ) ] = true;
					break;
			}
		}

		$n_handoff = count( $handoff_chats );
		$closed    = max( 0, $n_tracked - count( array_intersect_key( $handoff_chats, $tracked_set ) ) );

		$topics     = [];
		$classified = 0;
		$answered   = 0;
		$messages   = 0;
		$referred   = 0;
		$single     = 0;
		$disclaim   = [ 'personal' => 0, 'emergency' => 0 ];
		foreach ( $conv as $c ) {
			$messages += (int) $c['user_messages'];
			$referred += (int) $c['bot_referred'];
			if ( (int) $c['user_messages'] === 1 ) {
				++$single;
			}
			if ( isset( $disclaim[ $c['disclaimer'] ] ) ) {
				++$disclaim[ $c['disclaimer'] ];
			}
			if ( $c['topic'] !== null ) {
				$topics[ $c['topic'] ] = ( $topics[ $c['topic'] ] ?? 0 ) + 1;
			}
			if ( $c['answered'] !== null ) {
				++$classified;
				$answered += (int) $c['answered'];
			}
		}
		arsort( $topics );

		$device_counts = [];
		foreach ( $devices as $d ) {
			$device_counts[ $d ] = ( $device_counts[ $d ] ?? 0 ) + 1;
		}
		arsort( $device_counts );

		$page_counts = [];
		foreach ( $pages as $p ) {
			$page_counts[ $p ] = ( $page_counts[ $p ] ?? 0 ) + 1;
		}
		arsort( $page_counts );

		$orders  = self::orders( $start, $end );
		$revenue = array_sum( array_column( $orders, 'total' ) );

		return [
			'from'            => $from,
			'to'              => $to,
			'tracking_since'  => self::tracking_since(),
			'conversations'   => $n_conv,
			'tracked'         => $n_tracked,
			'visitors'        => count( $visitors ),
			'opens'           => $opens,
			'messages'        => $messages ?: $tracked_msgs,
			'avg_messages'    => $n_conv ? round( $messages / $n_conv, 1 ) : 0,
			'single_message'  => $single,
			'handoffs'        => $n_handoff,
			'handoff_by'      => array_map( 'count', $handoff_by ),
			'closed_by_bot'   => $closed,
			'bot_referred'    => $referred,
			'product_clicks'  => count( $product_chats ),
			'orders'          => $orders,
			'orders_count'    => count( $orders ),
			'revenue'         => $revenue,
			'topics'          => $topics,
			'classified'      => $classified,
			'answered'        => $answered,
			'disclaimers'     => $disclaim,
			'devices'         => $device_counts,
			'pages'           => array_slice( $page_counts, 0, 8, true ),
			'hours'           => $hours,
			'errors'          => $errors,
			'currency'        => function_exists( 'get_woocommerce_currency_symbol' ) ? html_entity_decode( get_woocommerce_currency_symbol() ) : '₪',
		];
	}

	/**
	 * Paid orders created in the range that carry a chat attribution.
	 */
	public static function orders( string $start, string $end ): array {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return [];
		}
		$tz  = wp_timezone();
		$ids = wc_get_orders(
			[
				'limit'        => -1,
				'return'       => 'ids',
				'status'       => wc_get_is_paid_statuses(),
				'date_created' => strtotime( $start . ' UTC' ) . '...' . ( strtotime( $end . ' UTC' ) - 1 ),
				'meta_query'   => [
					[
						'key'     => Attribution::META_CHAT,
						'compare' => 'EXISTS',
					],
				],
			]
		);
		$out = [];
		foreach ( $ids as $id ) {
			$order = wc_get_order( $id );
			if ( ! $order ) {
				continue;
			}
			$created = $order->get_date_created();
			$chat_at = new \DateTimeImmutable( (string) $order->get_meta( Attribution::META_CHAT_AT ), new \DateTimeZone( 'UTC' ) );
			$out[]   = [
				'id'         => $order->get_id(),
				'number'     => $order->get_order_number(),
				'date'       => $created ? $created->setTimezone( $tz )->format( 'd/m/Y H:i' ) : '',
				'total'      => (float) $order->get_total(),
				'chat_id'    => (string) $order->get_meta( Attribution::META_CHAT ),
				'hours_after'=> $created ? max( 0, round( ( $created->getTimestamp() - $chat_at->getTimestamp() ) / HOUR_IN_SECONDS, 1 ) ) : null,
			];
		}
		return $out;
	}

	/**
	 * One CSV row per conversation. No message text.
	 */
	public static function csv_rows( string $from, string $to ): array {
		global $wpdb;
		[ $start, $end ] = self::utc_bounds( $from, $to );
		$chats  = Installer::table_chats();
		$events = Installer::table_events();
		$tz     = wp_timezone();

		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$chats} WHERE started_at >= %s AND started_at < %s ORDER BY started_at ASC", $start, $end ),
			ARRAY_A
		);

		$ids   = array_column( $rows, 'chat_id' );
		$extra = [];
		if ( $ids ) {
			$in = implode( ',', array_fill( 0, count( $ids ), '%s' ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$ev = $wpdb->get_results( $wpdb->prepare( "SELECT chat_id, event, channel, page, device FROM {$events} WHERE chat_id IN ({$in}) ORDER BY id ASC", $ids ), ARRAY_A );
			foreach ( $ev as $e ) {
				$x = &$extra[ $e['chat_id'] ];
				if ( $e['event'] === 'handoff' ) {
					$x['handoff'][ $e['channel'] ] = self::CHANNEL_LABELS[ $e['channel'] ] ?? $e['channel'];
				} elseif ( $e['event'] === 'product_click' ) {
					$x['product'] = true;
				} elseif ( $e['event'] === 'message' ) {
					$x['page']   = $x['page'] ?? $e['page'];
					$x['device'] = $x['device'] ?? $e['device'];
				}
				unset( $x );
			}
		}

		$orders = [];
		foreach ( self::orders( $start, gmdate( 'Y-m-d H:i:s', strtotime( $end . ' UTC' ) + Attribution::WINDOW_DAYS * DAY_IN_SECONDS ) ) as $o ) {
			$orders[ $o['chat_id'] ] = $o;
		}

		$out   = [];
		$out[] = [ 'מזהה שיחה', 'התחלה', 'הודעות מבקר', 'נושא', 'הבוט ענה', 'הבוט הפנה לשירות', 'הועבר לשירות (קליק)', 'קליק לדף מוצר', 'דף', 'מכשיר', 'הזמנה', 'סכום' ];
		foreach ( $rows as $r ) {
			$x     = $extra[ $r['chat_id'] ] ?? [];
			$o     = $orders[ $r['chat_id'] ] ?? null;
			$out[] = [
				$r['chat_id'],
				( new \DateTimeImmutable( $r['started_at'], new \DateTimeZone( 'UTC' ) ) )->setTimezone( $tz )->format( 'Y-m-d H:i' ),
				(int) $r['user_messages'],
				$r['topic'] !== null ? ( Sync::TOPICS[ $r['topic'] ] ?? $r['topic'] ) : '',
				$r['answered'] === null ? '' : ( (int) $r['answered'] ? 'כן' : 'לא' ),
				(int) $r['bot_referred'] ? 'כן' : 'לא',
				isset( $x['handoff'] ) ? implode( ' + ', $x['handoff'] ) : '',
				! empty( $x['product'] ) ? 'כן' : '',
				$x['page'] ?? '',
				self::DEVICE_LABELS[ $x['device'] ?? '' ] ?? '',
				$o ? '#' . $o['number'] : '',
				$o ? $o['total'] : '',
			];
		}
		return $out;
	}

	/**
	 * @return array{0:string,1:string} UTC [start, end) for the local dates.
	 */
	public static function utc_bounds( string $from, string $to ): array {
		$tz    = wp_timezone();
		$utc   = new \DateTimeZone( 'UTC' );
		$start = ( new \DateTimeImmutable( $from . ' 00:00:00', $tz ) )->setTimezone( $utc );
		$end   = ( new \DateTimeImmutable( $to . ' 00:00:00', $tz ) )->modify( '+1 day' )->setTimezone( $utc );
		return [ $start->format( 'Y-m-d H:i:s' ), $end->format( 'Y-m-d H:i:s' ) ];
	}

	/**
	 * @return array{0:string,1:string} first and last local date of the previous month.
	 */
	public static function previous_month(): array {
		$first = ( new \DateTimeImmutable( 'first day of last month', wp_timezone() ) );
		return [ $first->format( 'Y-m-01' ), $first->format( 'Y-m-t' ) ];
	}

	private static function tracking_since(): string {
		$started = (string) get_option( Installer::STARTED_OPTION, '' );
		if ( $started === '' ) {
			return '';
		}
		return ( new \DateTimeImmutable( $started, new \DateTimeZone( 'UTC' ) ) )->setTimezone( wp_timezone() )->format( 'd/m/Y' );
	}

	// ------------------------------------------------------------------ rendering

	private static function pct( int $part, int $whole ): string {
		return $whole ? round( 100 * $part / $whole ) . '%' : '—';
	}

	private static function money( float $amount, string $symbol ): string {
		return number_format( $amount, 0, '.', ',' ) . ' ' . $symbol;
	}

	private static function bars( array $items, int $total ): string {
		if ( ! $items ) {
			return '<p style="color:#6b7280;margin:0">אין נתונים לתקופה.</p>';
		}
		$max  = max( $items ) ?: 1;
		$html = '<table role="presentation" style="width:100%;border-collapse:collapse;font-size:14px">';
		foreach ( $items as $label => $n ) {
			$w     = max( 2, (int) round( 100 * $n / $max ) );
			$html .= '<tr><td style="padding:4px 0 4px 12px;width:40%;color:#111827">' . esc_html( (string) $label ) . '</td>'
				. '<td style="padding:4px 0"><div style="background:#01615F;height:10px;border-radius:5px;width:' . $w . '%"></div></td>'
				. '<td style="padding:4px 12px 4px 0;width:90px;text-align:left;color:#374151;white-space:nowrap">' . (int) $n . ' (' . self::pct( (int) $n, $total ) . ')</td></tr>';
		}
		return $html . '</table>';
	}

	private static function card( string $label, string $value, string $sub = '' ): string {
		return '<td style="padding:6px;width:33%;vertical-align:top"><div style="border:1px solid #d1e7e6;border-radius:10px;padding:14px;background:#f5fbfa">'
			. '<div style="font-size:13px;color:#4b5563">' . esc_html( $label ) . '</div>'
			. '<div style="font-size:26px;font-weight:700;color:#01615F;margin-top:4px">' . esc_html( $value ) . '</div>'
			. ( $sub !== '' ? '<div style="font-size:12px;color:#6b7280;margin-top:2px">' . esc_html( $sub ) . '</div>' : '' )
			. '</div></td>';
	}

	private static function section( string $title, string $body ): string {
		return '<h3 style="font-size:16px;color:#01615F;margin:26px 0 8px;border-bottom:1px solid #e5e7eb;padding-bottom:6px">' . esc_html( $title ) . '</h3>' . $body;
	}

	public static function render( array $d ): string {
		$from = ( new \DateTimeImmutable( $d['from'] ) )->format( 'd/m/Y' );
		$to   = ( new \DateTimeImmutable( $d['to'] ) )->format( 'd/m/Y' );
		$conv    = (int) $d['conversations'];
		$tracked = (int) $d['tracked'];
		$partial = $tracked < $conv ? ' (מתוך ' . $tracked . ' שיחות שנמדדו)' : '';

		$h  = '<div dir="rtl" style="font-family:Arial,Helvetica,sans-serif;color:#111827;max-width:760px;line-height:1.5;text-align:right">';
		$h .= '<h2 style="margin:0 0 4px;font-size:22px;color:#01615F">דו"ח ביצועי הצ\'אטבוט – נועה</h2>';
		$h .= '<div style="color:#4b5563;font-size:14px">תקופה: ' . esc_html( $from . ' – ' . $to ) . '</div>';

		$h .= '<table role="presentation" style="width:100%;border-collapse:collapse;margin-top:14px"><tr>'
			. self::card( 'שיחות', (string) $conv, $d['visitors'] ? $d['visitors'] . ' מבקרים ייחודיים' : '' )
			. self::card( 'נסגרו ע"י הבוט', self::pct( (int) $d['closed_by_bot'], $tracked ), $d['closed_by_bot'] . ' שיחות ללא העברה לשירות' . $partial )
			. self::card( 'הועברו לשירות הלקוחות', (string) $d['handoffs'], self::pct( (int) $d['handoffs'], $tracked ) . ' מהשיחות' . $partial )
			. '</tr><tr>'
			. self::card( 'הזמנות אחרי שיחה', (string) $d['orders_count'], $d['orders_count'] ? self::money( (float) $d['revenue'], $d['currency'] ) : '' )
			. self::card( 'יחס המרה', self::pct( (int) $d['orders_count'], $tracked ), 'הזמנות מתוך שיחות' . $partial )
			. self::card( 'הבוט ענה לשאלה', $d['classified'] ? self::pct( (int) $d['answered'], (int) $d['classified'] ) : '—', $d['classified'] ? 'מתוך ' . $d['classified'] . ' שיחות שנותחו' : '' )
			. '</tr></table>';

		$h .= self::section(
			'העברות לשירות הלקוחות לפי ערוץ',
			self::bars( array_combine( array_values( self::CHANNEL_LABELS ), array_values( $d['handoff_by'] ) ), max( 1, (int) $d['handoffs'] ) )
			. '<p style="font-size:13px;color:#6b7280;margin:6px 0 0">הבוט הציע פנייה לשירות ב-' . (int) $d['bot_referred'] . ' שיחות; המבקר לחץ בפועל ב-' . (int) $d['handoffs'] . '.</p>'
		);

		$topics = [];
		foreach ( $d['topics'] as $key => $n ) {
			$topics[ Sync::TOPICS[ $key ] ?? $key ] = $n;
		}
		$h .= self::section( 'על מה שאלו', self::bars( $topics, max( 1, array_sum( $d['topics'] ) ) ) );

		if ( $d['orders'] ) {
			$rows = '';
			foreach ( $d['orders'] as $o ) {
				$rows .= '<tr><td style="padding:6px;border-bottom:1px solid #f3f4f6">#' . esc_html( $o['number'] ) . '</td>'
					. '<td style="padding:6px;border-bottom:1px solid #f3f4f6">' . esc_html( $o['date'] ) . '</td>'
					. '<td style="padding:6px;border-bottom:1px solid #f3f4f6">' . esc_html( self::money( $o['total'], $d['currency'] ) ) . '</td>'
					. '<td style="padding:6px;border-bottom:1px solid #f3f4f6">' . esc_html( self::since_chat( $o['hours_after'] ) ) . '</td></tr>';
			}
			$h .= self::section(
				'הזמנות אחרי שיחה עם הבוט',
				'<table style="width:100%;border-collapse:collapse;font-size:14px"><tr style="background:#f5fbfa"><th style="padding:6px;text-align:right">הזמנה</th><th style="padding:6px;text-align:right">תאריך</th><th style="padding:6px;text-align:right">סכום</th><th style="padding:6px;text-align:right">זמן מהשיחה</th></tr>' . $rows . '</table>'
			);
		}

		$activity = '<table role="presentation" style="width:100%;font-size:14px;border-collapse:collapse">'
			. self::kv( 'פתיחות של חלון הצ\'אט', (string) $d['opens'] )
			. self::kv( 'הודעות מבקרים', (string) $d['messages'] )
			. self::kv( 'ממוצע הודעות לשיחה', (string) $d['avg_messages'] )
			. self::kv( 'שיחות של הודעה אחת', $d['single_message'] . ' (' . self::pct( (int) $d['single_message'], $conv ) . ')' )
			. self::kv( 'שיחות עם לחיצה על קישור למוצר', (string) $d['product_clicks'] )
			. self::kv( 'שאלות רפואיות אישיות (הבוט הפנה לגורם מטפל)', (string) $d['disclaimers']['personal'] )
			. self::kv( 'מצבי חירום שזוהו', (string) $d['disclaimers']['emergency'] )
			. self::kv( 'תקלות טכניות בצ\'אט', (string) $d['errors'] )
			. '</table>';
		$h .= self::section( 'פעילות', $activity );

		$devices = [];
		foreach ( $d['devices'] as $k => $n ) {
			$devices[ self::DEVICE_LABELS[ $k ] ?? $k ] = $n;
		}
		$h .= self::section( 'מכשירים', self::bars( $devices, max( 1, array_sum( $d['devices'] ) ) ) );
		$h .= self::section( 'דפים שמהם התחילו שיחות', self::bars( self::page_labels( $d['pages'] ), max( 1, array_sum( $d['devices'] ) ) ) );
		$h .= self::section( 'שעות פעילות (הודעות לפי שעה)', self::hours( $d['hours'] ) );

		$h .= '<div style="margin-top:28px;padding:12px 14px;background:#f9fafb;border-radius:8px;font-size:12px;color:#4b5563">'
			. '<strong>הגדרות:</strong> '
			. '<b>שיחה</b> – שיחה עם לפחות הודעה אחת של מבקר. '
			. '<b>הועבר לשירות</b> – המבקר לחץ על וואטסאפ, טלפון או דף שירות הלקוחות שהבוט הציע. '
			. '<b>נסגרה ע"י הבוט</b> – שיחה ללא העברה כזו. '
			. '<b>הזמנה אחרי שיחה</b> – הזמנה ששולמה, שהמזמין שוחח עם הבוט או לחץ על קישור מוצר ממנו עד ' . Attribution::WINDOW_DAYS . ' ימים לפני ההזמנה (קשר בזמן, לא בהכרח סיבתי). '
			. '<b>הבוט ענה</b> – סיווג אוטומטי (AI) של כל שיחה לפי נושא והאם התקבלה תשובה עניינית. '
			. ( $d['tracking_since'] ? 'מדידת העברות, קליקים, הזמנות ומכשירים החלה ב-' . esc_html( $d['tracking_since'] ) . '. ' : '' )
			. 'לא נשמר תוכן השיחות בדו"ח.'
			. '</div></div>';

		return $h;
	}

	private static function kv( string $k, string $v ): string {
		return '<tr><td style="padding:5px 0;border-bottom:1px solid #f3f4f6">' . esc_html( $k ) . '</td><td style="padding:5px 0;border-bottom:1px solid #f3f4f6;text-align:left;font-weight:700">' . esc_html( $v ) . '</td></tr>';
	}

	private static function since_chat( ?float $hours ): string {
		if ( $hours === null ) {
			return '';
		}
		if ( $hours < 1 ) {
			return 'פחות משעה';
		}
		if ( $hours < 48 ) {
			return round( $hours ) . ' שעות';
		}
		return round( $hours / 24 ) . ' ימים';
	}

	private static function page_labels( array $pages ): array {
		$out = [];
		foreach ( $pages as $path => $n ) {
			$label         = $path === '/' ? 'דף הבית' : ( $path === '' ? 'לא ידוע' : urldecode( (string) $path ) );
			$out[ $label ] = ( $out[ $label ] ?? 0 ) + $n;
		}
		return $out;
	}

	private static function hours( array $hours ): string {
		$max = max( $hours ) ?: 1;
		$h   = '<table role="presentation" style="width:100%;border-collapse:collapse;table-layout:fixed;direction:ltr"><tr style="height:80px">';
		foreach ( $hours as $n ) {
			$px = (int) round( 70 * $n / $max );
			$h .= '<td style="vertical-align:bottom;padding:0 1px"><div title="' . (int) $n . '" style="background:#2CD09D;height:' . max( $n ? 2 : 0, $px ) . 'px;border-radius:2px 2px 0 0"></div></td>';
		}
		$h .= '</tr><tr>';
		for ( $i = 0; $i < 24; $i++ ) {
			$h .= '<td style="font-size:10px;color:#6b7280;text-align:center">' . ( $i % 3 === 0 ? $i : '' ) . '</td>';
		}
		return $h . '</tr></table>';
	}
}
