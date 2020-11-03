var Encore = require('@symfony/webpack-encore');

Encore
    .setOutputPath('web/build/')
    .setPublicPath('/build')
    .cleanupOutputBeforeBuild()
    // Convert typescript files.
    .enableTypeScriptLoader()
    .addStyleEntry('global', './app/Resources/scss/application.scss')
    .addEntry('authentication', './src/AppBundle/Resources/javascript/authentication.ts')
    .addEntry('registration', './src/AppBundle/Resources/javascript/registration.ts')

    // Convert sass files.
    .enableSassLoader(function (options) {
        options.sassOptions = {
            outputStyle: 'expanded',
            includePaths: ['public'],
        };
    })
    .addLoader({test: /\.scss$/, loader: 'import-glob-loader'})
    .autoProvidejQuery()
    .addLoader({
        test: /\.tsx?|\.js$/,
        exclude: /node_modules|vendor/,
        use: [{
            loader: 'tslint-loader',
            options: {
                configFile: 'tslint.json',
                emitErrors: true,
                failOnHint: Encore.isProduction(),
                typeCheck: true
            }
        }]
    })
    .enableSingleRuntimeChunk()
    .enableSourceMaps(!Encore.isProduction())
    // enables hashed filenames (e.g. app.abc123.css)
    .enableVersioning(Encore.isProduction())
;


module.exports = Encore.getWebpackConfig();
module.exports.externals = {
    jquery: 'jQuery'
};
