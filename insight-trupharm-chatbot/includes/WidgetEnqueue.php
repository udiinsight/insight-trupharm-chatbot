<?php
/**
 * Enqueue the Insight Chat React widget on every public-facing page.
 *
 * The compiled assets live in /dist/ alongside this mu-plugin and are produced by
 * `npm run build` inside /widget/.  The WP-side responsibility is:
 *   - register the script + stylesheet on every front-end request,
 *   - inject a `window.INSIGHT_CHAT` bootstrap object with the REST URLs and a fresh nonce,
 *   - print a `<div id="insight-chat-root">` near </body> so the React app has a mount point.
 *
 * The widget skips wp-admin, the AI Engine block editor, the REST API, AJAX, and the
 * Bricks editor URL parameter so authoring tools are never wrapped.
 *
 * @package InsightChat
 */

namespace InsightChat;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WidgetEnqueue {

	public const HANDLE_JS  = 'insight-chat-widget';
	public const HANDLE_CSS = 'insight-chat-widget';

	public static function register(): void {
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue' ], 20 );
		add_action( 'wp_footer',          [ __CLASS__, 'render_mount_point' ] );
		// Prevent WP Rocket / generic optimizers from re-minifying our pre-minified IIFE bundle,
		// which can break it. We tag the script with no-minify/no-defer/no-lazy attributes that
		// every major WP optimization plugin honours.
		add_filter( 'script_loader_tag', [ __CLASS__, 'mark_script_untouchable' ], 10, 2 );
		add_filter( 'style_loader_tag',  [ __CLASS__, 'mark_style_untouchable' ], 10, 2 );
		add_filter( 'rocket_excluded_inline_js_content', [ __CLASS__, 'rocket_inline_exclusions' ] );
		add_filter( 'rocket_minify_excluded_external_js', [ __CLASS__, 'rocket_minify_exclusions' ] );
		add_filter( 'rocket_exclude_css', [ __CLASS__, 'rocket_css_exclusions' ] );
		// Skip the mount node from WP Rocket's "Lazy Render Content" optimisation. Otherwise
		// long pages (the homepage in particular) get `data-wpr-lazyrender` injected onto
		// `<div id="insight-chat-root">`, which applies `content-visibility: auto` to it.
		// The CSS contain:paint side-effect of that hides our `position: fixed` floating
		// button until the user scrolls the mount node into view at the bottom of the body.
		add_filter( 'rocket_lrc_exclusions', [ __CLASS__, 'rocket_lrc_exclusions' ] );
	}

	public static function mark_script_untouchable( string $tag, string $handle ): string {
		if ( $handle !== self::HANDLE_JS ) {
			return $tag;
		}
		// $tag often contains BOTH our `wp_add_inline_script(..., 'before')` block AND the
		// external <script src="..."> for the bundle. We only need the no-minify attrs on
		// the external one (Rocket rewrites the URL to its /cache/min/ when the attribute
		// is missing, breaking our pre-minified IIFE). Match script tags that have a `src=`
		// attribute and skip the inline-before tag.
		$attrs = ' data-no-minify="1" data-no-defer="1" data-cfasync="false" data-no-optimize="1"';
		return preg_replace_callback(
			'/<script\b([^>]*)>/i',
			function ( $m ) use ( $attrs ) {
				if ( strpos( $m[1], 'src=' ) === false ) {
					return $m[0];
				}
				return '<script' . $attrs . $m[1] . '>';
			},
			$tag
		);
	}

	public static function mark_style_untouchable( string $tag, string $handle ): string {
		if ( $handle !== self::HANDLE_CSS ) {
			return $tag;
		}
		// Same idea as mark_script_untouchable — keep Rocket from minify-rewriting our CSS.
		// CSS minification can break our @font-face URLs and the unicode-range declarations.
		$attrs = ' data-no-minify="1" data-cfasync="false" data-no-optimize="1"';
		return preg_replace_callback(
			'/<link\b([^>]*)>/i',
			function ( $m ) use ( $attrs ) {
				if ( strpos( $m[1], 'href=' ) === false ) {
					return $m[0];
				}
				return '<link' . $attrs . $m[1] . '>';
			},
			$tag
		);
	}

	public static function rocket_inline_exclusions( $excluded ): array {
		$excluded   = is_array( $excluded ) ? $excluded : [];
		$excluded[] = 'window.INSIGHT_CHAT';
		return $excluded;
	}

	public static function rocket_minify_exclusions( $excluded ): array {
		$excluded   = is_array( $excluded ) ? $excluded : [];
		$excluded[] = '/wp-content/mu-plugins/insight-chat/dist/insight-chat.js';
		return $excluded;
	}

	public static function rocket_css_exclusions( $excluded ): array {
		$excluded   = is_array( $excluded ) ? $excluded : [];
		$excluded[] = '/wp-content/mu-plugins/insight-chat/dist/insight-chat.css';
		return $excluded;
	}

	public static function rocket_lrc_exclusions( $exclusions ): array {
		$exclusions   = is_array( $exclusions ) ? $exclusions : [];
		// Matched as a substring of each element's opening tag — keeps the mount node off
		// Rocket's lazy-render list and lets the React widget render normally on long pages.
		$exclusions[] = 'id="insight-chat-root"';
		return $exclusions;
	}

