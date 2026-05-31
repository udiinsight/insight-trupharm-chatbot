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
          50:  '#f5f7fa',
          100: '#e7eaf0',
          200: '#cdd3df',
          300: '#a4adbf',
          400: '#76829c',
          500: '#525e79',
          600: '#3d4865',
          700: '#2c3550',
          800: '#1c2238',
          900: '#0e1322',
        },
        brand: {
          50:  '#E6EEF7',
          100: '#C2D5EB',
          200: '#95B6DC',
          300: '#6995CD',
          400: '#3D74BD',
          500: '#1B5AA8',
          600: '#043A89',
          700: '#03317A',
          800: '#02266B',
          900: '#021A50',
        },
        accent: {
          50:  '#ECFAFE',
          100: '#D7F4FC',
          200: '#B0E8F9',
          300: '#88DCF6',
          400: '#55D3F6',
          500: '#2EC2EE',
          600: '#18A4D2',
          700: '#137FA1',
          800: '#0E5A73',
          900: '#082E3B',
        },
      },
      boxShadow: {
        bubble: '0 1px 2px rgba(15, 23, 42, .04), 0 12px 30px rgba(15, 23, 42, .12)',
        button: '0 8px 28px rgba(46, 123, 255, .35)',
      },
      fontFamily: {
        sans: [
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
