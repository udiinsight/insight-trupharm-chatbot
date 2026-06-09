=== Insight - Trupharm - Chatbot ===
Contributors: insightmarketing
Tags: wordpress, ai, chatbot, ai-engine, hebrew, rtl
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.0.4
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

= 1.0.4 =
* feat: opening disclaimer (פתיח) on the welcome screen — a short AI/medical notice with a "קרא עוד" expander showing the full client-approved text.
* change: remove the source citations ("מקורות") UI from chat replies. The sources field is still returned and logged, but no longer displayed.

= 1.0.3 =
* feat: clickable source citations — each knowledge doc carries its source URL (refUrl), injected into the [Source #N] block so the model links each citation back to the relevant page.

= 1.0.2 =
* style: theme the widget to the SUGAR360 brand — deep-teal primary (#01615F), mint accent (#2CD09D), and the site's Almoni font.

= 1.0.1 =
* fix: exclude the widget bundle + bootstrap from WP Rocket "Delay JavaScript Execution" so the chat button appears on page load (not only after interaction).
* fix: correct the WP Rocket minify/CSS exclusion paths to the plugin's location.

= 1.0.0 =
* Initial release — React widget, document-citation enrichment, rate limiting, event logging, and REST endpoints.
