# Tropharm AI Chatbot — Project Requirements Summary

## Overview
WordPress-based AI chatbot for SUGAR360 e-commerce site, providing automated Hebrew-language support 24/7 based on a curated knowledge base. Built on AI Engine Pro plugin with custom UI.

## Goals
1. **Reduce support load** — handle FAQs, technical/marketing info, escalate when needed
2. **Drive conversions** — guide users through the sales funnel respectfully
3. **Direct lead capture** — route users to existing contact channels (no in-bot form collection)

## Core Functionality

**Language & UI**
- Full Hebrew with RTL support
- Custom-coded UI matching Tropharm brand (not default AI Engine widget)
- Mobile + desktop responsive
- Personalized greeting + suggested FAQ prompts
- Session context retention (memory within conversation)

**Knowledge & Responses**
- Document-based answers only (no hallucinations)
- Up to 30 initial documents (product catalog, guides, FAQs, technical specs)
- Source citation instead of recommendations for medical questions
- Client can self-update knowledge base via WordPress admin

**Medical Safeguards (Critical)**
- No personal medical advice
- No recommendations on medications, nutrition, or lifestyle changes
- No promises of refunds, free sensors, or service guarantees outside Tropharm's control
- No diagnosis or symptom interpretation

**External References**
- Whitelist of pre-approved authoritative sources only (Ministry of Health, Israeli Diabetes Association, health funds)
- Bot never links to unapproved sources
- Client responsible for maintaining the whitelist

**Lead Routing (not collection)**
- Bot identifies contact intent and displays CTA buttons:
  - WhatsApp link
  - Phone number
  - Contact page link
- No personal data collected within the bot itself

**Human Escalation**
- Triggered by: user frustration, off-scope questions, direct requests for an agent, or forbidden medical questions
- Bot displays Tropharm's existing service channels (WhatsApp, phone, hours)
- **Not** a live handoff (live agent integration is out of scope — would require future upgrade)

**Conversational Strategy** (included as core feature, not paid add-on)
- Persona detection: identify whether user is curious, considering, or ready to purchase
- Objection handling with information-based responses + Social Proof (accuracy data, user counts, studies, regulatory approvals)
- Dynamic CTAs adapted to journey stage

**Security & Performance**
- Rate limiting to prevent abuse
- Choice of Claude / GPT model (quality vs. cost balance)

## Admin Interface
- Document upload and management
- Conversation history viewing
- Usage statistics
- Self-service knowledge updates (no developer required)

## Content to be Provided by Client

**Knowledge base documents** (up to 30, preferred format: .md or .txt; Word/PDF accepted and converted by Insight):
- Product catalog
- User manuals (Quick Start, FAQ, Troubleshooting)
- Technical specs (accuracy, battery, app integration, compatibility)
- Warranty, returns, replacement policies
- Company information

**Approved sources whitelist** — list of official URLs the bot may reference

**Contact details** — business WhatsApp number, customer service phone, operating hours, contact page URL, purchase/order page URL

**Bot personality** — brand tone, optional bot name, 3–5 examples of desired bot behavior, list of common customer objections

**Design assets** — Tropharm / SUGAR 360 logo, brand colors and fonts (or style guide)

## Out of Scope
- Live agent handoff within the chat widget (future upgrade)
- In-bot form/lead capture (replaced by routing to existing channels)
- Personal medical advice (explicitly forbidden by design)

## Pricing Structure
- **One-time setup:** ₪8,500 (split 50/50 across signing and go-live)
- **Recurring:** AI Engine Pro license $59/year + token usage billed directly by Anthropic/OpenAI to client (~₪30–90/month estimated)
- Includes 1 month of post-launch bug fixes and adjustments

## Project Constraints
- Token costs billed directly to client's Anthropic/OpenAI account (not through Insight)
- Knowledge base accuracy depends on quality of source documents provided
- Bot personality and Social Proof effectiveness depend on client providing accurate, verified data (no fabrication permitted)
- Significant scope changes after approval will be re-quoted
