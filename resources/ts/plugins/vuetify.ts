import {createVuetify, type ThemeDefinition} from "vuetify"
import {aliases, md} from 'vuetify/iconsets/md'
import 'material-design-icons-iconfont/dist/material-design-icons.css'
import {VBtn} from 'vuetify/components/VBtn'
import {VMaskInput} from 'vuetify/labs/VMaskInput'

const lightTheme: ThemeDefinition = {
  dark: false,
  colors: {
    // APP COLORS
    primary: "#3066DA",
    'on-primary': "#FFFFFF",
    secondary: "#1D3E85",
    'on-secondary': "#FFFFFF",
    tertiary: "#495057",
    application: "#EFEFEF",

    // STANDARD COLORS
    accent: "#82B1FF",
    error: "#f55a4e",
    info: "#00d3ee",
    success: "#5cb860",
    warning: "#ffa21a",
  },
  variables: {}
}

export default createVuetify({
  components: {
    VMaskInput
  },
  theme: {
    defaultTheme: 'lightTheme',
    themes: {
      lightTheme
    }
  },
  // Don't forget to update shims-vue.d.ts when adding new aliases!
  aliases: {
    VBtnPrimary: VBtn,
    VBtnSecondary: VBtn,
    VBtnTertiary: VBtn,
  },
  icons: {
    defaultSet: 'md',
    aliases,
    sets: {
      md,
    },
  },
  defaults: {
    VBtnPrimary: {
      color: "primary",
      rounded: "lg",
      size: "large",
      variant: "flat",
    },
    VBtnSecondary: {
      color: "secondary",
      size: "large",
      variant: "outlined",
      rounded: 'lg',
      border: 'primary',
      style: 'border: thin solid rgb(var(--v-border-color));',
    },
    VBtnTertiary: {
      color: "secondary",
      size: "large",
      variant: "text",
      rounded: 'lg',
    },
    VTextField: {
      counter: 255,
      rules: [
        (value: string) => (String(value) ? String(value).length : 0) <= 255 || 'Max 255 characters'
      ]
    },
  },
  date: {
    formats: {
      date: "MM/DD/YYYY",
      datetime: "MM/DD/YYYY HH:mm",
      time: "HH:mm",
    },
  },
})
