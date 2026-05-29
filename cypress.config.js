const { defineConfig } = require('cypress')
require('dotenv').config({ path: '.env.local' })

module.exports = defineConfig({
  e2e: {
    baseUrl: 'https://oreof:8890/index.php/',
    env: {
      username: process.env.CYPRESS_USERNAME,
      password: process.env.CYPRESS_PASSWORD,
    },
  },
})
