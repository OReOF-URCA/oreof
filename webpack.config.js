const Encore = require('@symfony/webpack-encore')
const webpack = require('webpack')

require('dotenv').config() // line to add

// Manually configure the runtime environment if not already configured yet by the "encore" command.
// It's useful when you use tools that rely on webpack.config.js file.
if (!Encore.isRuntimeEnvironmentConfigured()) {
  Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev')
}

Encore
  // directory where compiled assets will be stored
  .setOutputPath('public/build/')
  // public path used by the web server to access the output path
  .setPublicPath('/build')
  // only needed for CDN's or subdirectory deploy
  // .setManifestKeyPrefix('build/')

  /*
       * ENTRY CONFIG
       *
       * Each entry will result in one JavaScript file (e.g. app.js)
       * and one CSS file (e.g. app.css) if your JavaScript imports CSS.
       */
  .addEntry('app', './assets/app.js')
  .addEntry('print', './assets/print.js')

  // enables the Symfony UX Stimulus bridge (used in assets/bootstrap.js)
  .enableStimulusBridge('./assets/controllers.json')

  // When enabled, Webpack "splits" your files into smaller pieces for greater optimization.
  .splitEntryChunks()

  // will require an extra script tag for runtime.js
  // but, you probably want this, unless you're building a single-page app
  .enableSingleRuntimeChunk()

  /*
       * FEATURE CONFIG
       *
       * Enable & configure other features below. For a full
       * list of features, see:
       * https://symfony.com/doc/current/frontend.html#adding-more-features
       */
  .cleanupOutputBeforeBuild()
  .enableBuildNotifications()
  .enableSourceMaps(!Encore.isProduction())
  // enables hashed filenames (e.g. app.abc123.css)
  .enableVersioning(Encore.isProduction())
  .enablePostCssLoader()

  // configure Babel
  // .configureBabel((config) => {
  //     config.plugins.push('@babel/a-babel-plugin');
  // })

  // enables and configure @babel/preset-env polyfills
  .configureBabelPresetEnv((config) => {
    config.useBuiltIns = 'usage'
    config.corejs = '3.23'
  })

  // enables Sass/SCSS support
  .enableSassLoader()

  // @pentiminax/ux-datatables (vendor/pentiminax/ux-datatables) importe dynamiquement, pour
  // chaque extension DataTables, les 4 variantes possibles (dt/bs/bs4/bs5) même si une seule est
  // réellement utilisée à l'exécution. Le projet est passé full Tailwind (plus de Bootstrap) et
  // n'installe que les paquets `-dt` et `-bs5` : sans cet IgnorePlugin, webpack échoue sur les
  // imports `datatables.net(-*)?-bs` / `-bs4` manquants et n'émet plus aucun asset.
  .addPlugin(new webpack.IgnorePlugin({
    resourceRegExp: /^datatables\.net(-[a-z]+)?-bs4?(\/|$)/,
  }))

// uncomment if you use TypeScript
// .enableTypeScriptLoader()

// uncomment if you use React
// .enableReactPreset()

// uncomment to get integrity="..." attributes on your script & link tags
// requires WebpackEncoreBundle 1.4 or higher
// .enableIntegrityHashes(Encore.isProduction())

// uncomment if you're having problems with a jQuery plugin
// .autoProvidejQuery()

module.exports = Encore.getWebpackConfig()
