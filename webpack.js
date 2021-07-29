const path = require('path')
const webpackConfig = require('@nextcloud/webpack-vue-config')

const buildMode = process.env.NODE_ENV
const isDev = buildMode === 'development'
webpackConfig.devtool = isDev ? 'cheap-source-map' : 'source-map'
// webpackConfig.bail = false

webpackConfig.stats = {
    colors: true,
    modules: false,
}

webpackConfig.entry = {
    adminSettings: { import: path.join(__dirname, 'src', 'adminSettings.js'), filename: 'cospend-adminSettings.js' },
    main: { import: path.join(__dirname, 'src', 'main.js'), filename: 'cospend-main.js' },
    login: { import: path.join(__dirname, 'src', 'login.js'), filename: 'cospend-login.js' },
    dashboard: { import: path.join(__dirname, 'src', 'dashboard.js'), filename: 'cospend-dashboard.js' },
}

module.exports = webpackConfig
