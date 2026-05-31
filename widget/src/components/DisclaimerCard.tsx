import type { DisclaimerKind, Lang } from '../types';
import { STRINGS } from '../lib/lang';
import { AlertIcon, InfoIcon } from './icons';

interface Props {
  lang: Lang;
  kind: DisclaimerKind;
}

export function DisclaimerCard({ lang, kind }: Props) {
  if (!kind) return null;

  const t = STRINGS[lang];
  const isEmergency = kind === 'emergency';

  const text =
    kind === 'emergency'
      ? t.disclaimerEmergency
      : kind === 'personal'
      ? t.disclaimerPersonal
      : t.disclaimerGeneral;

  return (
    <div
      role="note"
      aria-live={isEmergency ? 'assertive' : 'polite'}
      className={[
        'mt-3 flex gap-2 rounded-lg border p-3 text-sm leading-relaxed',
        isEmergency
          ? 'border-red-300 bg-red-50 text-red-900'
          : 'border-amber-200 bg-amber-50 text-amber-900',
      ].join(' ')}
    >
      <span aria-hidden className="mt-0.5 shrink-0">
        {isEmergency ? <AlertIcon className="h-4 w-4" /> : <InfoIcon className="h-4 w-4" />}
      </span>
      <span>{text}</span>
    </div>
  );
}
