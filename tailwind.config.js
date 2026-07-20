/** Tailwind config — E.Comercial (tokens de marca) */
export default {
  content: [
    "./resources/**/*.blade.php",
    "./resources/**/*.js",
    "./app/Livewire/**/*.php",
  ],
  theme: {
    extend: {
      colors: {
        brand:    { DEFAULT: "#EC6A19", dark: "#C85610", soft: "#FFF1E8" },
        graphite: { DEFAULT: "#6F6F6E", dark: "#4A4A49" },
        anthracite: "#232323",
        canvas: "#F5F5F3",
        ink: "#1F2937",
        muted: "#8A8A89",
        kpiBlue:  { bg: "#EAEFF5", fg: "#3F5972" },
        kpiRed:   { bg: "#FDECEC", fg: "#C8443E" },
        kpiBeige: { bg: "#F6EEE1", fg: "#9A6B38" },
        success: "#2E9E5B",
        danger: "#DC2626",
      },
      fontFamily: { sans: ["Montserrat", "ui-sans-serif", "system-ui", "sans-serif"] },
      boxShadow: {
        card: "0 1px 2px rgba(16,24,40,.04), 0 4px 16px rgba(16,24,40,.06)",
        soft: "0 1px 3px rgba(16,24,40,.08)",
      },
      borderRadius: { xl: "14px", "2xl": "18px" },
    },
  },
  plugins: [require("@tailwindcss/forms")],
};
