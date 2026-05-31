import { useEffect, useRef } from 'react';
import type { InsightChatBootstrap, Lang, Message } from '../types';
import { MessageBubble } from './MessageBubble';

interface Props {
  cfg: InsightChatBootstrap;
  lang: Lang;
  messages: Message[];
  onFollowUp: (prompt: string, label: string) => void;
  onAction: (action: string, label: string) => void;
}

export function MessageList({ cfg, lang, messages, onFollowUp, onAction }: Props) {
  const ref = useRef<HTMLDivElement | null>(null);

  // Auto-scroll on new messages, status changes, AND streamed-text growth so the
  // view follows the bot's reply as it streams in.
  const last = messages[messages.length - 1];
  useEffect(() => {
    if (!ref.current) return;
    ref.current.scrollTop = ref.current.scrollHeight;
  }, [messages.length, last?.pending, last?.reply?.response?.length]);

  return (
    <div
      ref={ref}
      className="ic-scroll flex-1 space-y-3 overflow-y-auto px-4 py-4"
      role="log"
      aria-live="polite"
      aria-relevant="additions"
    >
      {messages.map((m) => (
        <MessageBubble
          key={m.id}
          message={m}
          lang={lang}
          cfg={cfg}
          onFollowUp={onFollowUp}
          onAction={onAction}
        />
      ))}
    </div>
  );
}
