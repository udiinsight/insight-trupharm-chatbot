<?php
/**
 * Retrieval audit — embed Hebrew queries via OpenAI, query Pinecone, print top hits.
 *
 * Usage:
 *   wp eval-file wp-content/mu-plugins/insight-chat/cli/retrieval-audit.php --allow-root
 *
 * @package InsightChat\Cli
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$opts = get_option( 'mwai_options', [] );

$openai_key = '';
foreach ( $opts['ai_envs'] ?? [] as $env ) {
	if ( ( $env['type'] ?? '' ) === 'openai' ) {
		$openai_key = $env['apikey'] ?? '';
		break;
	}
}
if ( ! $openai_key ) {
	echo "ERROR: OpenAI key not found in mwai_options.\n";
	return;
}

$pc_env = null;
foreach ( $opts['embeddings_envs'] ?? [] as $env ) {
	if ( ( $env['type'] ?? '' ) === 'pinecone' && ! empty( $env['apikey'] ) ) {
		$pc_env = $env;
		break;
	}
}
if ( ! $pc_env ) {
	echo "ERROR: Pinecone env not found.\n";
	return;
}

$queries = [
	'מהו ניתוח קטרקט בלייזר?',
	'איך מתבצע ניתוח להסרת משקפיים?',
	'באיזה מרכזים רפואיים יש ניתוחי הסרת משקפיים בלייזר?',
	'מה זמן ההחלמה אחרי טיפול בטחורים?',
	'אורתופד שמתמחה בילדים',
];

foreach ( $queries as $q ) {
	echo "\n══════════════════════════════════════════════════════════\n";
	echo "QUERY: $q\n";
	echo "══════════════════════════════════════════════════════════\n";

	// 1. Embed the query via OpenAI.
	$embedding = embed_via_openai( $q, $openai_key, 1024 );
	if ( ! $embedding ) {
		echo "ERROR: failed to embed query.\n";
		continue;
	}

	// 2. Query Pinecone.
	$hits = query_pinecone( $pc_env['server'], $pc_env['apikey'], $embedding, 'default', 3 );
	if ( empty( $hits ) ) {
		echo "(no results)\n";
		continue;
	}
	foreach ( $hits as $i => $hit ) {
		$score = round( (float) ( $hit['score'] ?? 0 ), 3 );
		$id    = $hit['id'] ?? '?';
		$meta  = $hit['metadata'] ?? [];
		$title = $meta['title'] ?? $meta['post_title'] ?? '(no title)';
		$ref   = $meta['refId'] ?? $meta['post_id'] ?? '?';
		echo sprintf( "  #%d  score=%s  refId=%s  %s\n", $i + 1, $score, $ref, $title );
	}
}

function embed_via_openai( string $text, string $key, int $dim ): ?array {
	$body = wp_json_encode( [
		'model'      => 'text-embedding-3-large',
		'input'      => $text,
		'dimensions' => $dim,
	] );
	$res = wp_remote_post( 'https://api.openai.com/v1/embeddings', [
		'timeout' => 30,
		'headers' => [
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $key,
		],
		'body'    => $body,
	] );
	if ( is_wp_error( $res ) ) {
		echo 'OpenAI error: ' . $res->get_error_message() . "\n";
		return null;
	}
	$data = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( empty( $data['data'][0]['embedding'] ) ) {
		echo 'OpenAI body: ' . substr( wp_remote_retrieve_body( $res ), 0, 200 ) . "\n";
		return null;
	}
	return $data['data'][0]['embedding'];
}

function query_pinecone( string $host, string $key, array $vector, string $namespace, int $top_k ): array {
	$url  = rtrim( $host, '/' ) . '/query';
	$body = wp_json_encode( [
		'vector'          => $vector,
		'topK'            => $top_k,
		'namespace'       => $namespace,
		'includeMetadata' => true,
	] );
	$res = wp_remote_post( $url, [
		'timeout' => 30,
		'headers' => [
			'Content-Type'           => 'application/json',
			'Api-Key'                => $key,
			'X-Pinecone-API-Version' => '2025-01',
		],
		'body'    => $body,
	] );
	if ( is_wp_error( $res ) ) {
		echo 'Pinecone error: ' . $res->get_error_message() . "\n";
		return [];
	}
	$data = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( ! is_array( $data['matches'] ?? null ) ) {
		echo 'Pinecone body: ' . substr( wp_remote_retrieve_body( $res ), 0, 300 ) . "\n";
		return [];
	}
	return $data['matches'];
}
