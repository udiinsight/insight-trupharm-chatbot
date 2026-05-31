import type { Lang } from '../types';

const HEBREW_RE = /[֐-׿]/;

export function detectLang(text: string, fallback: Lang = 'he'): Lang {
  if (!text) return fallback;
  return HEBREW_RE.test(text) ? 'he' : 'en';
}

export function dirFor(lang: Lang): 'rtl' | 'ltr' {
  return lang === 'he' ? 'rtl' : 'ltr';
}

/** Strings shown in the widget shell. */
export const STRINGS: Record<Lang, Record<string, string>> = {
  he: {
    open: 'פתחו צ׳אט',
    close: 'סגירה',
    dismiss: 'הסתרה',
    callout: 'איך אפשר לעזור?',
    subtitle: 'העוזרת הדיגיטלית של SUGAR360',
    minimize: 'מזעור',
    placeholder: 'כתבו את השאלה שלכם…',
    send: 'שליחה',
    starterTitle: 'שאלות נפוצות:',
    sources: 'מקורות',
    actionRequestCallback: 'תיאום שיחה',
    actionContactClinic: 'יצירת קשר',
    actionOpenDoctorFinder: 'שירות לקוחות',
    error: 'משהו השתבש. אפשר לנסות שוב בעוד רגע.',
    disclaimerGeneral:
      'המידע כאן נועד להכרות כללית עם מערכת SUGAR360 ואינו מחליף ייעוץ רפואי אישי. לפני החלטה הקשורה לבריאותך או לאיזון הסוכרת, התייעצי עם הרופא או צוות הסוכרת המטפל.',
    disclaimerPersonal:
      'התשובה מבוססת על המידע הזמין במאגר ואינה ייעוץ רפואי אישי. כדי לבדוק אם המוצר או הצעד מתאימים למצבך, יש להתייעץ עם הרופא או צוות הסוכרת המטפל.',
    disclaimerEmergency:
      'התסמינים שתיארת דורשים פנייה מיידית לגורם רפואי. שירות SUGAR360 אינו שירות חירום רפואי. פני לרופא או לגורם הרפואי המתאים לך בהקדם.',
    iris: 'נועה',
    you: 'את/ה',
    typing: 'נועה מקלידה…',
    loadingSearching: 'נועה בודקת במאגר…',
    loadingComposing: 'מנסחת תשובה מותאמת…',
    welcomeTitle: 'הי! אני נועה',
    welcomeBody: 'אעזור לכם במידע על SUGAR360: שימוש והתקנה, מפרט, אחריות והזמנה.',
  },
  en: {
    open: 'Open chat',
    close: 'Close',
    dismiss: 'Dismiss',
    callout: 'How can I help?',
    subtitle: 'SUGAR360 digital assistant',
    minimize: 'Minimize',
    placeholder: 'Type a question…',
    send: 'Send',
    starterTitle: 'Common questions:',
    sources: 'Sources',
    actionRequestCallback: 'Request a call',
    actionContactClinic: 'Contact us',
    actionOpenDoctorFinder: 'Customer service',
    error: 'Something went wrong. Please try again in a moment.',
    disclaimerGeneral:
      'This information is for general orientation about SUGAR360 and does not replace personal medical advice. Before any decision related to your health or diabetes management, consult your doctor or diabetes care team.',
    disclaimerPersonal:
      'This answer is based on the available documentation and is not personal medical advice. To find out whether the product or step is right for your situation, please consult your doctor or diabetes care team.',
    disclaimerEmergency:
      'The symptoms you described require immediate attention from a medical provider. SUGAR360 support is not a medical emergency service. Please contact your doctor or the appropriate medical services right away.',
    iris: 'Noa',
    you: 'You',
    typing: 'Noa is typing…',
    loadingSearching: 'Noa is checking the knowledge base…',
    loadingComposing: 'Composing a tailored answer…',
    welcomeTitle: "Hi! I'm Noa",
    welcomeBody: 'I can help with SUGAR360: setup and use, specs, warranty, and ordering.',
  },
};
