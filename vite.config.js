import { defineConfig } from 'vite'
import Symfony from '@symfony/reprise/vite'
import tailwindcss from '@tailwindcss/vite'

const ignoredDataTablesTheme = '\0ignored-datatables-theme'

export default defineConfig({
  input: {
    app: './assets/app.js',
    print: './assets/print.js',
  },
  plugins: [
    {
      // @pentiminax/ux-datatables can reference all Bootstrap theme variants.
      // ORéOF only ships the DT and BS5 variants, as with the former Webpack IgnorePlugin.
      name: 'ignore-unused-datatables-bootstrap-themes',
      resolveId(id) {
        if (/^datatables\\.net(?:-[a-z]+)?-bs4?(?:\\/|$)/.test(id)) {
          return ignoredDataTablesTheme
        }
      },
      load(id) {
        if (id === ignoredDataTablesTheme) {
          return 'export default {}'
        }
      },
    },
    tailwindcss(),
    Symfony({
      stimulus: 'assets/controllers.json',
    }),
  ],
})
