import { useState } from 'react';
import type { Lang } from '../types';
import { STRINGS } from '../lib/lang';
import { SparkleIcon, InfoIcon } from './icons';

interface Props {
  lang: Lang;
  avatar?: string;
}

/** Welcome screen: prominent assistant portrait with a soft accent halo, then a chat-bubble greeting,
 *  followed by an always-on AI/medical disclaimer with a "read more" expander.
 *  Shown only when the conversation is empty. The 4 starter-question buttons render below
 *  this component (in StarterQuestions). */
export function WelcomeIntro({ lang, avatar }: Props) {
  const t = STRINGS[lang];
  const [expanded, setExpanded] = useState(false);
  return (
    <div className="px-4 pt-6 pb-2 flex flex-col items-center text-center gap-3">
      <div className="relative">
        <div
          aria-hidden
          className="absolute inset-0 rounded-full bg-accent-200 blur-2xl opacity-60 scale-110"
        />
        {avatar ? (
          <img
            src={avatar}
            alt={t.iris}
            className="relative h-24 w-24 rounded-full object-cover ring-4 ring-white shadow-lg"
          />
        ) : (
          <div className="relative h-24 w-24 rounded-full bg-brand-100 grid place-items-center ring-4 ring-white shadow-lg">
            <SparkleIcon className="h-10 w-10 text-brand-600" />
          </div>
        )}
      </div>
      <div className="rounded-2xl rounded-bl-md bg-white border border-accent-100 px-4 py-3 shadow-sm max-w-[85%]">
        <div className="font-semibold text-ink-900">{t.welcomeTitle}</div>
        <div className="mt-1 text-sm text-ink-700 leading-relaxed">{t.welcomeBody}</div>
      </div>

      <div className="w-[92%] rounded-xl border border-amber-200 bg-amber-50/70 px-3.5 py-3 text-start">
        <div className="flex gap-2">
          <span aria-hidden className="mt-0.5 shrink-0 text-amber-700">
            <InfoIcon className="h-4 w-4" />
          </span>
          <div className="text-xs leading-relaxed text-amber-900">
            {expanded ? (
              <span className="whitespace-pre-line">{t.welcomeDisclaimerFull}</span>
            ) : (
              <span>{t.welcomeDisclaimerShort}</span>
            )}
            <button
              type="button"
              onClick={() => setExpanded((v) => !v)}
              aria-expanded={expanded}
              className="ms-1 font-semibold text-brand-700 underline underline-offset-2 hover:text-brand-800"
            >
              {expanded ? t.disclaimerLess : t.disclaimerMore}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
