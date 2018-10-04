var Encore = require('@symfony/webpack-encore');

Encore
    .setOutputPath('web/build/')
    .setPublicPath('/build')
    .cleanupOutputBeforeBuild()
    // Convert typescript files.
    .enableTypeScriptLoader()
    .autoProvidejQuery()
    .addStyleEntry('global', './app/Resources/scss/application.scss')
    .addEntry('authentication', './src/AppBundle/Resources/javascript/authentication.ts')
    .addEntry('registration', './src/AppBundle/Resources/javascript/registration.ts')

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
    .addLoader({
        test: /\.tsx?|\.js$/,
        exclude: /node_modules|vendor/,
        use: [
            {
                loader: 'tslint-loader',
                options: {
                    configFile: 'tslint.json',
                    emitErrors: true,
                    failOnHint: Encore.isProduction(),
                    typeCheck: true
                }
            }
        ]
    })
    .enableSourceMaps();

module.exports = Encore.getWebpackConfig();
module.exports.externals = {
    jquery: 'jQuery'
};
