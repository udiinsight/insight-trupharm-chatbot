# Knowledge Ingestion Decisions

Documents the triage of the client's source documents into the chatbot knowledge base.
Source folder (raw originals, gitignored): `Knowlage source docs/wetransfer__sibionics_gs1-pdf_2026-06-08_1851/` (delivered 2026-06-08).

## Principle
The bot answers **only** from the curated knowledge docs in this folder. We keep the **Israeli, customer-facing** documents authoritative (price, warranty, contact, availability, regulatory). We do **not** ingest research papers, and we do **not** make any coverage/eligibility claims.

## INGESTED (Tier 1 → the 12 clean `NN-*.md` docs in this folder)
Synthesized from these client documents:

| Source document | Used for |
|---|---|
| `שאלות ותשובות נפוצות … 280526.docx` (FAQ, dated 28.05.26) | The richest, most current customer-facing doc. Feeds most docs: what-is-CGM, install, daily life, water/sport, troubleshooting, orders/shipping, returns/warranty, counterfeit detection, contact, GS1 vs GS3. |
| `H4801 … Product Insert (Hebrew) … FINAL_Elran_Hebrew.docx` (official IFU) | Indications, intended users/ages, contraindications (MRI/CT, AID, skin), warnings, storage, regulatory/CE. |
| `H4811 … המדריך לשימוש באפליקציה … .docx` (app + usage manual) | App account/pairing, alert thresholds (urgent-low 55, low 60–100, high 120–400, rapid ±2 mg/dL/min), AGP reports, therapeutic-decision safety, interfering substances (vitamin C, aspirin, anticoagulants, dehydration), troubleshooting table, technical spec. |
| `Introduction_to_SIBIONICS_GS1 … SUGAR360 310526.pptx` (product intro) | Product facts (MARD 8.7%, 40–450 mg/dL range, rt-CGM vs FLASH), **social proof** (active since 2015, 3M+ users, 100+ countries, ~850k sensors in W. Europe 2025), consumer price (~297 ₪ incl. VAT), social channels. |
| `מדריך משתמש מהיר_SIBIONICS_GS1.pdf` (quick-start) | **Image-only PDF (no text layer)** — could not extract text. Its setup content is fully covered by the FAQ + app manual + IFU, so no information lost. (OCR available later if a verbatim copy is needed.) |

Resulting docs: `01-what-is-cgm`, `02-product-and-specs`, `03-installation-setup`, `04-app-readings-alerts`, `05-safety-indications`, `06-troubleshooting`, `07-daily-life-water-sport`, `08-orders-shipping`, `09-returns-warranty-replacement`, `10-customer-service-contact`, `11-about-sugar360`, `12-pricing-and-coverage`.

## SKIPPED (not ingested) — research / clinical papers
The ~18 academic/clinical papers and consensus documents: `1-1`, `1-2`, `1-3`, `2-x`, `3-1`, `5-x`, `6`, `7`, the two AGP "consensus" docs, and `Article list-version date20251121.xlsx`. Reasons: off-topic for customer support, medical-advice risk, and they pollute retrieval. (`3-1` is about insulin nasal drops & post-op delirium — entirely unrelated.) If social proof from the 3 performance studies (`1-1/1-2/1-3`) is ever wanted, add a single short, attributed accuracy snippet — not the full papers.

## EXCLUDED (deliberately kept out)
- `SIBIONICS GS1 22.03.docx` — a **health-basket (סל) submission for 2027 = PENDING**, not approved coverage. The bot must **not** imply customers are covered/eligible. Coverage questions route to the kupah / customer service (see `12-pricing-and-coverage.md`).
- The intro deck's **subsidy table** (slide 3: "Type 1 → 2 sensors/month", etc.) was **not** ingested as fact, for the same reason — eligibility is kupah-specific and changes over time.

## LINK-ONLY (not dumped into the KB)
- `מדיניות פרטיות …` (privacy policy) and `תקנון אתר מאי 2026 …` (site terms): the bot points to the site pages rather than reproducing legal text. (A 1-line returns/warranty summary lives in `09-…`; specifics defer to the site policy.)

## Resolved with client
- **Customer-service phone number.** Two numbers exist: **09-9532100** (site customer-service page + current system prompt) and **03-7436555** (client's new FAQ 28.05.26 + product page). **Udi chose 09-9532100** (2026-06-09) — knowledge docs use it, and it already matches the system prompt's CTA/escalation `tel:`, so no prompt change needed.
- Email **sugar360@trupharm.co.il** is now client-provided and added as a contact channel in `10-customer-service-contact.md` (the prompt previously had no email).