	public static function enqueue(): void {
		if ( ! self::is_eligible() ) {
			return;
		}

		$dist_url = self::dist_url();
		$dist_dir = INSIGHT_CHAT_PATH . '/dist';

		$js_path  = $dist_dir . '/insight-chat.js';
		$css_path = $dist_dir . '/insight-chat.css';

		if ( ! is_readable( $js_path ) ) {
			// dist/ not deployed yet — fail silently rather than 404 on production.
			return;
		}

		$ver = (string) filemtime( $js_path );

		// WP Rocket strips the standard `?ver=` query param, which means the script URL
		// never changes across deploys and browsers cache the JS forever. We bake the
		// mtime into the URL itself with `?v=<mtime>` so the URL DOES change on every
		// build — passing `null` for the version arg stops WP from appending its own.
		wp_register_script(
			self::HANDLE_JS,
			$dist_url . '/insight-chat.js?v=' . $ver,
			[],
			null,
			true
		);
		wp_enqueue_script( self::HANDLE_JS );

		if ( is_readable( $css_path ) ) {
			$css_ver = (string) filemtime( $css_path );
			wp_register_style(
				self::HANDLE_CSS,
				$dist_url . '/insight-chat.css?v=' . $css_ver,
				[],
				null
			);
			wp_enqueue_style( self::HANDLE_CSS );
		}

		$bootstrap = [
			'chatEndpoint' => esc_url_raw( rest_url( 'mwai-ui/v1/chats/submit' ) ),
			'apiBase'      => esc_url_raw( rest_url( RestApi::NAMESPACE ) ),
			'nonce'        => wp_create_nonce( 'wp_rest' ),
			'siteUrl'      => esc_url_raw( home_url( '/' ) ),
			'defaultLang'  => self::detect_lang(),
			'botId'        => self::bot_id(),
			'aiAvatar'     => self::ai_avatar_url(),
		];

		wp_add_inline_script(
			self::HANDLE_JS,
			'window.INSIGHT_CHAT = ' . wp_json_encode( $bootstrap ) . ';',
			'before'
		);
	}

	public static function render_mount_point(): void {
		if ( ! self::is_eligible() ) {
			return;
		}
		// The React entry script will create this element if missing, but rendering it
		// here makes the layout reservation deterministic and lets us print loading hints.
		echo '<div id="insight-chat-root" data-insight-chat="ready"></div>';
	}

	// ------------------------------------------------------------------ helpers

	private static function is_eligible(): bool {
		if ( is_admin() ) {
			return false;
		}
		if ( wp_doing_ajax() || wp_doing_cron() ) {
			return false;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}
		// Bricks Builder edit mode — never inject the widget into the builder canvas.
		if ( isset( $_GET['bricks'] ) && in_array( $_GET['bricks'], [ 'run', 'editor' ], true ) ) {
			return false;
		}
		// Don't load on robots-only pages (e.g. WP_USE_THEMES === false).
		if ( defined( 'WP_USE_THEMES' ) && WP_USE_THEMES === false ) {
			return false;
		}

		/**
		 * Allow third-party code to hide the widget on specific routes (e.g. checkout flows).
		 *
		 * @param bool $eligible
		 */
		return (bool) apply_filters( 'insight_chat/widget/eligible', true );
	}

	private static function dist_url(): string {
		$dist = INSIGHT_CHAT_PATH . '/dist';
		// Resolve dist/ to a public URL whether the mu-plugin lives under mu-plugins or plugins.
		$content_dir = wp_normalize_path( WP_CONTENT_DIR );
		$dist_norm   = wp_normalize_path( $dist );
		if ( strpos( $dist_norm, $content_dir ) === 0 ) {
			return content_url( substr( $dist_norm, strlen( $content_dir ) ) );
		}
		// Fallback for unusual layouts.
		return plugins_url( 'dist', INSIGHT_CHAT_FILE );
	}

	private static function detect_lang(): string {
		$locale = function_exists( 'pll_current_language' ) ? (string) pll_current_language( 'slug' ) : substr( get_locale(), 0, 2 );
		$locale = strtolower( $locale );
		return in_array( $locale, [ 'he', 'iw' ], true ) ? 'he' : 'en';
	}

	private static function bot_id(): string {
		// V1: a single chatbot called "default". Keep configurable for later phases.
		return (string) apply_filters( 'insight_chat/widget/bot_id', 'default' );
	}

	/**
	 * Read the avatar URL configured on the AI Engine chatbot (mwai_chatbots[botId].aiAvatarUrl).
	 * Returns an empty string when the chatbot has no avatar set; the widget falls back to its
	 * built-in icon in that case.
	 */
	private static function ai_avatar_url(): string {
		$chatbots = get_option( 'mwai_chatbots', [] );
		if ( ! is_array( $chatbots ) ) {
			return '';
		}
		$target = self::bot_id();
		foreach ( $chatbots as $bot ) {
			if ( ! is_array( $bot ) ) {
				continue;
			}
			if ( ( $bot['botId'] ?? '' ) !== $target ) {
				continue;
			}
			if ( empty( $bot['aiAvatar'] ) || empty( $bot['aiAvatarUrl'] ) ) {
				return '';
			}
			return esc_url_raw( (string) $bot['aiAvatarUrl'] );
		}
		return '';
	}
}
