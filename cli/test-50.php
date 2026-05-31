<?php
/**
 * Run 50 representative Hebrew questions through the live chatbot.
 *
 * Inputs:  /tmp/insight-test-questions.json  (one upload from local docs/test-questions.json)
 * Outputs: /tmp/insight-test-50.jsonl       (one record per question, JSON lines)
 *
 * Each record contains: id, category, expected_*, question, raw, parsed, time_ms.
 * The scorer (score-50.php) reads the JSONL and produces the markdown report.
 *
 * @package InsightChat\Cli
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

global $mwai;
if ( ! $mwai ) {
	echo "ERROR: \$mwai API not loaded.\n";
	return;
}

// Bypass per-user limits during the test run.
$admin_users = get_users( [ 'role' => 'administrator', 'number' => 1 ] );
if ( ! empty( $admin_users ) ) {
	wp_set_current_user( $admin_users[0]->ID );
}

$qpath = '/tmp/insight-test-questions.json';
if ( ! is_readable( $qpath ) ) {
	echo "ERROR: questions file not found at $qpath\n";
	return;
}
$payload = json_decode( (string) file_get_contents( $qpath ), true );
if ( ! is_array( $payload['questions'] ?? null ) ) {
	echo "ERROR: malformed questions file\n";
	return;
}

$out_path = '/tmp/insight-test-50.jsonl';
$fh = fopen( $out_path, 'w' );
if ( ! $fh ) {
	echo "ERROR: cannot write $out_path\n";
	return;
}

$total   = count( $payload['questions'] );
$started = microtime( true );

foreach ( $payload['questions'] as $i => $q ) {
	$id  = (string) ( $q['id'] ?? '?' );
	$txt = (string) ( $q['q'] ?? '' );
	if ( $txt === '' ) continue;

	$start = microtime( true );
	$reply = '';
	$err   = null;
	try {
		$reply = (string) $mwai->simpleChatbotQuery( 'default', $txt );
	} catch ( \Throwable $e ) {
		$err = $e->getMessage();
	}
	$ms = (int) round( ( microtime( true ) - $start ) * 1000 );

	$parsed = null;
	$used_fallback = false;
	if ( $reply !== '' ) {
		$trimmed = trim( $reply );
		// Try strict parse first (must start with { and end with }).
		if ( $trimmed !== '' && $trimmed[0] === '{' ) {
			$attempt = json_decode( $trimmed, true );
			if ( is_array( $attempt ) ) {
				$parsed = $attempt;
			}
		}
		if ( $parsed === null ) {
			// Fallback: strip ```json fences and try again.
			$used_fallback = true;
			if ( preg_match( '/```(?:json)?\s*\n?(.*?)\n?```/s', $trimmed, $m ) ) {
				$attempt = json_decode( trim( $m[1] ), true );
				if ( is_array( $attempt ) ) $parsed = $attempt;
			}
		}
		if ( $parsed === null ) {
			// Last-resort: substring between first { and last }.
			$first = strpos( $trimmed, '{' );
			$last  = strrpos( $trimmed, '}' );
			if ( $first !== false && $last !== false && $last > $first ) {
				$attempt = json_decode( substr( $trimmed, $first, $last - $first + 1 ), true );
				if ( is_array( $attempt ) ) $parsed = $attempt;
			}
		}
	}

	$record = [
		'id'                  => $id,
		'category'            => $q['category'] ?? '',
		'expected_disclaimer' => $q['expected_disclaimer'] ?? null,
		'expects_sources'     => (bool) ( $q['expects_sources'] ?? false ),
		'question'            => $txt,
		'time_ms'             => $ms,
		'used_fallback'       => $used_fallback,
		'error'               => $err,
		'raw'                 => $reply,
		'parsed'              => $parsed,
	];

	fwrite( $fh, wp_json_encode( $record, JSON_UNESCAPED_UNICODE ) . "\n" );
	fflush( $fh );
	echo sprintf( "  %s  %4d ms  %s\n", $id, $ms, $err ? '[ERR] ' . $err : ( $parsed ? 'OK' : 'BAD-JSON' ) );
}

fclose( $fh );
$total_s = round( microtime( true ) - $started, 1 );
echo "\nWrote $out_path  ($total questions in {$total_s}s).\n";
