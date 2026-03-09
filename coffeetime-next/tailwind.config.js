/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./app/**/*.{js,jsx,ts,tsx}",
    "./components/**/*.{js,jsx,ts,tsx}",
    "./lib/**/*.{js,jsx,ts,tsx}",
  ],
  theme: {
    extend: {
      colors: {
        // Brand palette – matches PHP CSS variables
        header: "rgba(151,86,39,0.85)",
        accent: "#ffa500",
        footer: "#8b4513",
        dark: "#333333",
        light: "#f5f5f5",
      },
      fontFamily: {
        sans: ["Arial", "sans-serif"],
      },
      maxWidth: {
        site: "1200px",
      },
      backdropBlur: {
        header: "8px",
      },
    },
  },
  plugins: [],
};
