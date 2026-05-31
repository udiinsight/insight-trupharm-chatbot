import { useEffect, useState } from 'react';
import type { Lang } from '../types';
import { STRINGS } from '../lib/lang';

interface Props {
  lang: Lang;
}

/** Pre-streaming loading bubble. Stages cycle automatically so a slow first-token wait
 *  feels purposeful instead of frozen:
 *    0–2s  → "Iris is checking sources…"
 *    2–6s  → "Composing a tailored answer…"
 *    6s+   → just the dots (typing label remains as aria-label only). */
export function LoadingStatus({ lang }: Props) {
  const t = STRINGS[lang];
  const [stage, setStage] = useState(0);

  useEffect(() => {
    const t1 = setTimeout(() => setStage(1), 2000);
    const t2 = setTimeout(() => setStage(2), 6000);
    return () => {
      clearTimeout(t1);
      clearTimeout(t2);
    };
  }, []);

  const label =
    stage === 0 ? t.loadingSearching : stage === 1 ? t.loadingComposing : '';

  return (
    <div className="flex ltr:justify-start rtl:justify-end">
      <div className="rounded-2xl rounded-bl-md border border-accent-100 bg-white px-3.5 py-2.5 shadow-sm">
        <div className="flex items-center gap-2 text-ink-700">
          <span
            className="inline-flex items-center gap-1"
            aria-label={t.typing}
            role="status"
          >
            <span
              className="h-1.5 w-1.5 rounded-full bg-brand-400 animate-pulse-soft"
              style={{ animationDelay: '0ms' }}
            />
            <span
              className="h-1.5 w-1.5 rounded-full bg-brand-400 animate-pulse-soft"
              style={{ animationDelay: '180ms' }}
            />
            <span
              className="h-1.5 w-1.5 rounded-full bg-brand-400 animate-pulse-soft"
              style={{ animationDelay: '360ms' }}
            />
          </span>
          {label && <span className="text-sm text-ink-600">{label}</span>}
        </div>
      </div>
    </div>
  );
}
