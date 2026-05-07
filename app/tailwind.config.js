/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./*.php",
    "./includes/*.php",
    "./models/*.php",
    "./classes/*.php",
    "./**/*.php"
  ],
  theme: {
    extend: {
      colors: {
        'rich-black': '#0d1321',
        'prussian-blue': '#1d2d44',
        'paynes-gray': '#3e5c76',
        'silver-lake-blue': '#748cab',
        'eggshell': '#f0ebd8',
        'custom-bg': '#f8f9fa'
      },
      fontFamily: {
        'sans': ['Inter', 'system-ui', 'sans-serif'],
      },
      spacing: {
        '18': '4.5rem',
        '88': '22rem',
      },
      animation: {
        'fade-in': 'fadeIn 0.5s ease-in-out',
        'slide-in': 'slideIn 0.3s ease-out',
      },
      keyframes: {
        fadeIn: {
          '0%': { opacity: '0' },
          '100%': { opacity: '1' },
        },
        slideIn: {
          '0%': { transform: 'translateY(-10px)', opacity: '0' },
          '100%': { transform: 'translateY(0)', opacity: '1' },
        }
      }
    },
  },
  plugins: [
    require('@tailwindcss/forms'),
    require('@tailwindcss/typography'),
  ],
}