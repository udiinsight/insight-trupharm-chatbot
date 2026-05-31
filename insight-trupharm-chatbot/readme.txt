=== Insight - Trupharm - Chatbot ===
Contributors: insightmarketing
Tags: wordpress, ai, chatbot, ai-engine, hebrew, rtl
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.0.0
License: Proprietary
License URI: https://insight-marketing.co.il

Hebrew RTL AI support chatbot for SUGAR360 (Tropharm), grounded in a curated document knowledge base.

== Description ==

Companion plugin for the SUGAR360 (Tropharm) support chatbot. It renders a custom React widget on the
front end and extends AI Engine Pro with:

* Document-grounded answers (AI Engine Knowledge + Pinecone) that cite the source document titles.
* Structured JSON replies: answer + sources + suggested actions + medical-disclaimer flags.
* Lead routing to existing channels (WhatsApp, phone, contact page) — no in-bot data collection.
* Human-escalation prompts and strict medical safeguards (no diagnosis, dosage, or personal advice).
* Per-IP rate limiting and privacy-first event logging (no message text, no raw IP).

Requires AI Engine (or AI Engine Pro) installed and configured with an Anthropic (Claude) environment
and a Pinecone embeddings environment. All API keys live in AI Engine settings, never in this plugin.

== Changelog ==

= 1.0.0 =
* Initial release — React widget, document-citation enrichment, rate limiting, event logging, and REST endpoints.
