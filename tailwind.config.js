/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './app/views/**/*.php',
  ],
  theme: {
    extend: {
      colors: {
        ctgreen: '#10E36B',
        ctdark: '#057038',
        ctpblue: '#00222C',
      },
      fontFamily: {
        sans: ['Montserrat','system-ui','-apple-system','sans-serif']
      }
    },
  },
  plugins: [],
}

