import type { Lang, Starter } from '../types';
import { STRINGS } from '../lib/lang';
import { SparkleIcon } from './icons';

interface Props {
  lang: Lang;
  starters: Starter[];
  onPick: (starter: Starter) => void;
}

export function StarterQuestions({ lang, starters, onPick }: Props) {
  if (!starters?.length) return null;
  const t = STRINGS[lang];

  return (
    <div className="px-4 pb-4">
      <div className="mb-2 flex items-center gap-1.5 text-xs uppercase tracking-wide text-ink-400">
        <SparkleIcon className="h-3.5 w-3.5" />
        <span>{t.starterTitle}</span>
      </div>
      <div className="grid gap-2">
        {starters.map((s, i) => (
          <button
            key={`${i}-${s.label}`}
            type="button"
            onClick={() => onPick(s)}
            className="w-full rounded-xl border border-ink-100 bg-white px-3 py-2 text-start text-sm text-ink-800 hover:border-brand-300 hover:bg-brand-50 hover:text-brand-700 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-400 shadow-sm"
            dir={lang === 'he' ? 'rtl' : 'ltr'}
          >
            {s.label}
          </button>
        ))}
      </div>
    </div>
  );
}
