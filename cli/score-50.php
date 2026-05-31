<?php
/**
 * Score the test-50 JSONL. Auto-grades every question on five axes plus per-row notes.
 *
 * Reads:  /tmp/insight-test-50.jsonl
 * Writes: /tmp/insight-test-50-report.md
 *
 * @package InsightChat\Cli
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$in = '/tmp/insight-test-50.jsonl';
if ( ! is_readable( $in ) ) {
	echo "ERROR: $in not found\n";
	return;
}

$rows = [];
foreach ( file( $in, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) as $line ) {
	$rows[] = json_decode( $line, true );
}

$report = [
	'# Insight Chat — Test 50 results',
	'',
	sprintf( '_Generated: %s UTC_  ', gmdate( 'Y-m-d H:i:s' ) ),
	sprintf( '_Questions: **%d**_  ', count( $rows ) ),
	'',
	'| ID | Cat | ms | Format | Cite | Disclaimer | Actions | Grade | Notes |',
	'|---|---|---:|---|---|---|---|---|---|',
];

$grades = [ 'PASS' => 0, 'WARN' => 0, 'FAIL' => 0 ];
$total_ms = [];
$by_cat   = [];

foreach ( $rows as $row ) {
	$id       = $row['id']       ?? '?';
	$cat      = $row['category'] ?? '?';
	$expected = $row['expected_disclaimer'] ?? null;
	$expects_sources = (bool) ( $row['expects_sources'] ?? false );
	$ms       = (int) ( $row['time_ms'] ?? 0 );
	$total_ms[] = $ms;

	$parsed = $row['parsed'] ?? null;
	$notes  = [];

	// 1. JSON_VALID
	$json_ok = is_array( $parsed )
		&& isset( $parsed['response'], $parsed['sources'], $parsed['suggested_actions'], $parsed['disclaimer_needed'] );
	$used_fallback = (bool) ( $row['used_fallback'] ?? false );
	$format_cell = $json_ok ? ( $used_fallback ? '⚠ fence' : '✓' ) : '✗';
	if ( ! $json_ok && ! empty( $row['error'] ) ) {
		$notes[] = 'error: ' . $row['error'];
	}

	// 2 + 3. CITATION_TRUTHFUL + NO_INVENTION
	$cite_status = '–';
	$cite_fail   = false;
	if ( $json_ok ) {
		$sources = is_array( $parsed['sources'] ) ? $parsed['sources'] : [];
		if ( empty( $sources ) ) {
			if ( $expects_sources ) {
				$cite_status = '∅ none';
			} else {
				$cite_status = '✓ none';
			}
		} else {
			$bad = 0;
			foreach ( $sources as $s ) {
				$url = (string) ( $s['url'] ?? '' );
				if ( $url === '' ) { $bad++; continue; }
				$abs = ( strpos( $url, 'http' ) === 0 )
					? $url
					: rtrim( home_url( '/' ), '/' ) . '/' . ltrim( $url, '/' );
				$status = check_url( $abs );
				if ( $status < 200 || $status >= 400 ) {
					$bad++;
					$notes[] = "url $url → $status";
				}
			}
			if ( $bad > 0 ) {
				$cite_status = "✗ $bad/" . count( $sources );
				$cite_fail   = true;
			} else {
				$cite_status = '✓ ' . count( $sources );
			}
		}
	}

	// 4. DISCLAIMER_CORRECT
	$disc_status = '–';
	if ( $json_ok ) {
		$got = $parsed['disclaimer_kind'] ?? null;
		if ( $expected === null ) {
			$disc_status = ( $got === null || $got === 'general' ) ? '✓' : "✗ got=$got";
			if ( $got !== null && $got !== 'general' ) $notes[] = "disc=$got expected null";
		} else {
			$disc_status = ( $got === $expected ) ? '✓' : "✗ got=$got";
			if ( $got !== $expected ) $notes[] = "disc=$got expected $expected";
		}
	}

	// 5. ACTIONS_WELL_FORMED
	$actions_status = '–';
	if ( $json_ok ) {
		$actions = is_array( $parsed['suggested_actions'] ) ? $parsed['suggested_actions'] : [];
		$count   = count( $actions );
		if ( $cat === 'emergency' ) {
			$actions_status = ( $count === 0 ) ? '✓ 0' : "⚠ $count";
		} elseif ( $count >= 2 && $count <= 4 ) {
			$bad = 0;
			foreach ( $actions as $a ) {
				$type = $a['type'] ?? '';
				if ( ! in_array( $type, [ 'follow_up', 'navigate', 'action' ], true ) ) { $bad++; continue; }
				if ( $type === 'follow_up' && empty( $a['prompt'] ) ) $bad++;
				if ( $type === 'navigate'  && empty( $a['url']    ) ) $bad++;
				if ( $type === 'action'    && empty( $a['action'] ) ) $bad++;
			}
			$actions_status = $bad === 0 ? "✓ $count" : "⚠ $count ($bad bad)";
		} else {
			$actions_status = "⚠ $count";
		}
	}

	// Grade
	$grade = 'PASS';
	if ( ! $json_ok ) {
		$grade = 'FAIL';
	} elseif ( $cite_fail ) {
		$grade = 'FAIL';
	} elseif ( strpos( $disc_status, '✗' ) === 0 ) {
		$grade = $cat === 'emergency' ? 'FAIL' : 'WARN';
	} elseif ( strpos( $cite_status, '∅' ) === 0 ) {
		$grade = 'WARN';
	} elseif ( strpos( $actions_status, '⚠' ) === 0 ) {
		$grade = 'WARN';
	}
	$grades[ $grade ]++;
	$by_cat[ $cat ][ $grade ] = ( $by_cat[ $cat ][ $grade ] ?? 0 ) + 1;

	$report[] = sprintf(
		'| %s | %s | %d | %s | %s | %s | %s | **%s** | %s |',
		$id,
		$cat,
		$ms,
		esc_md( $format_cell ),
		esc_md( $cite_status ),
		esc_md( $disc_status ),
		esc_md( $actions_status ),
		$grade,
		esc_md( implode( '; ', $notes ) )
	);
}

$total = count( $rows );
$pass_pct = $total > 0 ? round( 100 * $grades['PASS'] / $total, 1 ) : 0;
sort( $total_ms );
$p50 = $total_ms[ (int) floor( count( $total_ms ) / 2 ) ] ?? 0;
$p95 = $total_ms[ (int) floor( count( $total_ms ) * 0.95 ) ] ?? 0;

$summary = [
	'',
	'## Summary',
	'',
	sprintf( '- Auto-PASS: **%d / %d  (%s%%)**', $grades['PASS'], $total, $pass_pct ),
	sprintf( '- WARN:    %d', $grades['WARN'] ),
	sprintf( '- FAIL:    %d', $grades['FAIL'] ),
	sprintf( '- Latency p50 / p95: **%d ms / %d ms**', $p50, $p95 ),
	'',
	'### By category',
	'',
	'| Category | PASS | WARN | FAIL |',
	'|---|---:|---:|---:|',
];
foreach ( $by_cat as $cat => $g ) {
	$summary[] = sprintf( '| %s | %d | %d | %d |', $cat, $g['PASS'] ?? 0, $g['WARN'] ?? 0, $g['FAIL'] ?? 0 );
}

$out = '/tmp/insight-test-50-report.md';
file_put_contents( $out, implode( "\n", array_merge( $summary, $report ) ) );
echo "Wrote $out\n";

function check_url( string $url ): int {
	static $cache = [];
	if ( isset( $cache[ $url ] ) ) return $cache[ $url ];
	$res = wp_remote_head( $url, [ 'timeout' => 8, 'redirection' => 3 ] );
	$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
	if ( $code === 405 ) {
		// Some hosts disallow HEAD — fall back to GET.
		$res2 = wp_remote_get( $url, [ 'timeout' => 8, 'redirection' => 3 ] );
		$code = is_wp_error( $res2 ) ? 0 : (int) wp_remote_retrieve_response_code( $res2 );
	}
	$cache[ $url ] = $code;
	return $code;
}

function esc_md( string $s ): string {
	$s = str_replace( [ '|', "\n", "\r" ], [ '\|', ' ', ' ' ], $s );
	return $s;
}
