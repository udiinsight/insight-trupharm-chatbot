import { useEffect, useState } from 'react';
import type { InsightChatBootstrap, Lang, Starter } from '../types';
import { STRINGS, dirFor } from '../lib/lang';
import { useChat } from '../hooks/useChat';
import { fetchStarters, logEvent } from '../lib/api';
import { getSessionId } from '../lib/session';
import { resolveUrl } from '../lib/parse';
import { MessageList } from './MessageList';
import { Composer } from './Composer';
import { StarterQuestions } from './StarterQuestions';
import { WelcomeIntro } from './WelcomeIntro';
import { CloseIcon, SparkleIcon } from './icons';

interface Props {
  cfg: InsightChatBootstrap;
  open: boolean;
  onClose: () => void;
}

export function ChatPanel({ cfg, open, onClose }: Props) {
  const chat = useChat(cfg);
  const lang: Lang = chat.lang;
  const t = STRINGS[lang];
  const [starters, setStarters] = useState<Starter[]>([]);
  const [activeLang, setActiveLang] = useState<Lang>(lang);
  const avatar = resolveUrl(cfg.aiAvatar, cfg.siteUrl);

  // Lock the page scroll on small viewports so the chat behaves like a bottom sheet.
  useEffect(() => {
    if (!open) {
      document.body.classList.remove('insight-chat-modal-open');
      return;
    }
    if (window.matchMedia('(max-width: 640px)').matches) {
      document.body.classList.add('insight-chat-modal-open');
    }
    return () => {
      document.body.classList.remove('insight-chat-modal-open');
    };
  }, [open]);

  // Fetch starter questions once on open.
  useEffect(() => {
    if (!open) return;
    let cancelled = false;
    void (async () => {
      const list = await fetchStarters(cfg, activeLang);
      if (!cancelled) setStarters(list);
    })();
    void logEvent(cfg, 'open', { session_id: getSessionId(), lang: activeLang });
    return () => {
      cancelled = true;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, activeLang]);

  // Sync activeLang with what useChat detected from typed text.
  useEffect(() => {
    if (chat.lang !== activeLang) setActiveLang(chat.lang);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [chat.lang]);

  if (!open) return null;

  function handleClose() {
    void logEvent(cfg, 'close', { session_id: getSessionId(), lang: activeLang });
    onClose();
  }

  function onAction(action: string, label: string) {
    void logEvent(cfg, 'action_clicked', {
      session_id: getSessionId(),
      lang: activeLang,
      meta: { action, starter_label: label },
    });
    // V1 action handling: dispatch a CustomEvent the host site can listen for.
    // Doctor finder etc. live in their own surfaces.
    window.dispatchEvent(new CustomEvent('insight-chat:action', { detail: { action, label } }));
  }

  function onFollowUp(prompt: string, label: string) {
    void logEvent(cfg, 'starter_clicked', {
      session_id: getSessionId(),
      lang: activeLang,
      meta: { starter_label: label },
    });
    void chat.send(prompt);
  }

  function pickStarter(s: Starter) {
    void logEvent(cfg, 'starter_clicked', {
      session_id: getSessionId(),
      lang: activeLang,
      meta: { starter_label: s.label },
    });
    void chat.send(s.prompt);
  }

  const showWelcome = chat.messages.length === 0;

  return (
    <div
      role="dialog"
      aria-modal="false"
      aria-label={t.iris}
      dir={dirFor(activeLang)}
      className={[
        'fixed bottom-5 z-[2147483647]',
        // Pinned to the physical right edge in both LTR and RTL.
        'right-5',
        'w-[min(420px,calc(100vw-1.5rem))]',
        'max-sm:inset-0 max-sm:right-0 max-sm:w-auto max-sm:rounded-none',
        'flex flex-col overflow-hidden',
        'rounded-2xl border border-accent-100 bg-gradient-to-b from-accent-50 to-white shadow-bubble',
        'animate-in-up',
      ].join(' ')}
      style={{
        height: 'min(640px, calc(100dvh - 2.5rem))',
      }}
    >
      <header className="flex items-center justify-between gap-3 border-b border-accent-100 bg-white px-4 py-3">
        <div className="flex items-center gap-2.5">
          {avatar ? (
            <img
              src={avatar}
              alt={t.iris}
              className="h-10 w-10 rounded-full object-cover ring-2 ring-accent-100"
            />
          ) : (
            <span className="grid h-10 w-10 place-items-center rounded-full bg-brand-100 text-brand-700 ring-2 ring-accent-100">
              <SparkleIcon className="h-5 w-5" />
            </span>
          )}
          <div className="leading-tight">
            <div className="font-semibold text-ink-900">{t.iris}</div>
            <div className="text-xs text-ink-500">{t.subtitle}</div>
          </div>
        </div>
        <button
          type="button"
          aria-label={t.close}
          onClick={handleClose}
          className="grid h-8 w-8 place-items-center rounded-full text-ink-500 hover:bg-ink-100 hover:text-ink-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-400"
        >
          <CloseIcon className="h-4 w-4" />
        </button>
      </header>

      {showWelcome && <WelcomeIntro lang={activeLang} avatar={avatar} />}

      <MessageList
        cfg={cfg}
        lang={activeLang}
        messages={chat.messages}
        onFollowUp={onFollowUp}
        onAction={onAction}
      />

      {showWelcome && starters.length > 0 && (
        <StarterQuestions lang={activeLang} starters={starters} onPick={pickStarter} />
      )}

      <Composer
        lang={activeLang}
        disabled={chat.isSending}
        onSend={(text) => void chat.send(text)}
        onLangChange={setActiveLang}
      />
    </div>
  );
}
