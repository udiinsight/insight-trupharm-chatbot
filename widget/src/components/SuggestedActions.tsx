import type { Lang, SuggestedAction } from '../types';
import { resolveUrl } from '../lib/parse';
import { STRINGS } from '../lib/lang';
import { ExternalIcon, LinkIcon, PhoneIcon, WhatsAppIcon } from './icons';
import type { JSX } from 'react';

// Customer-service WhatsApp (054-5005138). Not published on the site yet — the number
// lives here (and in the system prompt) so the widget builds the wa.me link itself and
// the LLM only supplies the plain-text conversation summary.
const WHATSAPP_PHONE = '972545005138';

/** wa.me link with the localized opener + the LLM's conversation summary prefilled. */
function whatsappUrl(lang: Lang, summary: string): string {
  const opener = STRINGS[lang].whatsappOpener;
  const text = summary ? `${opener}\n${summary}` : opener;
  return `https://wa.me/${WHATSAPP_PHONE}?text=${encodeURIComponent(text)}`;
}

interface Props {
  lang: Lang;
  actions: SuggestedAction[];
  siteUrl: string;
  onFollowUp: (prompt: string, label: string) => void;
  onAction: (action: string, label: string) => void;
  onNavigate?: (url: string, label: string) => void;
}

function navigateIcon(url: string): JSX.Element {
  if (url.startsWith('tel:')) return <PhoneIcon className="h-4 w-4 shrink-0" />;
  if (url.startsWith('https://api.whatsapp.com') || url.startsWith('https://wa.me')) {
    return <WhatsAppIcon className="h-4 w-4 shrink-0" />;
  }
  if (url.startsWith('http')) return <ExternalIcon className="h-3.5 w-3.5 shrink-0" />;
  return <LinkIcon className="h-3.5 w-3.5 shrink-0" />;
}

// Built-in actions: each maps to either a fallback URL (opens in a new tab) or a Bricks
// popup id (opens the in-page Bricks popup via the global bricksOpenPopup function).
// The `insight-chat:action` CustomEvent still fires for any host-side hooks.
type ActionTarget = { url: string } | { popupId: number };

// Sugar360 routes contact/purchase through explicit `navigate` CTAs (WhatsApp / phone / contact /
// purchase URLs) emitted by the system prompt, not built-in action targets. Kept empty so a stray
// `action` type falls through to a no-navigation button (fires the insight-chat:action event only).
const ACTION_TARGETS: Record<string, ActionTarget> = {};

declare global {
  interface Window {
    bricksOpenPopup?: (id: number | string | HTMLElement, timeout?: number, extra?: unknown) => void;
  }
}

/** Open a Bricks popup by id. Some Bricks builds wire the popup via the
 *  `data-interactions` attribute on a trigger element (with its own animation flow);
 *  others expose a global `bricksOpenPopup`. We try the click-real-trigger path first
 *  so animations match exactly what a visitor would see if they clicked the actual
 *  contact button on the site. Falls through to the API call if no trigger is found. */
function openBricksPopup(id: number) {
  const idStr = String(id);
  // Bricks "interactions" stores popup config as JSON inside data-interactions.
  // Match both quoted-string and bare-number variants of the templateId field.
  const selectors = [
    `[data-interactions*='"templateId":"${idStr}"']`,
    `[data-interactions*='"templateId":${idStr}']`,
    `[data-popup-id="${idStr}"]`,
  ];
  for (const sel of selectors) {
    const trigger = document.querySelector<HTMLElement>(sel);
    if (trigger) {
      trigger.click();
      return;
    }
  }
  if (typeof window.bricksOpenPopup === 'function') {
    window.bricksOpenPopup(id);
  }
}

export function SuggestedActions({ lang, actions, siteUrl, onFollowUp, onAction, onNavigate }: Props) {
  if (!actions?.length) return null;
  // Two pill styles. `cta` for action + navigate (the visitor's primary next step).
  // `whiteOutline` for follow_up (a continue-the-thread question). Less visual weight
  // because follow-ups are typically secondary to the explicit CTAs.
  const pillBase =
    'inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm transition-colors ' +
    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-400';
  const cta =
    'border border-brand-200 bg-brand-50 text-brand-700 hover:bg-brand-100 hover:border-brand-300';
  const whiteOutline =
    'border border-ink-200 bg-white text-ink-800 hover:border-brand-300 hover:text-brand-700';

  return (
    <div className="mt-3 flex flex-wrap gap-2">
      {actions.map((a, i) => {
        const key = `${a.type}-${i}-${a.label}`;

        if (a.type === 'follow_up') {
          return (
            <button
              key={key}
              type="button"
              onClick={() => onFollowUp(a.prompt, a.label)}
              className={`${pillBase} ${whiteOutline}`}
              dir={lang === 'he' ? 'rtl' : 'ltr'}
            >
              {a.label}
            </button>
          );
        }

        if (a.type === 'whatsapp') {
          return (
            <a
              key={key}
              href={whatsappUrl(lang, a.summary ?? '')}
              target="_blank"
              rel="noopener noreferrer"
              // Log the bare channel URL, not the prefilled text (keeps the summary out of analytics).
              onClick={() => onNavigate?.(`https://wa.me/${WHATSAPP_PHONE}`, a.label)}
              className={`${pillBase} ${cta}`}
              dir={lang === 'he' ? 'rtl' : 'ltr'}
            >
              <WhatsAppIcon className="h-4 w-4 shrink-0" />
              <span>{a.label}</span>
            </a>
          );
        }

        if (a.type === 'navigate') {
          return (
            <a
              key={key}
              href={resolveUrl(a.url, siteUrl)}
              target="_blank"
              rel="noopener noreferrer"
              onClick={() => onNavigate?.(a.url, a.label)}
              className={`${pillBase} ${cta}`}
              dir={lang === 'he' ? 'rtl' : 'ltr'}
            >
              {navigateIcon(a.url)}
              <span>{a.label}</span>
            </a>
          );
        }

        // type === 'action'
        const target = ACTION_TARGETS[a.action];

        if (target && 'popupId' in target) {
          const popupId = target.popupId;
          return (
            <button
              key={key}
              type="button"
              onClick={() => {
                onAction(a.action, a.label);
                openBricksPopup(popupId);
              }}
              className={`${pillBase} ${cta}`}
              dir={lang === 'he' ? 'rtl' : 'ltr'}
            >
              {a.label}
            </button>
          );
        }

        if (target && 'url' in target) {
          return (
            <a
              key={key}
              href={resolveUrl(target.url, siteUrl)}
              target="_blank"
              rel="noopener noreferrer"
              onClick={() => onAction(a.action, a.label)}
              className={`${pillBase} ${cta}`}
              dir={lang === 'he' ? 'rtl' : 'ltr'}
            >
              {a.label}
            </a>
          );
        }

        return (
          <button
            key={key}
            type="button"
            onClick={() => onAction(a.action, a.label)}
            className={`${pillBase} ${cta}`}
            dir={lang === 'he' ? 'rtl' : 'ltr'}
          >
            {a.label}
          </button>
        );
      })}
    </div>
  );
}
