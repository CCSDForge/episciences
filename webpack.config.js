const Encore = require('@symfony/webpack-encore');

// Manually configure the runtime environment if not already configured yet by the "encore" command.
// It's useful when you use tools that rely on webpack.config.js file.
if (!Encore.isRuntimeEnvironmentConfigured()) {
    Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

Encore
    // directory where compiled assets will be stored
    .setOutputPath('public/build/')
    // public path used by the web server to access the output path
    .setPublicPath('/build')
    .configureFilenames({
        js: '[name].js',
        css: '[name].css'
    })
    // only needed for CDN's or sub-directory deploy
    //.setManifestKeyPrefix('build/')

    /*
     * ENTRY CONFIG
     *
     * Add 1 entry for each "page" of your app
     * (including one that's included on every page - e.g. "app")
     *
     * Each entry will result in one JavaScript file (e.g. app.js)
     * and one CSS file (e.g. app.css) if your JavaScript imports CSS.
     */
    .addEntry('app', './assets/app.js')
    .addEntry('altcha', './assets/altcha.js')
    // CSS-only entries for vendor libraries only needed on specific pages (not the whole site),
    // loaded on demand via webpackAssets()->queueStylesheet() where used.
    .addEntry('datatables-bootstrap', './assets/datatables-bootstrap.js')
    .addEntry('chartjs', './assets/chartjs.js')
    .addEntry('sortablejs', './assets/sortablejs.js')
    .addEntry('filepond', './assets/filepond.js')
    .addEntry('jquery-ui-theme', './assets/jquery-ui-theme.js')
    //.addEntry('page1', './assets/page1.js')
    //.addEntry('page2', './assets/page2.js')

    // jQuery itself is still loaded globally via CDN (VENDOR_JQUERY, see layout.phtml), not bundled.
    // Any bundled jQuery plugin (Bootstrap JS, DataTables, bootbox) must attach to that same global
    // instance rather than bundling its own private copy of jQuery — otherwise plugins wouldn't be
    // visible to non-bundled inline scripts calling $(...).plugin(). FilePond is standalone and has no
    // such requirement (see assets/filepond.js).
    .addExternals({ jquery: 'jQuery' })

    // When enabled, Webpack "splits" your files into smaller pieces for greater optimization.
    .splitEntryChunks()

    // Layout templates reference each entry's CSS output as a single static file (e.g. /css/main.css,
    // symlinked to build/app.css) rather than through entrypoints.json, so CSS pulled in from
    // node_modules (e.g. Bootstrap) must stay merged into that file instead of being split into its
    // own "vendors-*.css" chunk.
    .configureSplitChunks((splitChunks) => {
        splitChunks.cacheGroups = {
            ...splitChunks.cacheGroups,
            defaultVendors: {
                test: /[\\/]node_modules[\\/].*\.js$/,
                priority: -10,
                reuseExistingChunk: true
            }
        };
    })

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

    // enables @babel/preset-env polyfills
    .configureBabelPresetEnv((config) => {
        config.useBuiltIns = 'usage';
        config.corejs = 3;
    })

    // enables Sass/SCSS support
    .enableSassLoader((options) => {
        options.sassOptions = {
            quietDeps: true,
            verbose: false,
            silenceDeprecations: ["legacy-js-api", "import", "global-builtin"]
        };
    })

// uncomment if you use TypeScript
//.enableTypeScriptLoader()

// uncomment to get integrity="..." attributes on your script & link tags
// requires WebpackEncoreBundle 1.4 or higher
//.enableIntegrityHashes(Encore.isProduction())

// uncomment if you're having problems with a jQuery plugin
//.autoProvidejQuery()

// uncomment if you use API Platform Admin (composer require api-admin)
//.enableReactPreset()
//.addEntry('admin', './assets/admin.js')
;

module.exports = Encore.getWebpackConfig();
