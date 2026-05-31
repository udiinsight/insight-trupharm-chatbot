import { useEffect, useRef, useState } from 'react';
import type { Lang } from '../types';
import { STRINGS, detectLang } from '../lib/lang';
import { SendIcon } from './icons';

interface Props {
  lang: Lang;
  disabled?: boolean;
  onSend: (text: string) => void;
  onLangChange?: (lang: Lang) => void;
}

const MAX_LEN = 1000;

export function Composer({ lang, disabled, onSend, onLangChange }: Props) {
  const t = STRINGS[lang];
  const [value, setValue] = useState('');
  const ref = useRef<HTMLTextAreaElement | null>(null);

  // Auto-resize the textarea up to ~5 lines.
  useEffect(() => {
    if (!ref.current) return;
    ref.current.style.height = 'auto';
    ref.current.style.height = `${Math.min(ref.current.scrollHeight, 140)}px`;
  }, [value]);

  function submit() {
    const text = value.trim();
    if (!text || disabled) return;
    onSend(text);
    setValue('');
    ref.current?.focus();
  }

  function handleKeyDown(e: React.KeyboardEvent<HTMLTextAreaElement>) {
    if (e.key === 'Enter' && !e.shiftKey && !e.nativeEvent.isComposing) {
      e.preventDefault();
      submit();
    }
  }

  function onChange(e: React.ChangeEvent<HTMLTextAreaElement>) {
    const next = e.target.value.slice(0, MAX_LEN);
    setValue(next);
    if (next.length >= 2) {
      const detected = detectLang(next, lang);
      if (detected !== lang) onLangChange?.(detected);
    }
  }

  return (
    <form
      className="flex items-end gap-2 border-t border-ink-100 bg-white p-3"
      onSubmit={(e) => {
        e.preventDefault();
        submit();
      }}
      dir={lang === 'he' ? 'rtl' : 'ltr'}
    >
      <textarea
        ref={ref}
        value={value}
        onChange={onChange}
        onKeyDown={handleKeyDown}
        rows={1}
        placeholder={t.placeholder}
        aria-label={t.placeholder}
        className="flex-1 resize-none rounded-xl border border-ink-200 bg-white px-3.5 py-2.5 text-[15px] leading-snug text-ink-900 placeholder:text-ink-400 focus:outline-none focus:border-brand-400 focus:ring-2 focus:ring-brand-200 max-h-[140px]"
        dir={lang === 'he' ? 'rtl' : 'ltr'}
      />
      <button
        type="submit"
        disabled={disabled || !value.trim()}
        aria-label={t.send}
        className="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-brand-600 text-white shadow-button transition-opacity hover:bg-brand-700 disabled:opacity-40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-400"
      >
        <SendIcon className="h-4 w-4 rtl:-scale-x-100" />
      </button>
    </form>
  );
}
