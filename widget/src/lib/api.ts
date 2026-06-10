import type { InsightChatBootstrap, Lang, Starter } from '../types';

/** Prior conversation turns. AI Engine reads the history the model sees from the
 *  client-sent `messages` param (query/base.php inject_params → set_messages) —
 *  it does NOT rebuild it from stored discussions. Omitting it = stateless bot. */
export interface HistoryMessage {
  role: 'user' | 'assistant';
  content: string;
}

export interface ChatSubmitResponse {
  /** AI Engine returns the assistant text in `data` (non-streaming) or a `reply` field. */
  data?: string;
  reply?: string;
  success?: boolean;
  message?: string;
  /** Some versions wrap actions/extra under `extra`. */
  extra?: { actions?: unknown[]; chatId?: string };
}

export async function submitChat(
  cfg: InsightChatBootstrap,
  message: string,
  chatId?: string,
  messages: HistoryMessage[] = [],
): Promise<{ text: string; chatId?: string; raw: ChatSubmitResponse }> {
  const res = await fetch(cfg.chatEndpoint, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': cfg.nonce,
    },
    body: JSON.stringify({
      botId: cfg.botId,
      newMessage: message,
      chatId,
      messages,
      stream: false,
    }),
  });

  let json: ChatSubmitResponse | null = null;
  try {
    json = (await res.json()) as ChatSubmitResponse;
  } catch {
    // fall through
  }

  if (!res.ok) {
    const detail = json?.message ? `: ${json.message}` : '';
    throw new Error(`HTTP ${res.status}${detail}`);
  }

  const text = (json?.data ?? json?.reply ?? '').toString();
  return { text, chatId: json?.extra?.chatId, raw: json ?? {} };
}

/**
 * Streaming variant. AI Engine emits SSE events of three types:
 *   - {"type":"live","data":"<delta>"}         per-token text delta to append
 *   - {"type":"end","data":"<json-string>"}    final aggregated response (JSON-encoded)
 *   - {"type":"error","data":"<message>"}      stream error
 *
 * Caller hooks:
 *   - onLive: each delta as it arrives. Caller is responsible for accumulating.
 *   - onEnd:  final fully-aggregated text + chatId, after the stream closes.
 *   - onError: terminal error message.
 *
 * `onEnd` is called exactly once, even if the network closes without an `end` event.
 * In that case `final.text` is whatever the caller accumulated through `onLive`.
 */
export interface StreamCallbacks {
  onLive: (delta: string) => void;
  onEnd: (final: { text: string; chatId?: string }) => void;
  onError: (message: string) => void;
}

export async function submitChatStream(
  cfg: InsightChatBootstrap,
  message: string,
  chatId: string | undefined,
  messages: HistoryMessage[],
  cb: StreamCallbacks,
): Promise<void> {
  const res = await fetch(cfg.chatEndpoint, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': cfg.nonce,
      Accept: 'text/event-stream',
    },
    body: JSON.stringify({
      botId: cfg.botId,
      newMessage: message,
      chatId,
      messages,
      stream: true,
    }),
  });

  if (!res.ok || !res.body) {
    cb.onError(`HTTP ${res.status}`);
    return;
  }

  const reader = res.body.getReader();
  const decoder = new TextDecoder();
  let buffer = '';
  let accumulated = '';
  let finalText: string | undefined;
  let finalChatId: string | undefined;
  let endedExplicitly = false;

  // Process SSE events that have completed (terminated by blank line).
  function pumpBuffer() {
    let idx;
    while ((idx = buffer.indexOf('\n\n')) !== -1) {
      const block = buffer.slice(0, idx);
      buffer = buffer.slice(idx + 2);

      // Multi-line "data:" entries are joined with newlines per SSE spec.
      const lines = block.split('\n').filter((l) => l.startsWith('data:'));
      if (!lines.length) continue;
      const dataStr = lines.map((l) => l.slice(5).replace(/^ /, '')).join('\n');
      if (!dataStr || dataStr === '[DONE]') continue;

      let event: { type?: string; data?: unknown };
      try {
        event = JSON.parse(dataStr);
      } catch {
        continue;
      }

      if (event.type === 'live' && typeof event.data === 'string') {
        accumulated += event.data;
        cb.onLive(event.data);
      } else if (event.type === 'end') {
        endedExplicitly = true;
        // event.data is a JSON-encoded string of the final reply object.
        let reply: unknown = event.data;
        if (typeof reply === 'string') {
          try {
            reply = JSON.parse(reply);
          } catch {
            // leave as string
          }
        }
        if (reply && typeof reply === 'object') {
          const r = reply as { reply?: string; data?: string; chatId?: string; extra?: { chatId?: string } };
          finalText = r.reply ?? r.data ?? accumulated;
          finalChatId = r.chatId ?? r.extra?.chatId;
        } else if (typeof reply === 'string') {
          finalText = reply;
        }
      } else if (event.type === 'error') {
        cb.onError(typeof event.data === 'string' ? event.data : 'stream error');
      }
    }
  }

  try {
    while (true) {
      const { value, done } = await reader.read();
      if (done) break;
      buffer += decoder.decode(value, { stream: true });
      pumpBuffer();
    }
    // Flush any trailing bytes.
    buffer += decoder.decode();
    pumpBuffer();
  } catch (err) {
    cb.onError(err instanceof Error ? err.message : String(err));
    return;
  }

  cb.onEnd({
    text: endedExplicitly && finalText !== undefined ? finalText : accumulated,
    chatId: finalChatId,
  });
}

export async function fetchStarters(cfg: InsightChatBootstrap, lang: Lang): Promise<Starter[]> {
  const url = `${cfg.apiBase}/starter-questions?lang=${encodeURIComponent(lang)}`;
  const res = await fetch(url, { credentials: 'same-origin' });
  if (!res.ok) return [];
  const json = (await res.json()) as { items?: Starter[] };
  return Array.isArray(json.items) ? json.items : [];
}

export async function logEvent(
  cfg: InsightChatBootstrap,
  event_type: 'open' | 'close' | 'action_clicked' | 'starter_clicked' | 'error',
  payload: { session_id?: string; lang?: Lang; meta?: Record<string, unknown> } = {},
): Promise<void> {
  // Fire and forget — we never block the UI on a log call.
  try {
    await fetch(`${cfg.apiBase}/events`, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': cfg.nonce,
      },
      body: JSON.stringify({ event_type, ...payload }),
      keepalive: true,
    });
  } catch {
    // swallow — logging must never break chat
  }
}
