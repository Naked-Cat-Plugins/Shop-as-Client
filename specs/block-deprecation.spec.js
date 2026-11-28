/**
 * Checkout pages saved before 8.0 store the Shop as Client step as an empty wrapper div. Since 8.0
 * the block saves no markup, so without a deprecation the editor reported the step as invalid
 * ("This block contains unexpected or invalid content").
 */
const { test, expect } = require( '@playwright/test' );
const nc = require( process.env.NAKEDCAT_TESTS_LIB + '/js/helpers.js' );

const OLD_MARKUP =
	'<!-- wp:woocommerce/ptwoo-shop-as-client -->\n<div class="wp-block-woocommerce-ptwoo-shop-as-client"></div>\n<!-- /wp:woocommerce/ptwoo-shop-as-client -->\n\n';
const FIELDS_OPEN = '<div class="wp-block-woocommerce-checkout-fields-block">';

let pageId;

test.beforeAll( () => {
	const checkout = nc.wcBlockPageContent( 'checkout' );
	expect( checkout ).toContain( FIELDS_OPEN );
	const content = checkout.replace( FIELDS_OPEN, FIELDS_OPEN + OLD_MARKUP );
	const encoded = Buffer.from( content ).toString( 'base64' );
	pageId = nc.wpEval(
		`echo wp_insert_post( wp_slash( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'nakedcat-tests-sac-deprecation', 'post_content' => base64_decode( '${ encoded }' ) ) ) );`
	);
	expect( Number( pageId ) ).toBeGreaterThan( 0 );
	// The stored markup is the pre-8.0 one.
	expect( nc.wpEval( `echo get_post_field( 'post_content', ${ pageId } );` ) ).toContain(
		'<div class="wp-block-woocommerce-ptwoo-shop-as-client"></div>'
	);
} );

test.afterAll( () => {
	if ( pageId ) {
		nc.wp( [ 'post', 'delete', String( pageId ), '--force' ] );
	}
} );

test( 'a checkout saved before 8.0 opens in the editor without an invalid block', async ( { page } ) => {
	await nc.login( page );
	await page.goto( `/wp-admin/post.php?post=${ pageId }&action=edit` );

	// Wait for the editor to have parsed the page and registered our block.
	await page.waitForFunction(
		() =>
			window.wp?.blocks?.getBlockType( 'woocommerce/ptwoo-shop-as-client' ) &&
			window.wp?.data?.select( 'core/block-editor' )?.getBlocks()?.length > 0
	);

	const result = await page.evaluate( () => {
		const flatten = ( blocks ) => blocks.flatMap( ( b ) => [ b, ...flatten( b.innerBlocks ) ] );
		const all = flatten( window.wp.data.select( 'core/block-editor' ).getBlocks() );
		const sac = all.filter( ( b ) => b.name === 'woocommerce/ptwoo-shop-as-client' );
		return {
			count: sac.length,
			valid: sac.every( ( b ) => b.isValid ),
			invalid: all.filter( ( b ) => ! b.isValid ).map( ( b ) => b.name ),
			serialized: sac.length ? window.wp.blocks.serialize( sac ) : '',
		};
	} );

	expect( result.count ).toBe( 1 );
	expect( result.invalid ).toEqual( [] );
	expect( result.valid ).toBe( true );
	// Migrated to the current form: saved again, the step has no markup of its own.
	expect( result.serialized ).not.toContain( '<div' );

	const canvas = page.frameLocator( 'iframe[name="editor-canvas"]' );
	await expect( canvas.locator( '.wp-block-woocommerce-checkout' ).first() ).toBeVisible( { timeout: 20_000 } );
	// The step renders its editor preview instead of the invalid-content warning. (Other plugins'
	// blocks may show notices of their own, so the warning is checked through isValid above.)
	await expect( canvas.locator( '.wp-block-woocommerce-ptwoo-shop-as-client-block' ) ).toHaveCount( 1 );
} );
