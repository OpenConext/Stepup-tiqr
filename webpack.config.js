var Encore = require('@symfony/webpack-encore');

Encore
    .setOutputPath('public/build/')
    .copyFiles([
        {
            from: './assets/images',
            to: './images/[path][name].[ext]',
        },
        {
            from: './assets/openconext/images',
            to: './images/logo/[path][name].[ext]',
        }
    ])
    .setPublicPath('/build')
    .cleanupOutputBeforeBuild()
    // Convert typescript files.
    .enableTypeScriptLoader()
    .addStyleEntry('global', './assets/scss/application.scss')
    .addEntry('authentication', './assets/typescript/authentication.ts')
    .addEntry('registration', './assets/typescript/registration.ts')

    // Convert sass files.
    .enableSassLoader(function (options) {
        options.sassOptions = {
            outputStyle: 'expanded',
            includePaths: ['public'],
        };
    })
    .addLoader({test: /\.scss$/, loader: 'webpack-import-glob-loader'})
    .configureLoaderRule('eslint', loaderRule => {
        loaderRule.test = /\.(jsx?|vue)$/
    })
    .enableSingleRuntimeChunk()
    .enableSourceMaps(!Encore.isProduction())
    // enables hashed filenames (e.g. app.abc123.css)
    .enableVersioning(Encore.isProduction())
;


module.exports = Encore.getWebpackConfig();
