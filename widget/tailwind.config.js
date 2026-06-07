/** @type {import('tailwindcss').Config} */
export default {
  // Scope every utility class so the widget cannot collide with site CSS.
  important: '#insight-chat-root',
  content: ['./index.html', './src/**/*.{ts,tsx}'],
  // Tailwind preflight emits unscoped global selectors (button, h1-h6, p, a, ul,
  // img, …) that would restyle the host WordPress page. Disabled here; globals.css
  // provides an equivalent reset scoped to #insight-chat-root.
  corePlugins: { preflight: false },
  // The widget mounts inside an element that itself sets dir="rtl"|"ltr",
  // so Tailwind's selectorParent strategy matches naturally.
  theme: {
    extend: {
      colors: {
        ink: {
          50:  '#f6f7f7',
          100: '#e9ebeb',
          200: '#d4d8d8',
          300: '#b0b6b6',
          400: '#838b8b',
          500: '#5c6463',
          600: '#454c4b',
          700: '#343a39',
          800: '#222827',
          900: '#141918',
        },
        // SUGAR360 brand — deep teal primary (#01615F), from the site (--s360-teal / headings).
        brand: {
          50:  '#ecfdf7',
          100: '#d4f8ec',
          200: '#a9ecd6',
          300: '#6fd9bb',
          400: '#34bfa0',
          500: '#0e8a83',
          600: '#01615f',
          700: '#014c4a',
          800: '#013a39',
          900: '#012b2a',
        },
        // SUGAR360 accent — mint (#2CD09D), from the site (--accent / --brand-teal).
        accent: {
          50:  '#ecfdf7',
          100: '#d4f8ec',
          200: '#a7eed5',
          300: '#6fe0bd',
          400: '#2cd09d',
          500: '#25b88a',
          600: '#1f9e76',
          700: '#197d5e',
          800: '#135c45',
          900: '#0c3b2c',
        },
      },
      boxShadow: {
        bubble: '0 1px 2px rgba(15, 23, 42, .04), 0 12px 30px rgba(15, 23, 42, .12)',
        button: '0 8px 28px rgba(1, 97, 95, .35)',
      },
      fontFamily: {
        sans: [
          '"Almoni"',
          '"Heebo"',
          '"Assistant"',
          '"Segoe UI Hebrew"',
          '"Segoe UI"',
          'system-ui',
          '-apple-system',
          'sans-serif',
        ],
      },
      animation: {
        'in-up':  'in-up .25s cubic-bezier(.2,.8,.2,1)',
        'pulse-soft': 'pulse-soft 1.4s ease-in-out infinite',
      },
      keyframes: {
        'in-up': {
          '0%': { opacity: '0', transform: 'translateY(8px)' },
          '100%': { opacity: '1', transform: 'translateY(0)' },
        },
        'pulse-soft': {
          '0%, 100%': { opacity: '0.4' },
          '50%': { opacity: '1' },
        },
      },
    },
  },
  plugins: [],
};
