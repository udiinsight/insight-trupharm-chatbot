import { useState } from 'react';
import type { InsightChatBootstrap } from './types';
import { dirFor } from './lib/lang';
import { resolveUrl } from './lib/parse';
import { FloatingButton } from './components/FloatingButton';
import { ChatPanel } from './components/ChatPanel';

interface Props {
  cfg: InsightChatBootstrap;
}

const DISMISS_KEY = 'insight-chat-dismissed';

function readDismissed(): boolean {
  try {
    return sessionStorage.getItem(DISMISS_KEY) === '1';
  } catch {
    return false;
  }
}

export function App({ cfg }: Props) {
  const [open, setOpen] = useState(false);
  const [dismissed, setDismissed] = useState<boolean>(readDismissed);

  // Once dismissed, the entire widget disappears for the rest of the session.
  // Comes back on the next visit (sessionStorage clears when the browser/tab closes).
  if (dismissed) return null;

  function handleDismiss() {
    try {
      sessionStorage.setItem(DISMISS_KEY, '1');
    } catch {
      // Ignore storage failures (private mode etc.) — still hide for this view.
    }
    setOpen(false);
    setDismissed(true);
  }

  return (
    <div dir={dirFor(cfg.defaultLang)}>
      <FloatingButton
        lang={cfg.defaultLang}
        open={open}
        onToggle={() => setOpen((v) => !v)}
        onDismiss={handleDismiss}
        avatar={resolveUrl(cfg.aiAvatar, cfg.siteUrl)}
      />
      <ChatPanel cfg={cfg} open={open} onClose={() => setOpen(false)} />
    </div>
  );
}
