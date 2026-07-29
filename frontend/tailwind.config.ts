import type { Config } from 'tailwindcss'

// Custom design token palette (validated with the user — see CLAUDE.md
// "Visual direction"). No raw Tailwind color utility (bg-blue-500,
// text-red-600...) should be used directly in a feature component —
// always through these tokens or the components/ui/ set.
export default {
  content: ['./index.html', './src/**/*.{vue,ts}'],
  theme: {
    extend: {
      colors: {
        primary: { 500: '#185FA5', 600: '#0C447C' },
        success: { 100: '#EAF3DE', 600: '#3B6D11' },
        warning: { 100: '#FAEEDA', 600: '#854F0B' },
        danger: { 100: '#FCEBEB', 600: '#A32D2D' },
        surface: { 0: '#FAFAF8', 1: '#F1EFE8', 2: '#FFFFFF' },
      },
    },
  },
  plugins: [],
} satisfies Config
