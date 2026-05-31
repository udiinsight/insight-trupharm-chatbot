# SUGAR360 Knowledge Base

This folder holds the documents the chatbot answers from. The bot uses **only** these documents
(loaded into AI Engine's Knowledge base, backed by Pinecone). It does not read the website.

## What goes here
- Up to ~30 documents: product catalog, user manuals (quick start, FAQ, troubleshooting),
  technical specs (accuracy, battery, app integration, compatibility), warranty / returns /
  replacement policy, and company information.
- Preferred format: Markdown (.md) or plain text (.txt). Word/PDF are accepted and converted to
  clean text by Insight before ingestion.
- One topic per file. The file's title/heading becomes the citation title the bot shows.

## How documents are ingested (self-service, in WP admin)
AI Engine → Settings → Knowledge:
1. Make sure the Pinecone embeddings environment is selected (see the project plan).
2. Either click **Add** and paste the document title + text (type: manual), or use
   **Build Knowledge → Upload PDF** to chunk and embed a PDF (the filename becomes the chunk title).
3. Save. AI Engine embeds the content (OpenAI text-embedding-3-large) and stores vectors in Pinecone.
4. The chatbot retrieves the most relevant chunks per question and cites them by document title.

To update the knowledge base later, edit/add/remove entries in the same Knowledge screen — no developer needed.

## Folders
- `samples/` — PLACEHOLDER documents used to test the pipeline before the client delivers real docs.
  Replace them with client-approved content; do not ship the samples as-is.

## Client-input templates (these feed the system prompt, NOT the knowledge base)
- `sources-whitelist.md` — approved external URLs the bot may link to.
- `contact-details.md` — WhatsApp / phone / hours / contact + purchase URLs.
- `bot-persona.md` — bot name, tone, behavior examples, common objections.

Fill these in, then mirror the values into `system-prompts/he.md` (replace the `‹…›` tokens) and redeploy the chatbot config.
