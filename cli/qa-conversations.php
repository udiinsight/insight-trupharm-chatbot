<?php
/**
 * Multi-turn conversation QA runner (SUGAR360 / Tropharm).
 *
 * Executes the scripted conversations from docs/qa-scripts.json through the live
 * chatbot using the SAME contract as the widget (client-side `messages` history +
 * client-generated `chatId` per conversation), checks per-turn expectations
 * mechanically, and tone-judges flagged turns with an LLM (server-side, so raw
 * Hebrew transcripts never leave the box).
 *
 * Usage on the (staging) server, from public_html:
 *   ITC_QA_SCRIPTS=/tmp/itc_qa/docs/qa-scripts.json ITC_QA_FROM=1 ITC_QA_TO=5 \
 *     wp eval-file /tmp/itc_qa/cli/qa-conversations.php --user=1
 *
 * Stdout: ASCII-only scorecard lines (ids, enums, lengths, judge scores).
 * Full transcripts: appended to /tmp/itc_qa_results.jsonl (JSON lines).
 *
 * @package InsightChat\Cli
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

error_reporting( E_ERROR );
@ini_set( 'display_errors', '0' );

global $mwai;
wp_set_current_user( 1 );

$scripts_path = getenv( 'ITC_QA_SCRIPTS' ) ?: '/tmp/itc_qa/docs/qa-scripts.json';
$from         = (int) ( getenv( 'ITC_QA_FROM' ) ?: 1 );
$to           = (int) ( getenv( 'ITC_QA_TO' ) ?: 999 );
$out_path     = '/tmp/itc_qa_results.jsonl';

$payload = json_decode( (string) file_get_contents( $scripts_path ), true );
if ( ! is_array( $payload['scripts'] ?? null ) ) {
	echo "ERROR: cannot read scripts at {$scripts_path}\n";
	return;
}
$fh = fopen( $out_path, 'a' );

function itc_qa_submit( string $msg, string $chat_id, array $messages ): string {
	$req = new WP_REST_Request( 'POST', '/mwai-ui/v1/chats/submit' );
	$req->set_header( 'Content-Type', 'application/json' );
	$req->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
	$req->set_body( wp_json_encode( [
		'botId'      => 'default',
		'newMessage' => $msg,
		'chatId'     => $chat_id,
		'messages'   => $messages,
		'stream'     => false,
	] ) );
	$res  = rest_do_request( $req );
	$data = is_object( $res ) ? $res->get_data() : $res;
	return is_array( $data ) ? (string) ( $data['reply'] ?? ( $data['data'] ?? '' ) ) : '';
}

function itc_qa_parse( string $raw ): ?array {
	$trimmed = trim( $raw );
	if ( $trimmed !== '' && $trimmed[0] === '{' ) {
		$attempt = json_decode( $trimmed, true );
		if ( is_array( $attempt ) ) {
			return $attempt;
		}
	}
	if ( preg_match( '/```(?:json)?\s*\n?(.*?)\n?```/s', $trimmed, $m ) ) {
		$attempt = json_decode( trim( $m[1] ), true );
		if ( is_array( $attempt ) ) {
			return $attempt;
		}
	}
	$first = strpos( $trimmed, '{' );
	$last  = strrpos( $trimmed, '}' );
	if ( $first !== false && $last !== false && $last > $first ) {
		$attempt = json_decode( substr( $trimmed, $first, $last - $first + 1 ), true );
		if ( is_array( $attempt ) ) {
			return $attempt;
		}
	}
	return null;
}

function itc_qa_judge( $mwai, string $text ): ?array {
	if ( ! $mwai || ! method_exists( $mwai, 'simpleTextQuery' ) ) {
		return null;
	}
	$prompt = "You are a strict QA judge for a Hebrew customer-service chatbot of a continuous glucose monitor. The audience is sensitive (people living with diabetes). Evaluate ONLY the reply below.\n"
		. "Return ONLY compact JSON: {\"pushy\":1-5,\"tone_ok\":true|false,\"notes_en\":\"max 8 English words\"}.\n"
		. "pushy: 1 = no sales pressure at all, 5 = aggressive selling. tone_ok: warm, professional, honest, not dramatic, not dismissive of concerns.\n\nREPLY:\n" . $text;
	try {
		$r = (string) $mwai->simpleTextQuery( $prompt );
		$r = trim( preg_replace( '/^```(?:json)?|```$/m', '', trim( $r ) ) );
		$j = json_decode( $r, true );
		return is_array( $j ) ? $j : null;
	} catch ( \Throwable $e ) {
		return null;
	}
}

$idx = 0;
foreach ( $payload['scripts'] as $script ) {
	$idx++;
	if ( $idx < $from || $idx > $to ) {
		continue;
	}
	$sid      = (string) ( $script['id'] ?? ( 'S' . $idx ) );
	$chat_id  = 'qa-' . substr( md5( $sid . microtime() ), 0, 10 );
	$messages = [];
	$t        = 0;

	foreach ( $script['turns'] as $turn ) {
		$t++;
		$q     = (string) ( $turn['q'] ?? '' );
		$ex    = is_array( $turn['expect'] ?? null ) ? $turn['expect'] : [];
		$start = microtime( true );
		$raw   = itc_qa_submit( $q, $chat_id, $messages );
		$ms    = (int) round( ( microtime( true ) - $start ) * 1000 );

		$messages[] = [ 'role' => 'user', 'content' => $q ];
		$messages[] = [ 'role' => 'assistant', 'content' => $raw ];

		$j     = itc_qa_parse( $raw );
		$resp  = is_array( $j ) ? (string) ( $j['response'] ?? '' ) : '';
		$fails = [];
		$warns = [];
		$types = [];
		$nsrc  = 0;
		$sum_len = -1;

		if ( ! is_array( $j ) ) {
			$fails[] = 'parse';
		} else {
			$dk  = $j['disclaimer_kind'] ?? null;
			$dks = $dk === null ? 'null' : (string) $dk;
			if ( ! empty( $ex['disclaimer'] ) && ! in_array( $dks, $ex['disclaimer'], true ) ) {
				$fails[] = 'disc=' . $dks;
			}
			$acts  = is_array( $j['suggested_actions'] ?? null ) ? $j['suggested_actions'] : [];
			$types = array_map( static fn( $a ) => is_array( $a ) ? ( $a['type'] ?? '?' ) : '?', $acts );
			foreach ( (array) ( $ex['must_actions'] ?? [] ) as $need ) {
				if ( ! in_array( $need, $types, true ) ) {
					$fails[] = 'no-' . $need;
				}
			}
			if ( ! empty( $ex['must_contact'] ) ) {
				$has = in_array( 'whatsapp', $types, true );
				foreach ( $acts as $a ) {
					if ( ( $a['type'] ?? '' ) === 'navigate' && preg_match( '~tel:|customer-service~', (string) ( $a['url'] ?? '' ) ) ) {
						$has = true;
					}
				}
				if ( ! $has ) {
					$fails[] = 'no-contact';
				}
			}
			if ( ! empty( $ex['must_navigate_contains'] ) ) {
				$ok = false;
				foreach ( $acts as $a ) {
					if ( ( $a['type'] ?? '' ) === 'navigate' && strpos( (string) ( $a['url'] ?? '' ), (string) $ex['must_navigate_contains'] ) !== false ) {
						$ok = true;
					}
				}
				if ( ! $ok ) {
					$fails[] = 'no-nav';
				}
			}
			if ( ! empty( $ex['empty_actions'] ) && count( $acts ) > 0 ) {
				$fails[] = 'acts=' . count( $acts );
			}
			if ( ! empty( $ex['forbid_text'] ) && $resp !== '' && preg_match( '~' . $ex['forbid_text'] . '~u', $resp ) ) {
				$fails[] = 'forbidden-text';
			}
			if ( ! empty( $ex['require_text'] ) && ! preg_match( '~' . $ex['require_text'] . '~u', $resp ) ) {
				$fails[] = 'missing-text';
			}
			if ( ! empty( $ex['english'] ) ) {
				$latin = preg_match_all( '~[A-Za-z]~', $resp );
				$heb   = preg_match_all( '~[א-ת]~u', $resp );
				if ( $latin < $heb ) {
					$fails[] = 'not-english';
				}
			}
			foreach ( $acts as $a ) {
				if ( ( $a['type'] ?? '' ) === 'whatsapp' ) {
					$sum_len = mb_strlen( (string) ( $a['summary'] ?? '' ), 'UTF-8' );
				}
			}
			if ( ! empty( $ex['summary_required'] ) && $sum_len <= 0 ) {
				$fails[] = 'summary-empty';
			}
			if ( ! empty( $ex['summary_topic'] ) && $sum_len > 0 ) {
				$sum = '';
				foreach ( $acts as $a ) {
					if ( ( $a['type'] ?? '' ) === 'whatsapp' ) {
						$sum = (string) ( $a['summary'] ?? '' );
					}
				}
				if ( ! preg_match( '~' . $ex['summary_topic'] . '~u', $sum ) ) {
					$warns[] = 'summary-topic';
				}
			}
			$nsrc = count( $j['sources'] ?? [] );
			if ( isset( $ex['min_sources'] ) && $nsrc < (int) $ex['min_sources'] ) {
				$warns[] = 'src=' . $nsrc;
			}
		}

		$judge = null;
		if ( ! empty( $ex['judge'] ) && $resp !== '' ) {
			$judge = itc_qa_judge( $mwai, $resp );
		}

		fwrite( $fh, wp_json_encode( [
			'script'  => $sid,
			'turn'    => $t,
			'q'       => $q,
			'time_ms' => $ms,
			'raw'     => $raw,
			'fails'   => $fails,
			'warns'   => $warns,
			'judge'   => $judge,
		], JSON_UNESCAPED_UNICODE ) . "\n" );

		$status = $fails ? ( 'FAIL[' . implode( ',', $fails ) . ']' ) : 'ok';
		$wstr   = $warns ? ( ' warn[' . implode( ',', $warns ) . ']' ) : '';
		$jstr   = '';
		if ( ! empty( $ex['judge'] ) ) {
			$jstr = $judge
				? ( ' pushy=' . ( $judge['pushy'] ?? '?' ) . ' tone=' . ( ( $judge['tone_ok'] ?? null ) === true ? 'ok' : 'BAD' ) . ' note=' . preg_replace( '~[^\x20-\x7E]~', '?', (string) ( $judge['notes_en'] ?? '' ) ) )
				: ' judge=n/a';
		}
		echo $sid . '.t' . $t . ' ' . $status . $wstr
			. ' acts=' . ( $types ? implode( ',', $types ) : '-' )
			. ' src=' . $nsrc
			. ( $sum_len >= 0 ? ( ' sum_len=' . $sum_len ) : '' )
			. ' len=' . mb_strlen( $resp, 'UTF-8' )
			. ' ms=' . $ms
			. $jstr . "\n";
	}
}
fclose( $fh );
echo "QA BATCH DONE ({$from}-{$to})\n";
