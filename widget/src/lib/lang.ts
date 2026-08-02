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
    error:
      'לא הצלחתי להביא תשובה כרגע. אפשר לנסות לשאול שוב, או לשלוח הודעה לשירות הלקוחות בוואטסאפ 054-5005138 או בטלפון 09-7436555, בימים א׳ עד ה׳ בין 8:00 ל 16:00.',
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
    welcomeBody:
      'אני העוזרת הדיגיטלית של SUGAR360, מערכת הניטור הרציף של הסוכר מבית תרופארם, עם חיישן SIBIONICS GS1.\n\nאפשר לשאול אותי על החיישן והדיוק, התקנה וצימוד לאפליקציה, שימוש יומיומי, מחיר והזמנה, אחריות והחזרות, ותקלות בשימוש.',
    disclaimerMore: 'קרא עוד',
    disclaimerLess: 'הצג פחות',
    whatsappOpener: 'שלום, הגעתי מהצ׳אט באתר SUGAR360. תקציר הפנייה:',
    welcomeDisclaimerShort:
      'לצורך הנגשת מידע אנו נעזרים בבינה מלאכותית. המידע כאן הוא מסכם בלבד ואינו תחליף לייעוץ רפואי.',
    welcomeDisclaimerFull:
      'לצורך הנגשת מידע, אנו נעזרים בבינה מלאכותית מתקדמת.\n\nהמידע בבוט זה הינו מידע מסכם בלבד, המבוסס על עיבוד ואיחוד של מקורות שונים, לרבות אתר החברה ומקורות פומביים, ובכלל זה אתר משרד הבריאות, קופות חולים ועמותות חולים.\n\nחשוב לציין כי המידע המתקבל באמצעות הבוט נועד למטרות מידע והכוונה בלבד, ואינו מהווה תחליף לייעוץ רפואי אישי, אבחון או טיפול על ידי רופא, אחות סוכרת או איש מקצוע מוסמך. בכל שאלה או חשש רפואי, לרבות שינוי טיפול או מצב רפואי חריג, יש לפנות לגורם מוסמך כאמור.\n\nלמען הסר ספק, בשימוש בכלי AI ייתכנו טעויות או מידע שאינו עדכני או מלא, ויש לאמת את המידע מול מקור רשמי, כגון הוראות השימוש למוצר, המדריך למשתמש ו/או גורם רפואי מוסמך.\n\nהשימוש בבוט כפוף לתקנון השימוש באתר, ומהווה אישור כי המשתמש קרא והסכים לתנאיו, לרבות ההתניות והגבלות האחריות המפורטות בו.',
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
    error:
      'I could not get an answer just now. Please try asking again, or message customer service on WhatsApp 054-5005138 or call 09-7436555, Sunday to Thursday 8:00 to 16:00.',
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
    welcomeBody:
      'I am the digital assistant for SUGAR360, the continuous glucose monitoring system by Tropharm, with the SIBIONICS GS1 sensor.\n\nYou can ask about the sensor and its accuracy, installation and pairing, everyday use, price and ordering, warranty and returns, and faults while using the sensor.',
    disclaimerMore: 'Read more',
    disclaimerLess: 'Show less',
    whatsappOpener: 'Hello, I am coming from the SUGAR360 website chat. Conversation summary:',
    welcomeDisclaimerShort:
      'To make information accessible we use AI. The information here is a summary only and does not replace medical advice.',
    welcomeDisclaimerFull:
      'To make information accessible, we use advanced artificial intelligence.\n\nThe information in this bot is a summary only, based on processing and combining various sources, including the company website and public sources such as the Ministry of Health, health funds (kupot cholim), and patient associations.\n\nPlease note that information provided by the bot is intended for information and guidance only, and does not replace personal medical advice, diagnosis, or treatment by a doctor, diabetes nurse, or qualified professional. For any medical question or concern, including a change in treatment or an unusual medical condition, please consult such a qualified provider.\n\nFor the avoidance of doubt, when using AI tools there may be errors or information that is not up to date or complete, and you should verify the information against an official source, such as the product instructions for use, the user guide, and/or a qualified medical provider.\n\nUse of the bot is subject to the website terms of use, and constitutes confirmation that the user has read and agreed to them, including the conditions and limitations of liability detailed therein.',
  },
};
