# Approved Links Whitelist

Per the client's instruction, the bot links **only** to SUGAR360's own pages (sugar360.co.il). No external sites are linked unless added here later. Mirror this list into the whitelist section of `system-prompts/he.md`.

| Page | URL | Use |
|---|---|---|
| Home | https://sugar360.co.il/ | general intro |
| Product / purchase (SIBIONICS GS1) | https://sugar360.co.il/product/sibionics-gs1/ | product info, specs, price, ordering |
| About | https://sugar360.co.il/about/ | company / Tropharm |
| FAQ | https://sugar360.co.il/faq/ | common questions |
| Customer service / contact | https://sugar360.co.il/customer-service/ | contact, hours, shipping/returns/warranty |

Rule: if no approved page fits the question, the bot does not invent a link — it suggests phoning customer service or using the contact page. For medical questions it refers the visitor to their doctor / diabetes care team.

## Citation mapping (`refUrl` per knowledge doc)

Every vector carries a `refUrl`; AI Engine surfaces it as the `URL` field of the `[Source #N]` block, and the prompt copies it into `sources[].url` so the widget renders a clickable citation. **A re-ingest must re-apply this** — `refUrl` is not derived from the file, and an empty value silently downgrades citations to plain text (that was the state on live until 2026-08-02).

| Knowledge doc | refUrl |
|---|---|
| `01-what-is-cgm`, `02-product-and-specs`, `05-safety-indications`, `12-pricing-and-coverage` | https://sugar360.co.il/product/sibionics-gs1/ |
| `03-installation-setup`, `04-app-readings-alerts`, `06-troubleshooting`, `07-daily-life-water-sport` | https://sugar360.co.il/faq/ |
| `08-orders-shipping`, `09-returns-warranty-replacement`, `10-customer-service-contact` | https://sugar360.co.il/customer-service/ |
| `11-about-sugar360` | https://sugar360.co.il/about/ |
