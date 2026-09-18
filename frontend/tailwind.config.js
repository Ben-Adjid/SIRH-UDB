/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      colors: {
        brand: {
          50: '#eff6ff',
          100: '#dbeafe',
          200: '#bfdbfe',
          300: '#93c5fd',
          400: '#60a5fa',
          500: '#2563eb',
          600: '#1d4ed8',
          700: '#1e40af',
          800: '#1e3a8a',
          900: '#172554',
        },
        surface: {
          50: '#f8fafc',
          100: '#f1f5f9',
          200: '#e2e8f0',
          300: '#cbd5e1',
          400: '#94a3b8',
          500: '#64748b',
          600: '#475569',
          700: '#334155',
          800: '#1e293b',
          900: '#0f172a',
        }
      },
      fontFamily: {
        sans: ['Outfit', 'sans-serif'],
        mono: ['JetBrains Mono', 'monospace'],
      },
      borderRadius: {
        '2xl': '1rem',
        '3xl': '1.5rem',
        '4xl': '2rem',
        '5xl': '2.5rem',
      },
      animation: {
        'fade-in': 'fadeInUp 0.5s cubic-bezier(0.16,1,0.3,1) forwards',
        'slide-in': 'fadeInLeft 0.4s cubic-bezier(0.16,1,0.3,1) forwards',
        'scale-in': 'scaleIn 0.35s cubic-bezier(0.34,1.56,0.64,1) forwards',
        'premium': 'fadeInUp 0.6s cubic-bezier(0.16,1,0.3,1) forwards',
        'shimmer': 'shimmer 1.5s infinite',
        'pulse-ring': 'pulseRing 1.5s infinite',
      },
      boxShadow: {
        'premium': '0 20px 25px -5px rgba(0,0,0,0.05), 0 10px 10px -5px rgba(0,0,0,0.02)',
        'card': '0 4px 24px -4px rgba(15,23,42,0.08), 0 1px 2px rgba(15,23,42,0.04)',
        'glow': '0 0 48px -8px rgba(37,99,235,0.12)',
      },
    },
  },
  plugins: [],
}
