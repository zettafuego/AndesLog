import 'vuetify/styles'
import '@mdi/font/css/materialdesignicons.css'
import { createVuetify } from 'vuetify'
import { aliases, mdi } from 'vuetify/iconsets/mdi'

export const vuetify = createVuetify({
  icons: { defaultSet: 'mdi', aliases, sets: { mdi } },
  theme: {
    defaultTheme: 'anden',
    themes: {
      anden: {
        dark: true,
        colors: {
          background: '#101114',
          surface: '#171B21',
          primary: '#E8A317',
          secondary: '#8FD0B4',
          error: '#E15B64',
          info: '#7FA8C9',
          success: '#8FD0B4',
          warning: '#E8A317',
          'on-primary': '#1A1406',
          'on-surface': '#E7E4DC',
          'on-background': '#E7E4DC',
        },
      },
    },
  },
  defaults: {
    VBtn: { rounded: 'sm', elevation: 0 },
    VCard: { elevation: 0, rounded: 'lg' },
    VTextField: { variant: 'outlined', density: 'comfortable', hideDetails: 'auto', color: 'primary' },
    VSelect: { variant: 'outlined', density: 'comfortable', hideDetails: 'auto', color: 'primary' },
    VAlert: { variant: 'tonal', density: 'compact' },
  },
})
