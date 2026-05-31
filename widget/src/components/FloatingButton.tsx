import type { Lang } from '../types';
import { STRINGS } from '../lib/lang';
import { ChatIcon, CloseIcon } from './icons';

interface Props {
  lang: Lang;
  open: boolean;
  onToggle: () => void;
  /** Hide the launcher (and chat) for the rest of the session. */
  onDismiss: () => void;
  avatar?: string;
}

/** Floating launcher pinned to the physical right edge in both LTR and RTL.
 *  Includes a small "How can I help?" callout above the button (only when the chat
 *  panel is closed) and a tiny X on the callout that hides the whole widget for the
 *  current browser session. */
export function FloatingButton({ lang, open, onToggle, onDismiss, avatar }: Props) {
  const t = STRINGS[lang];
  return (
    <div className="fixed bottom-5 right-5 z-[2147483646] flex flex-col items-end gap-2">
      {!open && (
        <div
          className={[
            'animate-in-up flex items-center gap-2',
            'rounded-2xl rounded-br-md border border-accent-100 bg-white px-3.5 py-2 shadow-bubble',
          ].join(' ')}
          dir={lang === 'he' ? 'rtl' : 'ltr'}
        >
          <span className="text-sm font-medium text-ink-800 whitespace-nowrap">{t.callout}</span>
          <button
            type="button"
            aria-label={t.dismiss}
            onClick={(e) => {
              e.stopPropagation();
              onDismiss();
            }}
            className="grid h-5 w-5 place-items-center rounded-full text-ink-400 hover:bg-ink-100 hover:text-ink-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-300"
          >
            <CloseIcon className="h-3 w-3" />
          </button>
        </div>
      )}
      <button
        type="button"
        aria-label={t.open}
        aria-expanded={open}
        onClick={onToggle}
        className={[
          'flex h-[68px] w-[68px] items-center justify-center self-start',
          'overflow-hidden rounded-full text-white',
          'bg-brand-600 hover:bg-brand-700 active:bg-brand-800',
          'shadow-button ring-4 ring-accent-200/60 transition-transform',
          'hover:scale-[1.04] active:scale-[0.98]',
          'focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-300',
          open ? 'scale-95 opacity-90' : '',
        ].join(' ')}
      >
        {avatar ? (
          <img src={avatar} alt={t.open} className="h-full w-full rounded-full object-cover" />
        ) : (
          <ChatIcon className="h-6 w-6" />
        )}
      </button>
    </div>
  );
}
