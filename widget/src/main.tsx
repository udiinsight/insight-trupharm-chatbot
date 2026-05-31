import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { App } from './App';
import type { InsightChatBootstrap } from './types';
import './styles/globals.css';

declare global {
  interface Window {
    INSIGHT_CHAT?: Partial<InsightChatBootstrap>;
  }
}

const ROOT_ID = 'insight-chat-root';

function readBootstrap(): InsightChatBootstrap {
  const raw = window.INSIGHT_CHAT ?? {};
  const origin = window.location.origin;
  return {
    chatEndpoint: raw.chatEndpoint ?? `${origin}/wp-json/mwai-ui/v1/chats/submit`,
    apiBase:      raw.apiBase      ?? `${origin}/wp-json/insight-chat/v1`,
    nonce:        raw.nonce        ?? '',
    siteUrl:      raw.siteUrl      ?? origin,
    defaultLang:  raw.defaultLang  ?? 'he',
    botId:        raw.botId        ?? 'default',
    aiAvatar:     raw.aiAvatar     ?? '',
  };
}

function ensureMountNode(): HTMLElement {
  let el = document.getElementById(ROOT_ID);
  if (!el) {
    el = document.createElement('div');
    el.id = ROOT_ID;
    document.body.appendChild(el);
  }
  return el;
}

function boot() {
  const cfg = readBootstrap();
  const root = createRoot(ensureMountNode());
  root.render(
    <StrictMode>
      <App cfg={cfg} />
    </StrictMode>,
  );
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', boot);
} else {
  boot();
}
