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
    // jQuery / jQuery UI: self-hosted via webpack but loaded as plain global <script> tags through
    // VENDOR_JQUERY / VENDOR_JQUERY_UI (see public/const.php and layout.phtml), not queued via
    // webpackAssets()->queueScript(). Each sets/uses window.jQuery so non-bundled inline scripts and
    // ZendX_JQuery_Form_Element widgets keep working against one shared instance — see
    // addExternals() below and assets/jquery.js / assets/jquery-ui.js.
    .addEntry('jquery', './assets/jquery.js')
    .addEntry('jquery-ui', './assets/jquery-ui.js')
    // CSS-only entries for vendor libraries only needed on specific pages (not the whole site),
    // loaded on demand via webpackAssets()->queueStylesheet() where used.
    .addEntry('datatables-bootstrap', './assets/datatables-bootstrap.js')
    .addEntry('chartjs', './assets/chartjs.js')
    .addEntry('sortablejs', './assets/sortablejs.js')
    .addEntry('filepond', './assets/filepond.js')
    .addEntry('jquery-ui-theme', './assets/jquery-ui-theme.js')
    .addEntry('dompurify', './assets/dompurify.js')
    .addEntry('tom-select', './assets/tom-select.js')
    //.addEntry('page1', './assets/page1.js')
    //.addEntry('page2', './assets/page2.js')

    // TinyMCE: self-hosted (previously loaded from cdnjs via VENDOR_TINYMCE, see public/const.php)
    // but NOT bundled as an addEntry() — TinyMCE dynamically fetches its own theme/model/icons/plugins
    // /skin siblings at runtime via plain string concatenation off its own script URL, so it must be
    // copied as-is rather than tree-shaken through webpack's module graph. Only the pieces actually
    // used (theme "silver", model "dom", icons "default", skin "oxide", plugins "link image code
    // fullscreen table" — see Ccsd_Form_Decorator_FormTinymce / FormControls and
    // public/js/tinymce/tinymce_patch.js) are copied, keeping the same on-disk layout TinyMCE expects
    // relative to tinymce.min.js. None of the "to" patterns use [hash] so filenames stay literal —
    // TinyMCE's own loader doesn't consult entrypoints.json/manifest.json to resolve them.
    .copyFiles({
        from: './node_modules/tinymce',
        to: 'tinymce/[name].[ext]',
        pattern: /tinymce\.min\.js$/,
        includeSubdirectories: false
    })
    .copyFiles({
        from: './node_modules/tinymce/themes/silver',
        to: 'tinymce/themes/silver/[name].[ext]',
        pattern: /theme\.min\.js$/
    })
    .copyFiles({
        from: './node_modules/tinymce/models/dom',
        to: 'tinymce/models/dom/[name].[ext]',
        pattern: /model\.min\.js$/
    })
    .copyFiles({
        from: './node_modules/tinymce/icons/default',
        to: 'tinymce/icons/default/[name].[ext]',
        pattern: /icons\.min\.js$/
    })
    .copyFiles({
        from: './node_modules/tinymce/skins/ui/oxide',
        to: 'tinymce/skins/ui/oxide/[name].[ext]',
        pattern: /(skin|content)\.min\.css$/
    })
    .copyFiles({
        from: './node_modules/tinymce/skins/content/default',
        to: 'tinymce/skins/content/default/[name].[ext]',
        pattern: /content\.min\.css$/
    })
    .copyFiles([
        { from: './node_modules/tinymce/plugins/link', to: 'tinymce/plugins/link/[name].[ext]', pattern: /plugin\.min\.js$/ },
        { from: './node_modules/tinymce/plugins/image', to: 'tinymce/plugins/image/[name].[ext]', pattern: /plugin\.min\.js$/ },
        { from: './node_modules/tinymce/plugins/code', to: 'tinymce/plugins/code/[name].[ext]', pattern: /plugin\.min\.js$/ },
        { from: './node_modules/tinymce/plugins/fullscreen', to: 'tinymce/plugins/fullscreen/[name].[ext]', pattern: /plugin\.min\.js$/ },
        { from: './node_modules/tinymce/plugins/table', to: 'tinymce/plugins/table/[name].[ext]', pattern: /plugin\.min\.js$/ }
    ])

    // Any other bundled jQuery plugin (Bootstrap JS, DataTables, bootbox) must attach to the global
    // jQuery instance set up by assets/jquery.js rather than bundling its own private copy — otherwise
    // plugins wouldn't be visible to non-bundled inline scripts calling $(...).plugin(). FilePond is
    // standalone and has no such requirement (see assets/filepond.js).
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
                reuseExistingChunk: true,
                // jquery/jquery-ui are referenced as single static files by VENDOR_JQUERY/VENDOR_JQUERY_UI
                // (see public/const.php, layout.phtml), not through entrypoints.json like other webpack
                // entries — so they must stay self-contained single files instead of being split into a
                // shared vendors chunk that a plain <script src> tag wouldn't know to also load.
                chunks: (chunk) => chunk.name !== 'jquery' && chunk.name !== 'jquery-ui'
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
