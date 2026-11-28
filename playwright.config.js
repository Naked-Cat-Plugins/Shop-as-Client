// @ts-check
const { defineConfig, devices } = require( '@playwright/test' );

module.exports = defineConfig( {
	testDir: './specs',
	fullyParallel: false,
	workers: 1,
	reporter: 'list',

	use: {
		baseURL: process.env.WP_BASE_URL,
		ignoreHTTPSErrors: true, // the Mac's local dev cert is self-signed
		screenshot: 'only-on-failure',
		// WordPress 7.2's admin animates page changes (View Transitions), which can stall painting
		// after a form submits; reduced motion turns them off.
		contextOptions: { reducedMotion: 'reduce' },
		trace: 'retain-on-failure',
	},

	projects: [
		{
			name: 'chromium',
			use: { ...devices[ 'Desktop Chrome' ] },
		},
	],
} );
