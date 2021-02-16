var Encore = require('@symfony/webpack-encore');

Encore
    .setOutputPath('public/build/')
    .setPublicPath('/build')
    .cleanupOutputBeforeBuild()
    // Convert typescript files.
    .enableTypeScriptLoader()
    .addStyleEntry('global', './public/scss/application.scss')
    .addEntry('authentication', './public/typescript/authentication.ts')
    .addEntry('registration', './public/typescript/registration.ts')

    // Convert sass files.
    .enableSassLoader(function (options) {
        options.sassOptions = {
            outputStyle: 'expanded',
            includePaths: ['public'],
        };
    })
    .addLoader({test: /\.scss$/, loader: 'import-glob-loader'})
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
    //.enableSourceMaps(!Encore.isProduction())
    .enableSourceMaps(true)
    // enables hashed filenames (e.g. app.abc123.css)
    .enableVersioning(Encore.isProduction())
;


module.exports = Encore.getWebpackConfig();
