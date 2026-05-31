export type Lang = 'he' | 'en';

export type DisclaimerKind = null | 'general' | 'personal' | 'emergency';

export interface Source {
  title: string;
  url: string;
}

export type SuggestedAction =
  | { type: 'follow_up'; label: string; prompt: string }
  | { type: 'navigate'; label: string; url: string }
  | { type: 'action'; label: string; action: string };

export interface StructuredReply {
  response: string;
  sources: Source[];
  suggested_actions: SuggestedAction[];
  disclaimer_needed: boolean;
  disclaimer_kind: DisclaimerKind;
}

export interface Message {
  id: string;
  role: 'user' | 'assistant';
  /** When role==='assistant', the parsed structured reply (or null while streaming). */
  reply?: StructuredReply | null;
  /** When role==='user', the message text shown in the bubble. */
  text?: string;
  /** Set to true while we await the LLM. */
  pending?: boolean;
  /** A non-empty string when this message represents an error. */
  error?: string;
}

export interface Starter {
  label: string;
  prompt: string;
}

export interface InsightChatBootstrap {
  /** AI Engine submit endpoint, e.g. /wp-json/mwai-ui/v1/chats/submit */
  chatEndpoint: string;
  /** Insight Chat namespace, e.g. /wp-json/insight-chat/v1 */
  apiBase: string;
  /** wp_create_nonce('wp_rest') */
  nonce: string;
  /** Site URL for resolving relative source URLs returned by the LLM. */
  siteUrl: string;
  /** Initial direction. Frontend re-detects from input when needed. */
  defaultLang: Lang;
  /** Bot ID configured on the AI Engine side. */
  botId: string;
  /** AI Engine chatbot avatar URL (from mwai_chatbots[default].aiAvatarUrl). Empty = use fallback icon. */
  aiAvatar: string;
}
