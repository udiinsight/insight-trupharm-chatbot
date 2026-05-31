# Contact Details (client input)

Fill these in, then replace the matching `‹…›` tokens in `system-prompts/he.md` and redeploy.

- Business WhatsApp number (international format, e.g. 9725XXXXXXXX): `‹WHATSAPP_E164›`
  → becomes `https://api.whatsapp.com/send?phone=‹WHATSAPP_E164›`
- Customer-service phone (for `tel:`): `‹SUPPORT_PHONE›`
- Service hours: `‹SERVICE_HOURS›`
- Contact page URL: `‹CONTACT_URL›`
- Purchase / order page URL: `‹PURCHASE_URL›`

Note: the bot never collects personal data — it only routes visitors to these channels.
