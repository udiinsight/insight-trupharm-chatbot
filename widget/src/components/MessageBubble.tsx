import type { InsightChatBootstrap, Lang, Message } from '../types';
import { STRINGS } from '../lib/lang';
import { SuggestedActions } from './SuggestedActions';
import { DisclaimerCard } from './DisclaimerCard';
import { LoadingStatus } from './LoadingStatus';

interface Props {
  lang: Lang;
  message: Message;
  cfg: InsightChatBootstrap;
  onFollowUp: (prompt: string, label: string) => void;
  onAction: (action: string, label: string) => void;
}

export function MessageBubble({ lang, message, cfg, onFollowUp, onAction }: Props) {
  const t = STRINGS[lang];

  if (message.role === 'user') {
    return (
      <div className="flex ltr:justify-end rtl:justify-start">
        <div className="max-w-[85%] rounded-2xl rounded-br-md bg-brand-600 px-3.5 py-2.5 text-white shadow-sm whitespace-pre-wrap break-words">
          {message.text}
        </div>
      </div>
    );
  }

  if (message.pending) {
    // While streaming, show the partial response text with a blinking caret.
    // Before any text arrives, show the staged loading bubble.
    const partial = message.reply?.response;
    if (partial && partial.length > 0) {
      return (
        <div className="flex ltr:justify-start rtl:justify-end">
          <div className="max-w-[92%] rounded-2xl rounded-bl-md bg-white border border-ink-100 px-3.5 py-3 shadow-bubble">
            <div className="text-ink-900 whitespace-pre-wrap leading-relaxed break-words">
              {partial}
              <span
                aria-hidden
                className="ml-0.5 inline-block h-4 w-[2px] -mb-0.5 align-middle bg-brand-600 animate-pulse-soft"
              />
            </div>
          </div>
        </div>
      );
    }
    return <LoadingStatus lang={lang} />;
  }

  if (message.error) {
    return (
      <div className="flex ltr:justify-start rtl:justify-end">
        <div className="max-w-[90%] rounded-2xl rounded-bl-md border border-red-200 bg-red-50 px-3.5 py-2.5 text-red-900 text-sm">
          {t.error}
        </div>
      </div>
    );
  }

  const reply = message.reply;
  if (!reply) return null;

  // Emergency disclaimers move ABOVE the answer; everything else below.
  const showDisclaimerOnTop = reply.disclaimer_kind === 'emergency';

  return (
    <div className="flex ltr:justify-start rtl:justify-end">
      <div className="max-w-[92%] rounded-2xl rounded-bl-md bg-white border border-ink-100 px-3.5 py-3 shadow-bubble">
        {showDisclaimerOnTop && reply.disclaimer_needed && (
          <DisclaimerCard lang={lang} kind={reply.disclaimer_kind} />
        )}

        <div className="text-ink-900 whitespace-pre-wrap leading-relaxed break-words">
          {reply.response}
        </div>

        <SuggestedActions
          lang={lang}
          actions={reply.suggested_actions}
          siteUrl={cfg.siteUrl}
          onFollowUp={onFollowUp}
          onAction={onAction}
        />

        {!showDisclaimerOnTop && reply.disclaimer_needed && (
          <DisclaimerCard lang={lang} kind={reply.disclaimer_kind} />
        )}
      </div>
    </div>
  );
}
