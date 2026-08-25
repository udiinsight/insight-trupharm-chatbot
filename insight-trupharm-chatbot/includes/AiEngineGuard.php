<?php
/**
 * Protect the AI Engine option values this install cannot run without.
 *
 * AI Engine's settings save rebuilds `mwai_options` from whatever the submitting
 * admin panel posted, and silently drops every key that panel did not re-include.
 * A panel that does not render the API-key field posts it back empty, which is how
 * the 2026-08-23 outage blanked `apikey` on both `ai_envs` entries while the
 * chatbot kept pointing at them — every message then failed with
 * "No API Key provided. Please visit the Settings. (ChatML Engine)".
 *
 * Two responsibilities:
 *   1. A `pre_update_option_mwai_options` filter that restores a blanked key from
 *      the value already in the database, and re-appends a required environment a
 *      save dropped entirely.
 *   2. A diagnosis used to surface the broken state loudly (admin notice + cron
 *      log) instead of letting visitors hit a generic widget error for days.
 *
 * No key material is ever written from source. Keys only ever move from the old
 * option value to the new one, inside the filter.
 *
 * @package InsightChat
 */

namespace InsightChat;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AiEngineGuard {

	public const CRON_HOOK    = 'insight_chat_aiengine_healthcheck';
	public const STATE_OPTION = 'insight_chat_aiengine_health';

	/**
	 * Default environment ids for this install. Filterable so staging can point
	 * elsewhere without a code change.
	 */
	private const DEFAULT_AI_ENV_ID         = 'kwirzk4p';
	private const DEFAULT_EMBEDDINGS_ENV_ID = 'wpkaj1fb';

