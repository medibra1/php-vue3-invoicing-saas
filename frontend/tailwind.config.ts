import type { Config } from 'tailwindcss'

// Custom design token palette (validated with the user — see CLAUDE.md
// "Visual direction"). No raw Tailwind color utility (bg-blue-500,
// text-red-600...) should be used directly in a feature component —
// always through these tokens or the components/ui/ set.
export default {
    darkMode: ['class'],
    content: ['./index.html', './src/**/*.{vue,ts}'],
  theme: {
  	extend: {
  		colors: {
  			primary: {
  				'500': '#185FA5',
  				'600': '#0C447C',
  				DEFAULT: 'hsl(var(--primary))',
  				foreground: 'hsl(var(--primary-foreground))'
  			},
  			success: {
  				'100': '#EAF3DE',
  				'600': '#3B6D11'
  			},
  			warning: {
  				'100': '#FAEEDA',
  				'600': '#854F0B'
  			},
  			danger: {
  				'100': '#FCEBEB',
  				'600': '#A32D2D'
  			},
  			surface: {
  				'0': '#FAFAF8',
  				'1': '#F1EFE8',
  				'2': '#FFFFFF'
  			},
  			background: 'hsl(var(--background))',
  			foreground: 'hsl(var(--foreground))',
  			card: {
  				DEFAULT: 'hsl(var(--card))',
  				foreground: 'hsl(var(--card-foreground))'
  			},
  			popover: {
  				DEFAULT: 'hsl(var(--popover))',
  				foreground: 'hsl(var(--popover-foreground))'
  			},
  			secondary: {
  				DEFAULT: 'hsl(var(--secondary))',
  				foreground: 'hsl(var(--secondary-foreground))'
  			},
  			muted: {
  				DEFAULT: 'hsl(var(--muted))',
  				foreground: 'hsl(var(--muted-foreground))'
  			},
  			accent: {
  				DEFAULT: 'hsl(var(--accent))',
  				foreground: 'hsl(var(--accent-foreground))'
  			},
  			destructive: {
  				DEFAULT: 'hsl(var(--destructive))',
  				foreground: 'hsl(var(--destructive-foreground))'
  			},
  			border: 'hsl(var(--border))',
  			input: 'hsl(var(--input))',
  			ring: 'hsl(var(--ring))',
  			chart: {
  				'1': 'hsl(var(--chart-1))',
  				'2': 'hsl(var(--chart-2))',
  				'3': 'hsl(var(--chart-3))',
  				'4': 'hsl(var(--chart-4))',
  				'5': 'hsl(var(--chart-5))'
  			}
  		},
  		borderRadius: {
  			lg: 'var(--radius)',
  			md: 'calc(var(--radius) - 2px)',
  			sm: 'calc(var(--radius) - 4px)'
  		}
  	}
  },
  plugins: [],
} satisfies Config
