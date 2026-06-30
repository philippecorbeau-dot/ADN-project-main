const Encore = require('@symfony/webpack-encore');

if (!Encore.isRuntimeEnvironmentConfigured()) {
    Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

Encore
    .setOutputPath('public/build/')
    .setPublicPath('/build')

    // Compiler Tailwind CSS
    .addEntry('app', './assets/input.css')
    
    // Admin moderne bundle
    .addEntry('admin_modern', './assets/admin_modern.js')
    
    // Dashboard utilisateur bundle
    .addEntry('user_dashboard', './assets/user_dashboard.js')
    
    // Homepage moderne bundle
    .addEntry('modern_homepage', './assets/modern-homepage.js')
    // Perf helpers
    .addEntry('perf', './assets/perf.js')
    // Header mobile minimal
    .addEntry('header_mobile', './assets/header-mobile.js')

    // Copie des images statiques (logos, favicons, illustrations)
    // On conserve la copie des images (logos, favicons, illustrations) encore référencées par le site.
    .copyFiles({
        from: './assets/theme/images',
        pattern: /\.(png|jpg|jpeg|svg|gif|ico)$/,
        to: 'theme/images/[path][name].[ext]'
    })
    
    // Copier les CSS modernes
    .copyFiles({
        from: './assets',
        pattern: /modern-homepage\.css$/,
        to: 'assets/[name].[ext]'
    })
    .copyFiles({
        from: './assets',
        pattern: /modern-header\.css$/,
        to: 'assets/[name].[ext]'
    })

    .enableSingleRuntimeChunk()
    .enablePostCssLoader((options) => {
        options.postcssOptions = {
            config: 'postcss.config.js'
        }
    })

    .enableTypeScriptLoader()
    .addAliases({
        '@symfony/stimulus-bridge/controllers.json': './assets/controllers.json',
    })
    .enableSassLoader()
    .cleanupOutputBeforeBuild()
    .enableBuildNotifications()
    .enableSourceMaps(!Encore.isProduction())
    .enableVersioning(Encore.isProduction())

    .configureBabel((config) => {
        config.plugins.push('@babel/plugin-proposal-class-properties');
    })

    .splitEntryChunks()
;

module.exports = Encore.getWebpackConfig();
