const path = require('path')
const { VueLoaderPlugin } = require('vue-loader')
const { CleanWebpackPlugin } = require('clean-webpack-plugin')
const StyleLintPlugin = require('stylelint-webpack-plugin')

module.exports = {
	stats: {
		colors: true,
		excludeModules: true,
	},
	entry: {
		adminSettings: { import: path.join(__dirname, 'src', 'adminSettings.js'), filename: 'cospend-adminSettings.js' },
		main: { import: path.join(__dirname, 'src', 'main.js'), filename: 'cospend-main.js' },
		login: { import: path.join(__dirname, 'src', 'login.js'), filename: 'cospend-login.js' },
		dashboard: { import: path.join(__dirname, 'src', 'dashboard.js'), filename: 'cospend-dashboard.js' },
	},
	output: {
		path: path.join(__dirname, 'js'),
		publicPath: "/js/",
	},
	module: {
		rules: [
			{
				test: /\.css$/,
				use: ['vue-style-loader', 'css-loader'],
			},
			{
				test: /\.scss$/,
				use: ['vue-style-loader', 'css-loader', 'sass-loader'],
			},
			{
				test: /\.(js|vue)$/,
				use: 'eslint-loader',
				exclude: /node_modules/,
				enforce: 'pre',
			},
			{
				test: /\.vue$/,
				loader: 'vue-loader',
			},
			{
				test: /\.js$/,
				loader: 'babel-loader',
				exclude: /node_modules/,
			},
			{
				test: /\.(png|jpg|gif|svg|woff|woff2|eot|ttf)$/,
				loader: 'url-loader',
			},
		],
	},
	plugins: [
		new VueLoaderPlugin(),
		new CleanWebpackPlugin(),
		new StyleLintPlugin({
			files: 'src/**/*.{css,scss,vue}',
		}),
	],
	resolve: {
		extensions: ['*', '.js', '.vue'],
	},
}
