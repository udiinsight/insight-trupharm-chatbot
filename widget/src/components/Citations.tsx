import type { Lang, Source } from '../types';
import { STRINGS } from '../lib/lang';
import { resolveUrl } from '../lib/parse';
import { ExternalIcon, LinkIcon } from './icons';

interface Props {
  lang: Lang;
  sources: Source[];
  siteUrl: string;
  onClick?: (source: Source) => void;
}

export function Citations({ lang, sources, siteUrl, onClick }: Props) {
  if (!sources?.length) return null;
  return (
    <div className="mt-3">
      <div className="mb-1.5 inline-flex items-center gap-1.5 text-xs font-medium text-brand-700">
        <LinkIcon className="h-3.5 w-3.5" />
        <span>{STRINGS[lang].sources}</span>
      </div>
      <ul className="flex flex-wrap gap-1.5">
        {sources.slice(0, 3).map((src, i) => {
          const label = src.title || src.url;
          const hasUrl = !!src.url && src.url.trim() !== '';
          const chipClass =
            'inline-flex max-w-full items-center gap-1.5 rounded-full border border-accent-200 bg-accent-50 px-3 py-1 text-xs font-medium text-brand-700';
          if (!hasUrl) {
            // Document sources have no public URL — show the title as a non-clickable chip.
            return (
              <li key={`src-${i}`}>
                <span className={chipClass}>
                  <span className="truncate">{label}</span>
                </span>
              </li>
            );
          }
          return (
            <li key={`${src.url}-${i}`}>
              <a
                href={resolveUrl(src.url, siteUrl)}
                target="_blank"
                rel="noopener noreferrer"
                onClick={() => onClick?.(src)}
                className={`${chipClass} hover:border-accent-400 hover:bg-accent-100 hover:text-brand-800 transition-colors`}
              >
                <span className="truncate">{label}</span>
                <ExternalIcon className="h-3 w-3 shrink-0 text-accent-600" />
              </a>
            </li>
          );
        })}
      </ul>
    </div>
  );
}
