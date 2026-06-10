import { useCallback, useRef, useState } from 'react';
import type { InsightChatBootstrap, Lang, Message, StructuredReply } from '../types';
import { submitChatStream, logEvent } from '../lib/api';
import type { HistoryMessage } from '../lib/api';
import { parseStructured, progressiveParse } from '../lib/parse';
import { detectLang } from '../lib/lang';
import { getSessionId, newChatId } from '../lib/session';

interface State {
  messages: Message[];
  /** Latest detected language across the conversation. Determines RTL. */
  lang: Lang;
}

const EMPTY_REPLY: StructuredReply = {
  response: '',
  sources: [],
  suggested_actions: [],
  disclaimer_needed: false,
  disclaimer_kind: null,
};

// Typewriter state. AI Engine emits SSE in bursts; revealing tokens immediately makes
// the answer "jump" in chunks. Instead, we buffer the model output and reveal characters
// at a steady rate driven by requestAnimationFrame. The rate adapts: when we're far
// behind the model, we accelerate so we don't lag noticeably; when caught up, we wait.
interface Typewriter {
  /** The latest text we know we should display (grows as the stream comes in). */
  target: string;
  /** How many chars of `target` are currently shown to the user. */
  shown: number;
  /** Set when the stream has produced a complete parseable reply. */
  finalReply: StructuredReply | null;
  /** Active RAF id, or null when no animation frame is pending. */
  raf: number | null;
  /** True after a new send() begins so an in-flight tick from the previous turn aborts. */
  cancelled: boolean;
}

function newTypewriter(): Typewriter {
  return { target: '', shown: 0, finalReply: null, raf: null, cancelled: false };
}

export function useChat(cfg: InsightChatBootstrap) {
  const [state, setState] = useState<State>({ messages: [], lang: cfg.defaultLang });
  const chatIdRef = useRef<string | undefined>(undefined);
  // Prior turns sent with every submit. AI Engine builds the model's history from
  // this client-side array (it does not rebuild it from stored discussions) — without
  // it every message starts a brand-new conversation. Assistant entries keep the raw
  // model output (the JSON string) so they match what the server logs in Discussions.
  const historyRef = useRef<HistoryMessage[]>([]);
  const inFlight = useRef(false);
  const typewriter = useRef<Typewriter>(newTypewriter());

  const send = useCallback(
    async (message: string) => {
      const trimmed = message.trim();
      if (!trimmed || inFlight.current) return;
      inFlight.current = true;

      // Reset typewriter for the new turn. Cancel any pending frame from the previous one.
      if (typewriter.current.raf !== null) {
        cancelAnimationFrame(typewriter.current.raf);
      }
      typewriter.current.cancelled = true;
      typewriter.current = newTypewriter();
      const tw = typewriter.current;

      const lang = detectLang(trimmed, state.lang);
      const userMsg: Message = {
        id: `u_${Date.now()}_${Math.random().toString(36).slice(2)}`,
        role: 'user',
        text: trimmed,
      };
      const placeholderId = `a_${Date.now()}_${Math.random().toString(36).slice(2)}`;
      const placeholder: Message = {
        id: placeholderId,
        role: 'assistant',
        pending: true,
        reply: null,
      };

      setState((s) => ({ messages: [...s.messages, userMsg, placeholder], lang }));

      let buffer = '';

      const updatePlaceholder = (patch: Partial<Message>) => {
        setState((s) => ({
          ...s,
          messages: s.messages.map((m) => (m.id === placeholderId ? { ...m, ...patch } : m)),
        }));
      };

      const scheduleTick = () => {
        if (tw.raf !== null) return;
        tw.raf = requestAnimationFrame(tick);
      };

      const tick = () => {
        tw.raf = null;
        if (tw.cancelled) return;

        const target = tw.target;
        const targetLen = target.length;

        if (tw.shown < targetLen) {
          // Smooth typewriter rate. Baseline 2 chars/frame (≈120 chars/sec on 60Hz),
          // gently scales up to a hard cap of 4 chars/frame (≈240/sec) when bursts
          // of tokens arrive at once. The cap is what prevents perceived "chunkiness":
          // even a 200-char SSE chunk reveals over ~50 frames (~830ms), not in one go.
          const lag = targetLen - tw.shown;
          const advance = Math.min(4, Math.max(2, Math.ceil(lag / 24)));
          tw.shown = Math.min(targetLen, tw.shown + advance);
          updatePlaceholder({
            pending: true,
            reply: { ...EMPTY_REPLY, response: target.slice(0, tw.shown) },
          });
        }

        if (tw.shown < target.length) {
          // Still revealing.
          scheduleTick();
          return;
        }

        // Caught up to whatever target says right now.
        if (tw.finalReply) {
          // The stream produced a complete reply; lock in the full structure
          // (sources, actions, disclaimer) now that the visible text is up to date.
          updatePlaceholder({ pending: false, reply: tw.finalReply });
        }
        // Otherwise: stream is still running, idle until next chunk arrives.
      };

      try {
        if (!chatIdRef.current) chatIdRef.current = newChatId();
        await submitChatStream(cfg, trimmed, chatIdRef.current, historyRef.current.slice(-14), {
          onLive: (delta) => {
            buffer += delta;
            if (tw.finalReply) return; // already locked; ignore trailing tokens
            const partial = progressiveParse(buffer);
            if (partial.full) {
              tw.finalReply = partial.full;
              tw.target = partial.full.response || tw.target;
              scheduleTick();
              return;
            }
            if (typeof partial.response === 'string') {
              tw.target = partial.response;
              scheduleTick();
            }
          },
          onEnd: ({ text, chatId }) => {
            if (chatId) chatIdRef.current = chatId;
            const raw = text || buffer;
            if (raw) {
              historyRef.current = [
                ...historyRef.current,
                { role: 'user' as const, content: trimmed },
                { role: 'assistant' as const, content: raw },
              ].slice(-14);
            }
            if (tw.finalReply) {
              // Already locked from progressiveParse(.full).
              scheduleTick();
              return;
            }
            const fallback =
              parseStructured(text || buffer) ??
              ({
                ...EMPTY_REPLY,
                response: text || buffer || '',
              } as StructuredReply);
            tw.finalReply = fallback;
            tw.target = fallback.response || tw.target;
            scheduleTick();
          },
          onError: (msg) => {
            tw.cancelled = true;
            updatePlaceholder({ pending: false, error: msg, reply: null });
            void logEvent(cfg, 'error', {
              session_id: getSessionId(),
              lang,
              meta: { error_code: msg.slice(0, 120) },
            });
          },
        });
      } catch (err) {
        const error = err instanceof Error ? err.message : String(err);
        tw.cancelled = true;
        updatePlaceholder({ pending: false, error, reply: null });
        void logEvent(cfg, 'error', {
          session_id: getSessionId(),
          lang,
          meta: { error_code: error.slice(0, 120) },
        });
      } finally {
        inFlight.current = false;
      }
    },
    [cfg, state.lang],
  );

  return {
    messages: state.messages,
    lang: state.lang,
    send,
    isSending: inFlight.current,
  };
}
