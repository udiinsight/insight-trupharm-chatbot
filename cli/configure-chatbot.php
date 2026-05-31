<?php
/**
 * One-shot chatbot configuration script (SUGAR360 / Tropharm).
 *
 * Updates (or creates) mwai_chatbots[default] with:
 *   - Anthropic Claude Sonnet + the Anthropic env
 *   - contentAware + Pinecone embeddings env (document-based knowledge)
 *   - The Hebrew system prompt loaded from system-prompts/he.md
 *
 * Usage on the (staging) server:
 *   INSIGHT_PROMPT_FILE=/path/to/system-prompts/he.md wp eval-file cli/configure-chatbot.php --allow-root
 *
 * Reads the Hebrew prompt from the file path passed via the INSIGHT_PROMPT_FILE env var,
 * defaulting to system-prompts/he.md next to this repo's cli/ folder.
 *
 * @package InsightChat\Cli
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$prompt_file = getenv( 'INSIGHT_PROMPT_FILE' );
if ( ! $prompt_file ) {
	$prompt_file = __DIR__ . '/../system-prompts/he.md';
}
if ( ! is_readable( $prompt_file ) ) {
	echo "ERROR: prompt file not readable at {$prompt_file}\n";
	return;
}

$raw    = (string) file_get_contents( $prompt_file );
$prompt = extract_prompt_from_markdown( $raw );
if ( $prompt === '' ) {
	echo "ERROR: no fenced prompt block found in {$prompt_file}\n";
	return;
}

$opts          = get_option( 'mwai_options', [] );
$claude_env_id = '';
foreach ( $opts['ai_envs'] ?? [] as $env ) {
	if ( ( $env['type'] ?? '' ) === 'anthropic' ) {
		$claude_env_id = $env['id'];
		break;
	}
}
if ( ! $claude_env_id ) {
	echo "ERROR: Anthropic env not found.\n";
	return;
}

$pinecone_env_id = '';
foreach ( $opts['embeddings_envs'] ?? [] as $env ) {
	if ( ( $env['type'] ?? '' ) === 'pinecone' && ! empty( $env['apikey'] ) ) {
		$pinecone_env_id = $env['id'];
		break;
	}
}
if ( ! $pinecone_env_id ) {
	echo "ERROR: configured Pinecone env not found.\n";
	return;
}

$chatbots = get_option( 'mwai_chatbots', [] );
if ( ! is_array( $chatbots ) ) {
	$chatbots = [];
}

$fields = [
	'botId'                => 'default',
	'aiName'               => 'נועה:',
	'userName'             => 'מבקר:',
	'guestName'            => 'מבקר:',
	'name'                 => 'Noa (SUGAR360)',
	'envId'                => $claude_env_id,
	'model'                => 'claude-sonnet-4-6',
	'temperature'          => 0.3,
	'maxTokens'            => 1000,
	'maxMessages'          => 15,
	'maxResults'           => 6,
	'mode'                 => 'chat',
	'scope'                => 'chatbot',
	'contentAware'         => true,
	'embeddingsEnvId'      => $pinecone_env_id,
	'instructions'         => $prompt,
	'startSentence'        => 'שלום, אני נועה, העוזרת הדיגיטלית של SUGAR360. איך אפשר לעזור?',
	'textInputPlaceholder' => 'כתבו לי שאלה על SUGAR360...',
	'textSend'             => 'שליחה',
	'textClear'            => 'איפוס',
	'historyStrategy'      => null,
];

$updated = false;
foreach ( $chatbots as $i => $bot ) {
	if ( ( $bot['botId'] ?? '' ) !== 'default' ) {
		continue;
	}
	$chatbots[ $i ] = array_merge( $bot, $fields );
	$updated = true;
	echo "✓ Updated chatbot id=default → model=claude-sonnet-4-6, envId={$claude_env_id}, embeddingsEnvId={$pinecone_env_id}\n";
}

if ( ! $updated ) {
	$chatbots[] = $fields;
	echo "✓ Created chatbot id=default → model=claude-sonnet-4-6, envId={$claude_env_id}, embeddingsEnvId={$pinecone_env_id}\n";
}

if ( ! update_option( 'mwai_chatbots', $chatbots ) ) {
	// update_option returns false when value unchanged. Re-fetch and verify.
	$check = get_option( 'mwai_chatbots' );
	if ( ! is_array( $check ) ) {
		echo "ERROR: failed to save chatbots option.\n";
		return;
	}
}

echo "\nDone. Prompt length: " . mb_strlen( $prompt, 'UTF-8' ) . " chars.\n";

/**
 * Pull the first fenced code block out of a markdown file. The Hebrew prompt is wrapped
 * in a triple-backtick block so the surrounding markdown is documentation, not part of
 * the prompt sent to the LLM.
 */
function extract_prompt_from_markdown( string $raw ): string {
	if ( preg_match( '/```\s*\n(.*?)\n```/s', $raw, $m ) ) {
		return trim( $m[1] );
	}
	return '';
}
