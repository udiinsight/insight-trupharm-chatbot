<?php
/**
 * Rewrite the embeddings-context block so the LLM sees a clear source title (and, for
 * post-backed embeddings, a permalink) with each retrieved chunk, instead of bare body text.
 *
 * AI Engine's default `context_search` concatenates `$data['content']` for each retrieved
 * chunk without any source attribution. The model then has to guess where the text came
 * from, which produces vague or invented citations.
 *
 * Sugar360's knowledge base is document-based: each entry is a manual/PDF embedding whose
 * `title` is the document name. Those documents have no public permalink, so we cite the
 * document title only. (The post-backed branch is kept for any future post-synced content.)
 *
 * This filter runs at priority 20 (after AI Engine's own priority-10 `context_search`).
 * We re-derive the content from `$context['embeddings']` and wrap every chunk with a clear
 * `[Source #N] Title: … [| URL: …]` header before the body, and an `[end source #N]` trailer.
 * The system prompt then instructs the LLM to cite those titles (and copy any URL verbatim).
 *
 * @package InsightChat
 */

namespace InsightChat;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ContextEnricher {

	public static function register(): void {
		// Priority 20 = after AI Engine's own priority-10 `context_search`.
		add_filter( 'mwai_context_search', [ __CLASS__, 'enrich' ], 20, 3 );
	}

	/**
	 * @param mixed $context AI Engine's context_search output (array or null).
	 * @param mixed $query   The Meow_MWAI_Query object.
	 * @param array $options Search options.
	 * @return mixed Same shape as $context, with `content` rewritten.
	 */
	public static function enrich( $context, $query, $options ) {
		unset( $query, $options );
		if ( ! is_array( $context ) ) {
			return $context;
		}
		$embeddings = $context['embeddings'] ?? [];
		if ( ! is_array( $embeddings ) || empty( $embeddings ) ) {
			return $context;
		}

		global $mwai_embeddings;
		if ( ! $mwai_embeddings ) {
			return $context;
		}

		$blocks = [];
		$index  = 0;
		foreach ( $embeddings as $row ) {
			++$index;
			$embed_id = (string) ( $row['id'] ?? '' );
			$type     = (string) ( $row['type'] ?? '' );
			$ref      = (int) ( $row['ref'] ?? 0 );

			$data = [];
			if ( $embed_id !== '' && method_exists( $mwai_embeddings, 'get_vector_by_remoteId' ) ) {
				$fetched = $mwai_embeddings->get_vector_by_remoteId( $embed_id );
				if ( is_array( $fetched ) ) {
					$data = $fetched;
				}
			}
			$body = ! empty( $data['content'] ) ? (string) $data['content'] : '';
			if ( $body === '' ) {
				continue;
			}

			$title = '';
			$url   = '';
			if ( $type === 'postId' && $ref > 0 ) {
				// Post-backed embedding: cite the post title + permalink.
				$title = (string) ( $row['title'] ?: get_the_title( $ref ) );
				$url   = (string) get_permalink( $ref );
			} else {
				// Document / manual embedding: cite the document title. Uploaded knowledge has no
				// public permalink, so the source is title-only (the system prompt cites titles).
				$title = (string) ( $row['title'] ?? '' );
				if ( $title === '' && ! empty( $data['title'] ) ) {
					$title = (string) $data['title'];
				}
			}
			$url = self::relativize( $url );

			$header_parts = [ "[Source #$index]" ];
			if ( $title !== '' ) {
				$header_parts[] = 'Title: ' . self::flatten( $title );
			}
			if ( $url !== '' ) {
				$header_parts[] = 'URL: ' . $url;
			}
			$header = implode( ' | ', $header_parts );

			$blocks[] = $header . "\n" . trim( $body ) . "\n[end source #$index]";
		}

		if ( empty( $blocks ) ) {
			return $context;
		}

		$context['content'] = implode( "\n\n", $blocks );
		return $context;
	}

	/**
	 * Convert an absolute permalink to a site-relative path so the LLM is more likely
	 * to copy it verbatim and the frontend can resolve it against the site origin.
	 */
	private static function relativize( string $url ): string {
		if ( $url === '' ) {
			return '';
		}
		$home = home_url( '/' );
		if ( strpos( $url, $home ) === 0 ) {
			$rel = '/' . ltrim( substr( $url, strlen( $home ) ), '/' );
			return $rel === '' ? '/' : $rel;
		}
		return $url;
	}

	private static function flatten( string $s ): string {
		return trim( preg_replace( '/\s+/', ' ', $s ) );
	}
}
