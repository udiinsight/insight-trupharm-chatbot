const KEY = 'insight_chat_session_id';

/** Random ASCII alphanum, 32 chars. */
function randomId(): string {
  const bytes = new Uint8Array(16);
  crypto.getRandomValues(bytes);
  return Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('');
}

/** A fresh chat/discussion id, generated client-side per conversation.
 *  AI Engine treats the client as the owner of the conversation state: it does NOT
 *  return a chatId on submit, so without this every message would open a new
 *  discussion server-side. */
export function newChatId(): string {
  return randomId();
}

/** A session ID lives in localStorage so it survives the same browser session
 *  even if the visitor closes and reopens the widget. We never PII-hash here —
 *  the backend will salt+hash on its end. */
export function getSessionId(): string {
  try {
    const existing = localStorage.getItem(KEY);
    if (existing && /^[a-zA-Z0-9]{8,64}$/.test(existing)) return existing;
    const fresh = randomId();
    localStorage.setItem(KEY, fresh);
    return fresh;
  } catch {
    // Privacy mode / disabled storage: fall back to a per-load random id.
    return randomId();
  }
}
