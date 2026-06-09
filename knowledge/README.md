# SUGAR360 Knowledge Base

This folder holds the documents the chatbot answers from. The bot uses **only** these documents
(loaded into AI Engine's Knowledge base, backed by Pinecone). It does not read the website live.

## Contents
Twelve customer-facing Hebrew docs (`01`–`12`), synthesized from the client's real source
documents delivered 2026-06-08. One topic per file; the file's `#` title becomes the title the
retrieval layer attaches to the chunk.

- `01-what-is-cgm` · `02-product-and-specs` · `03-installation-setup` · `04-app-readings-alerts`
- `05-safety-indications` · `06-troubleshooting` · `07-daily-life-water-sport`
- `08-orders-shipping` · `09-returns-warranty-replacement` · `10-customer-service-contact`
- `11-about-sugar360` · `12-pricing-and-coverage`

The triage (what was ingested, skipped, excluded, and why) is documented in
[`INGESTION-DECISIONS.md`](./INGESTION-DECISIONS.md). Raw client originals live in the gitignored
`Knowlage source docs/` folder and are **not** committed (public repo).

## How documents are ingested
AI Engine → Settings → Knowledge (or via `cli/` scripts → `rest_do_request POST /mwai/v1/vectors/add`):
1. Ensure the Pinecone embeddings environment is selected.
2. Click **Add** and paste the document title + text (type: manual), or **Build Knowledge → Upload PDF**.
3. Save. AI Engine embeds the content (OpenAI `text-embedding-3-large` @ 1024 dim) and upserts to Pinecone.
4. The chatbot retrieves the most relevant chunks per question.

To update the knowledge base later, edit/add/remove entries in the same Knowledge screen — no developer needed.

## Guardrails baked into the content
- **No coverage/eligibility claims** — coverage/subsidy questions route to the kupah + customer service.
- **No invented contact info, price, or specs** — all values trace to the client's delivered docs.
- Medical safety lives in `05-safety-indications`; the bot's medical safeguards are enforced by the system prompt.

## Client-input templates (these feed the system prompt, NOT the knowledge base)
- `sources-whitelist.md` — approved external URLs the bot may link to.
- `contact-details.md` — phone / hours / contact + purchase URLs.
- `bot-persona.md` — bot name, tone, behavior examples, common objections.
