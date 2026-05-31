import type { Lang } from '../types';
import { STRINGS } from '../lib/lang';
import { SparkleIcon } from './icons';

interface Props {
  lang: Lang;
  avatar?: string;
}

/** Welcome screen: prominent assistant portrait with a soft accent halo, then a chat-bubble greeting.
 *  Shown only when the conversation is empty. The 4 starter-question buttons render below
 *  this component (in StarterQuestions). */
export function WelcomeIntro({ lang, avatar }: Props) {
  const t = STRINGS[lang];
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
    </div>
  );
}
