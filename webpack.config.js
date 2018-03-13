var Encore = require('@symfony/webpack-encore');

Encore
    .setOutputPath('web/build/')
    .setPublicPath('/build')
    .cleanupOutputBeforeBuild()
    .addStyleEntry('global', './app/Resources/scss/application.scss')
    // Convert sass files.
    .enableSassLoader(function (options) {
        // https://github.com/sass/node-sass#options.
        options.includePaths = [
            'node_modules/bootstrap-sass/assets/stylesheets'
        ];
        options.outputStyle = 'expanded';
    })
    .addLoader({ test: /\.scss$/, loader: 'import-glob-loader' })
    .autoProvidejQuery()
    .enableSourceMaps(!Encore.isProduction())
;

module.exports = Encore.getWebpackConfig();