	public static function register(): void {
		add_filter( 'pre_update_option_mwai_options', [ __CLASS__, 'enforce_options' ], 10, 2 );

		add_action( 'admin_notices', [ __CLASS__, 'render_admin_notice' ] );
		add_action( self::CRON_HOOK, [ __CLASS__, 'run_health_check' ] );

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::CRON_HOOK );
		}
	}

	/**
	 * The Anthropic environment the chatbot answers through.
	 */
	public static function ai_env_id(): string {
		return (string) apply_filters( 'insight_chat/anthropic_env_id', self::DEFAULT_AI_ENV_ID );
	}

	/**
	 * The Pinecone environment holding the knowledge base.
	 */
	public static function embeddings_env_id(): string {
		return (string) apply_filters( 'insight_chat/embeddings_env_id', self::DEFAULT_EMBEDDINGS_ENV_ID );
	}

	/**
	 * Filter `mwai_options` on its way to the database.
	 *
	 * Deliberately minimal: it restores what a save lost rather than forcing a
	 * target state, so an intentional settings change still goes through. Keys
	 * this install does not drive (botId, the embeddings post-sync settings — this
	 * install ingests its knowledge base manually) are left entirely alone.
	 *
	 * @param mixed $new The new option value being saved.
	 * @param mixed $old The previous option value.
	 * @return mixed
	 */
	public static function enforce_options( $new, $old ) {
		if ( ! is_array( $new ) ) {
			return $new;
		}
		$old = is_array( $old ) ? $old : [];

		// Restore-when-lost, not force: an admin turning a module off on purpose is
		// a different action from a panel omitting the key.
		if ( empty( $new['module_chatbots'] ) && ! empty( $old['module_chatbots'] ) ) {
			$new['module_chatbots'] = $old['module_chatbots'];
		}
		if ( empty( $new['module_embeddings'] ) && ! empty( $old['module_embeddings'] ) ) {
			$new['module_embeddings'] = $old['module_embeddings'];
		}

		$new['ai_envs'] = self::pin_env(
			$new['ai_envs'] ?? [],
			$old['ai_envs'] ?? [],
			self::ai_env_id()
		);
		$new['embeddings_envs'] = self::pin_env(
			$new['embeddings_envs'] ?? [],
			$old['embeddings_envs'] ?? [],
			self::embeddings_env_id()
		);

		return self::pin_embeddings_defaults( $new, $old );
	}

	/**
	 * Keep one required environment present in an env list, and stop a save from
	 * blanking the API keys of the envs that did survive.
	 *
	 * @param mixed  $new_list    The env list being saved.
	 * @param mixed  $old_list    The env list currently in the database.
	 * @param string $required_id Env id that must not disappear.
	 * @return array
	 */
	private static function pin_env( $new_list, $old_list, string $required_id ): array {
		$new_list = is_array( $new_list ) ? array_values( $new_list ) : [];
		$old_list = is_array( $old_list ) ? array_values( $old_list ) : [];

		$old_by_id = [];
		foreach ( $old_list as $env ) {
			if ( is_array( $env ) && ! empty( $env['id'] ) ) {
				$old_by_id[ (string) $env['id'] ] = $env;
			}
		}

		$found = false;
		foreach ( $new_list as $i => $env ) {
			if ( ! is_array( $env ) || empty( $env['id'] ) ) {
				continue;
			}
			$id = (string) $env['id'];
			// A panel that does not render the key field posts it back empty — keep
			// whatever key the database already holds rather than wiping it.
			if ( empty( $env['apikey'] ) && ! empty( $old_by_id[ $id ]['apikey'] ) ) {
				$new_list[ $i ]['apikey'] = $old_by_id[ $id ]['apikey'];
			}
			if ( $id === $required_id ) {
				$found = true;
			}
		}

		if ( ! $found && isset( $old_by_id[ $required_id ] ) ) {
			$new_list[] = $old_by_id[ $required_id ];
		}

		return $new_list;
	}

	/**
	 * Keep the three embeddings defaults AI Engine has required since 3.5.7.
	 *
	 * `ai_embeddings_default_env` is an *AI* env (the OpenAI env that embeds the
	 * visitor's question), not the Pinecone env, and its id differs per install —
	 * so it is kept from the payload, else the old value, else re-detected.
	 *
	 * @param array $new Option array being written.
	 * @param array $old Option array currently in the database.
	 * @return array
	 */
	private static function pin_embeddings_defaults( array $new, array $old ): array {
		if ( empty( $new['ai_embeddings_default_model'] ) && ! empty( $old['ai_embeddings_default_model'] ) ) {
			$new['ai_embeddings_default_model'] = $old['ai_embeddings_default_model'];
		}
		if ( empty( $new['ai_embeddings_default_dimensions'] ) && ! empty( $old['ai_embeddings_default_dimensions'] ) ) {
			$new['ai_embeddings_default_dimensions'] = $old['ai_embeddings_default_dimensions'];
		}

		$default_env = (string) ( $new['ai_embeddings_default_env'] ?? '' );
		if ( '' === $default_env ) {
			$default_env = (string) ( $old['ai_embeddings_default_env'] ?? '' );
		}
		if ( '' === $default_env || ! self::env_exists( $new['ai_envs'] ?? [], $default_env ) ) {
			$detected = self::first_env_id_of_type( $new['ai_envs'] ?? [], 'openai' );
			if ( '' !== $detected ) {
				$default_env = $detected;
			}
		}
		if ( '' !== $default_env ) {
			$new['ai_embeddings_default_env'] = $default_env;
		}

		return $new;
	}

	private static function env_exists( $list, string $id ): bool {
		foreach ( (array) $list as $env ) {
			if ( is_array( $env ) && (string) ( $env['id'] ?? '' ) === $id ) {
				return true;
			}
		}
		return false;
	}

	private static function first_env_id_of_type( $list, string $type ): string {
		foreach ( (array) $list as $env ) {
			if ( is_array( $env ) && (string) ( $env['type'] ?? '' ) === $type ) {
				return (string) ( $env['id'] ?? '' );
			}
		}
		return '';
	}

	/**
	 * Inspect the live configuration for the faults that silently break the chat.
	 *
	 * Two autoloaded `get_option` calls, no queries — cheap enough for admin_notices.
	 *
	 * @return array{healthy:bool, problems:array<int, array{code:string, detail:string}>}
	 */
	public static function diagnose(): array {
		$problems = [];
		$options  = get_option( 'mwai_options' );
		$bots     = get_option( 'mwai_chatbots' );

		if ( ! is_array( $options ) ) {
			return [
				'healthy'  => false,
				'problems' => [
					[
						'code'   => 'mwai_options_missing',
						'detail' => 'The AI Engine settings option (mwai_options) is missing entirely.',
					],
				],
			];
		}

		$ai_envs  = (array) ( $options['ai_envs'] ?? [] );
		$emb_envs = (array) ( $options['embeddings_envs'] ?? [] );

		// The 2026-08-23 failure: env present, key blank.
		foreach ( [ 'ai_envs' => $ai_envs, 'embeddings_envs' => $emb_envs ] as $bucket => $list ) {
			foreach ( $list as $env ) {
				if ( ! is_array( $env ) || empty( $env['id'] ) ) {
					continue;
				}
				// The OpenAI vector-store env type legitimately carries no key.
				if ( 'openai-vector-store' === (string) ( $env['type'] ?? '' ) ) {
					continue;
				}
				if ( empty( $env['apikey'] ) ) {
					$problems[] = [
						'code'   => 'env_apikey_empty',
						'detail' => sprintf(
							'%s environment "%s" (%s) has an empty API key. Chat requests fail with "No API Key provided. Please visit the Settings."',
							$bucket,
							(string) ( $env['name'] ?? $env['id'] ),
							(string) $env['id']
						),
					];
				}
			}
		}

		// The 2026-07-28 Levinger failure: env gone, chatbot still referencing it.
		$bot = null;
		foreach ( (array) $bots as $candidate ) {
			if ( is_array( $candidate ) && 'default' === (string) ( $candidate['botId'] ?? '' ) ) {
				$bot = $candidate;
				break;
			}
		}
		if ( is_array( $bot ) ) {
			$env_id = (string) ( $bot['envId'] ?? '' );
			if ( '' !== $env_id && ! self::env_exists( $ai_envs, $env_id ) ) {
				$problems[] = [
					'code'   => 'chatbot_env_dangling',
					'detail' => sprintf( 'Chatbot references AI environment "%s", which no longer exists. Every message returns: AI Engine: No environment found for ID (%s).', $env_id, $env_id ),
				];
			}
			$emb_id = (string) ( $bot['embeddingsEnvId'] ?? '' );
			if ( '' !== $emb_id && ! self::env_exists( $emb_envs, $emb_id ) ) {
				$problems[] = [
					'code'   => 'embeddings_env_dangling',
					'detail' => sprintf( 'Chatbot references embeddings environment "%s", which no longer exists. Answers lose the knowledge base.', $emb_id ),
				];
			}
		}

		if ( empty( $options['module_chatbots'] ) ) {
			$problems[] = [
				'code'   => 'module_chatbots_off',
				'detail' => 'AI Engine\'s Chatbots module is off, so the /mwai-ui/v1/chats/submit route is not registered.',
			];
		}
		if ( empty( $options['ai_embeddings_default_env'] ) ) {
			$problems[] = [
				'code'   => 'embeddings_default_env_unset',
				'detail' => 'ai_embeddings_default_env is unset, so visitor questions cannot be embedded and the knowledge base is skipped.',
			];
		}

		return [
			'healthy'  => empty( $problems ),
			'problems' => $problems,
		];
	}

	/**
	 * Show a plain-language error banner on every admin screen while chat is down.
	 * Diagnosed live so it appears on the very next page load after a bad save.
	 */
	public static function render_admin_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$result = self::diagnose();
		if ( $result['healthy'] ) {
			return;
		}
		echo '<div class="notice notice-error"><p><strong>';
		echo esc_html__( 'SUGAR360 chat is down — visitors are getting an error.', 'insight-trupharm-chatbot' );
		echo '</strong></p><ul style="list-style:disc;margin-inline-start:20px;">';
		foreach ( $result['problems'] as $problem ) {
			echo '<li>' . esc_html( $problem['detail'] ) . '</li>';
		}
		echo '</ul><p>';
		echo esc_html__( 'Fix in Meow Apps → AI Engine → Settings → AI Environments.', 'insight-trupharm-chatbot' );
		echo '</p></div>';
	}

	/**
	 * Hourly check so a break is recorded even when nobody is logged in.
	 */
	public static function run_health_check(): void {
		$result = self::diagnose();

		if ( $result['healthy'] ) {
			delete_option( self::STATE_OPTION );
			return;
		}

		$state = get_option( self::STATE_OPTION );
		$first = is_array( $state ) && ! empty( $state['first_seen'] ) ? $state['first_seen'] : gmdate( 'c' );

		update_option(
			self::STATE_OPTION,
			[
				'first_seen' => $first,
				'last_seen'  => gmdate( 'c' ),
				'problems'   => $result['problems'],
			],
			false
		);

		foreach ( $result['problems'] as $problem ) {
			error_log( sprintf( '[insight-chat] AI Engine unhealthy since %s — %s: %s', $first, $problem['code'], $problem['detail'] ) );
		}
	}
}
