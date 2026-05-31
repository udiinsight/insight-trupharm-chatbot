# Disclaimer Strings — SUGAR360 (Tropharm)

> Frontend-rendered strings shown when the LLM response sets `disclaimer_needed: true`.
> The widget (`DisclaimerCard.tsx`) chooses the variant from `disclaimer_kind`.
> Keep these short. They appear in a subtle highlighted card under the bot reply.
> The Hebrew/English text below is the source of truth — mirror any change into `DisclaimerCard.tsx`.

---

## A. כללי · General medical info disclaimer

Trigger: `disclaimer_kind: "general"` — a general medical/health question not tied to the individual.

**Hebrew**
```
המידע כאן נועד להכרות כללית עם מערכת SUGAR360 ואינו מחליף ייעוץ רפואי אישי. לפני החלטה הקשורה לבריאותך או לאיזון הסוכרת, התייעצי עם הרופא או צוות הסוכרת המטפל.
```

**English**
```
This information is for general orientation about SUGAR360 and does not replace personal medical advice. Before any decision related to your health or diabetes management, consult your doctor or diabetes care team.
```

---

## B. ייעוץ אישי · "Consult your care team" variant

Trigger: `disclaimer_kind: "personal"` — the visitor described their own glucose values, condition, medication, pregnancy, or asked whether the product/step is right for them.

**Hebrew**
```
התשובה מבוססת על המידע הזמין במאגר ואינה ייעוץ רפואי אישי. כדי לבדוק אם המוצר או הצעד מתאימים למצבך, יש להתייעץ עם הרופא או צוות הסוכרת המטפל.
```

**English**
```
This answer is based on the available documentation and is not personal medical advice. To find out whether the product or step is right for your situation, please consult your doctor or diabetes care team.
```

---

## C. חירום · Emergency note

Trigger: `disclaimer_kind: "emergency"` — the LLM detected an acute or dangerous medical situation (severe hypo/hyperglycemia symptoms, loss of consciousness, etc.).

By policy the bot does not interpret glucose values, does not advise treatment, and does not reassure about danger. It states it is not a medical provider and that SUGAR360 support is not an emergency service, and asks the visitor to contact their doctor or the appropriate medical services immediately. **The exact emergency wording is subject to the client's medical/legal review.**

**Hebrew**
```
התסמינים שתיארת דורשים פנייה מיידית לגורם רפואי. שירות SUGAR360 אינו שירות חירום רפואי. פני לרופא או לגורם הרפואי המתאים לך בהקדם.
```

**English**
```
The symptoms you described require immediate attention from a medical provider. SUGAR360 support is not a medical emergency service. Please contact your doctor or the appropriate medical services right away.
```

---

## How the model surfaces these

The system prompt returns `disclaimer_needed` + `disclaimer_kind`:

```json
{
  "response": "...",
  "sources": [...],
  "suggested_actions": [...],
  "disclaimer_needed": true,
  "disclaimer_kind": "personal" | "general" | "emergency"
}
```

When `disclaimer_needed` is `false`, `disclaimer_kind` is `null`. When `true`, it is exactly one of `general` / `personal` / `emergency`. The widget falls back to "general" if the kind is missing.
