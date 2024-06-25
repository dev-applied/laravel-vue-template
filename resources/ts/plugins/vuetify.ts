import { createVuetify, type ThemeDefinition } from "vuetify"
import '@mdi/font/css/materialdesignicons.css'

const lightTheme: ThemeDefinition = {
  dark: false,
  colors: {
    primary: "#502075",
    secondary: "#ffc941",
    tertiary: "#495057",
    accent: "#82B1FF",
    error: "#f55a4e",
    info: "#00d3ee",
    success: "#5cb860",
    warning: "#ffa21a",
    application: "#EFEFEF"
  },
  variables: {

  }
}

export default createVuetify({
  theme: {
    defaultTheme: 'lightTheme',
    themes: {
      lightTheme
    }
  },
  icons: {
    defaultSet: "mdi",
  }
})
