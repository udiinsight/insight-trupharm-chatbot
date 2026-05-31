import type { StructuredReply } from '../types';

/**
 * The system prompt instructs the model to return a single JSON object.
 * Most of the time it does. Occasionally Claude wraps it in a fenced code block
 * (```json … ```), so we strip that fence as a fallback before parsing.
 *
 * Returns null if the text cannot be coerced into our expected shape.
 */
export function parseStructured(raw: string): StructuredReply | null {
  if (!raw) return null;
  const trimmed = raw.trim();
  const candidates: string[] = [trimmed];

  // Strip ```json fences if present.
  const fenceMatch = trimmed.match(/^```(?:json)?\s*\n([\s\S]*?)\n```$/);
  if (fenceMatch) candidates.push(fenceMatch[1].trim());

  // Try to locate the first { … } block as a last-resort heuristic.
  const firstBrace = trimmed.indexOf('{');
  const lastBrace = trimmed.lastIndexOf('}');
  if (firstBrace >= 0 && lastBrace > firstBrace) {
    candidates.push(trimmed.slice(firstBrace, lastBrace + 1));
  }

  for (const c of candidates) {
    try {
      const parsed = JSON.parse(c) as Partial<StructuredReply>;
      if (typeof parsed.response === 'string') {
        return {
          response: parsed.response,
          sources: Array.isArray(parsed.sources) ? parsed.sources : [],
          suggested_actions: Array.isArray(parsed.suggested_actions) ? parsed.suggested_actions : [],
          disclaimer_needed: Boolean(parsed.disclaimer_needed),
          disclaimer_kind: parsed.disclaimer_kind ?? null,
        };
      }
    } catch {
      // try next candidate
    }
  }

  return null;
}

/**
 * Pull whatever's usable out of a partially-streamed JSON buffer.
 *  - If the buffer is already a complete parseable object, returns `full`.
 *  - Otherwise tries to read just the in-progress `response` field for live display.
 *  - Returns `{}` when nothing useful is parseable yet (e.g. just `{` and a key).
 */
export function progressiveParse(buffer: string): { response?: string; full?: StructuredReply } {
  const trimmed = buffer.trim();
  if (!trimmed) return {};

  const full = parseStructured(trimmed);
  if (full) return { full };

  // Live extraction of the in-progress "response" string.
  // Match every char that's either non-quote-non-backslash, OR a backslash followed by anything (so escaped chars inside the string don't trip us).
  const m = trimmed.match(/"response"\s*:\s*"((?:[^"\\]|\\.)*)/);
  if (m) {
    const raw = m[1];
    const decoded = raw
      .replace(/\\n/g, '\n')
      .replace(/\\r/g, '')
      .replace(/\\t/g, '\t')
      .replace(/\\"/g, '"')
      .replace(/\\\\/g, '\\');
    return { response: decoded };
  }
  return {};
}

// Protocols we will render in <a href> / <img src>. Anything else
// (javascript:, data:, vbscript:, file:, blob:, etc.) is dropped.
const SAFE_URL_PROTOCOLS = new Set(['http:', 'https:', 'tel:', 'mailto:']);

/**
 * Resolve a possibly-relative URL (as returned by the LLM, or read from the
 * server-injected bootstrap) against the site origin, and reject any URL whose
 * protocol is not in the safe list. Returns '' for empty input or unsafe URLs.
 */
export function resolveUrl(url: string, siteUrl: string): string {
  if (!url) return '';
  try {
    const u = new URL(url, siteUrl);
    if (!SAFE_URL_PROTOCOLS.has(u.protocol)) return '';
    return u.toString();
  } catch {
    return '';
  }
}
