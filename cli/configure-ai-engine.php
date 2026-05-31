<?php
/**
 * One-shot AI Engine configuration script (SUGAR360 / Tropharm).
 *
 * Reads /tmp/insight-keys.json (created out-of-band) and writes:
 *   - Anthropic API key into the existing Claude env
 *   - Pinecone API key + server (host) into the existing Pinecone env
 *   - Removes the orphan empty Pinecone env
 *   - Disables post sync (Sugar360 knowledge is document-based, added via the Knowledge UI)
 *
 * Usage on the (staging) server:
 *   wp eval-file cli/configure-ai-engine.php --allow-root
 *
 * @package InsightChat\Cli
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$keys_path = '/tmp/insight-keys.json';
if ( ! is_readable( $keys_path ) ) {
	echo "ERROR: keys file not readable at $keys_path\n";
	return;
}
$creds = json_decode( (string) file_get_contents( $keys_path ), true );
if ( ! is_array( $creds ) || empty( $creds['pinecone'] ) || empty( $creds['anthropic'] ) || empty( $creds['pinecone_host'] ) ) {
	echo "ERROR: keys file missing required fields\n";
	return;
}

$opts = get_option( 'mwai_options', [] );
if ( ! is_array( $opts ) ) {
	$opts = [];
}

// --- AI envs: set Anthropic API key on existing Claude env ---
foreach ( $opts['ai_envs'] ?? [] as $i => $env ) {
	if ( ( $env['type'] ?? '' ) === 'anthropic' ) {
		$opts['ai_envs'][ $i ]['apikey'] = $creds['anthropic'];
		echo "✓ Set Anthropic API key on env id={$env['id']}\n";
	}
}

// --- Embeddings envs: configure Pinecone, drop the empty duplicate ---
$primary_id     = null;
$kept_envs      = [];
$existing_envs  = $opts['embeddings_envs'] ?? [];
foreach ( $existing_envs as $env ) {
	if ( ( $env['type'] ?? '' ) !== 'pinecone' ) {
		$kept_envs[] = $env;
		continue;
	}
	if ( ! empty( $env['name'] ) && empty( $primary_id ) ) {
		$primary_id              = $env['id'];
		$env['apikey']           = $creds['pinecone'];
		$env['server']           = $creds['pinecone_host'];
		$env['indexes']          = [ 'insight-trupharm-he' ];
		$env['index']            = 'insight-trupharm-he';
		$env['namespaces']       = [ 'default' ];
		$env['namespace']        = 'default';
		$env['pinecone_dimensions'] = 1024;
		$env['ai_embeddings_env']    = $env['ai_embeddings_env'] ?? '';
		$env['ai_embeddings_model']  = $env['ai_embeddings_model'] ?? 'text-embedding-3-large';
		$env['ai_embeddings_dimensions'] = $env['ai_embeddings_dimensions'] ?? 1024;
		$env['min_score']        = $env['min_score'] ?? 35;
		$env['max_select']       = $env['max_select'] ?? 30;
		$kept_envs[]             = $env;
		echo "✓ Configured Pinecone env id={$env['id']} → host=" . parse_url( $creds['pinecone_host'], PHP_URL_HOST ) . "\n";
	} else {
		echo "✗ Dropping empty Pinecone env id={$env['id']}\n";
	}
}
$opts['embeddings_envs'] = $kept_envs;

// --- Sync configuration ---
// Sugar360 is document-based: knowledge is added via AI Engine's Knowledge UI (manual / PDF),
// NOT by syncing WordPress posts. Disable post sync so nothing from the site gets embedded.
$opts['module_embeddings']   = '1';
$opts['module_chatbots']     = '1';
$opts['syncPosts']           = '0';
$opts['sync_post_envId']     = $primary_id;
$opts['syncPostTypes']       = [];
$opts['syncPostStatus']      = [ 'publish' ];
$opts['sync_post_categories'] = $opts['sync_post_categories'] ?? [];

update_option( 'mwai_options', $opts );

echo "\nFinal config:\n";
echo "  syncPosts:           {$opts['syncPosts']} (document-based; post sync disabled)\n";
echo "  embeddings index:    insight-trupharm-he\n";
echo "  ai_envs:             " . count( $opts['ai_envs'] ?? [] ) . " entries\n";
echo "  embeddings_envs:     " . count( $opts['embeddings_envs'] ?? [] ) . " entries\n";

echo "\nDone.\n";
