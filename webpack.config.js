const path = require('path');
const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const WooCommerceDependencyExtractionWebpackPlugin = require('@woocommerce/dependency-extraction-webpack-plugin');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');

module.exports = {
	...defaultConfig,
	entry: {
		'ptwoo-shop-as-client-block': path.resolve(process.cwd(), 'includes', 'blocks', 'shop-as-client', 'index.js'),
		'ptwoo-shop-as-client-block-frontend': path.resolve(
			process.cwd(),
			'includes',
			'blocks',
			'shop-as-client',
			'frontend.js'
		),
	},
	plugins: [
		...defaultConfig.plugins.filter(
			(plugin) =>
				plugin.constructor.name !== 'DependencyExtractionWebpackPlugin'
		),
		new WooCommerceDependencyExtractionWebpackPlugin(),
		new MiniCssExtractPlugin({
			filename: `[name].css`,
		}),
	],
};
