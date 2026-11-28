/**
 * After a Shop as Client order, the staff member's next checkout must not open with the client's
 * details. WooCommerce copies the posted address into the logged-in user's session customer, and
 * Shop as Client stops the staff member's own details from being updated, so without
 * shop_as_client_restore_session_customer() the session kept the client's email and addresses.
 */
const { test, expect } = require( '@playwright/test' );
const nc = require( process.env.NAKEDCAT_TESTS_LIB + '/js/helpers.js' );

let client, productId, adminId, orderId, restoreBacs, checkoutClassic, checkoutBlocks;

function sessionEmail( userId ) {
	return nc.wpEval(
		`global $wpdb; $v = $wpdb->get_var( $wpdb->prepare( "SELECT session_value FROM {$wpdb->prefix}woocommerce_sessions WHERE session_key = %s", '${ userId }' ) ); $d = $v ? maybe_unserialize( $v ) : array(); $c = isset( $d['customer'] ) ? maybe_unserialize( $d['customer'] ) : array(); echo isset( $c['email'] ) ? $c['email'] : '';`
	);
}

test.beforeAll( () => {
	client = nc.createUser( 'customer' );
	productId = nc.createProduct( 20 );
	adminId = Number( nc.wp( [ 'user', 'get', process.env.WP_ADMIN_USER, '--field=ID' ] ) );
	// Bank transfer on, put back afterwards.
	const old = nc.wpEval( `echo wp_json_encode( get_option( 'woocommerce_bacs_settings', null ) );` );
	nc.wpEval( `$s = get_option( 'woocommerce_bacs_settings', array() ); $s = is_array( $s ) ? $s : array(); $s['enabled'] = 'yes'; update_option( 'woocommerce_bacs_settings', $s );` );
	restoreBacs = () =>
		'null' === old
			? nc.wp( [ 'option', 'delete', 'woocommerce_bacs_settings' ] )
			: nc.wpEval( `update_option( 'woocommerce_bacs_settings', json_decode( ${ JSON.stringify( old ) }, true ) );` );
	checkoutClassic = nc.ensurePage( 'nakedcat-tests-sac-checkout-classic', '<!-- wp:shortcode -->[woocommerce_checkout]<!-- /wp:shortcode -->' );
	checkoutBlocks = nc.ensurePage( 'nakedcat-tests-sac-checkout-blocks', nc.wcBlockPageContent( 'checkout' ), { refresh: true } );
	nc.emptyCart( adminId );
} );

test.afterAll( () => {
	if ( orderId ) {
		nc.wpEval( `$o = wc_get_order( ${ Number( orderId ) } ); if ( $o ) { $o->delete( true ); }` );
	}
	nc.emptyCart( adminId );
	if ( restoreBacs ) {
		restoreBacs();
	}
	if ( productId ) {
		nc.deleteProduct( productId );
	}
	if ( client ) {
		nc.deleteUser( client );
	}
} );

test( 'a classic Shop as Client order leaves the staff member their own details for the next checkout', async ( { page } ) => {
	const clientEmail = `${ client.login }@example.com`;

	await nc.login( page );
	await nc.addToCart( page, productId );
	await page.goto( checkoutClassic );
	await page.waitForSelector( '#billing_shop_as_client' );

	await page.evaluate(
		( email ) => {
			const $ = window.jQuery;
			$( '#billing_shop_as_client' ).val( 'yes' ).trigger( 'change' );
			if ( $( '#billing_shop_as_client_create_user' ).length ) {
				$( '#billing_shop_as_client_create_user' ).val( 'no' ).trigger( 'change' );
			}
			$( '#billing_country' ).val( 'PT' ).trigger( 'change' );
			$( '#billing_email' ).val( email );
		},
		clientEmail
	);
	await page.waitForTimeout( 500 );
	await page.evaluate( () => {
		const $ = window.jQuery;
		const set = ( id, v ) => $( id ).val( v ).trigger( 'change' );
		set( '#billing_first_name', 'Cliente' );
		set( '#billing_last_name', 'Teste' );
		set( '#billing_address_1', 'Rua Augusta 1' );
		set( '#billing_city', 'Lisboa' );
		set( '#billing_postcode', '1100-148' );
		if ( $( '#billing_state' ).is( 'select' ) ) {
			set( '#billing_state', 'LS' );
		}
		set( '#billing_phone', '912345678' );
		$( '#ship-to-different-address-checkbox' ).prop( 'checked', false ).trigger( 'change' );
	} );
	// Wait for the order review refresh to finish before choosing the payment method.
	await page.waitForFunction( () => ! document.querySelector( '.blockUI' ) );
	await page.evaluate( () => window.jQuery( '#payment_method_bacs' ).prop( 'checked', true ).trigger( 'change' ) );
	await page.waitForFunction( () => ! document.querySelector( '.blockUI' ) );
	// Terms and conditions, when the install asks for them.
	await page.evaluate( () => window.jQuery( '#terms' ).prop( 'checked', true ) );
	await Promise.all( [ page.waitForURL( /order-received/, { timeout: 30_000 } ), page.click( '#place_order' ) ] );
	orderId = ( page.url().match( /order-received\/(\d+)/ ) || [] )[ 1 ];
	expect( orderId ).toBeTruthy();

	// The order went to the client.
	expect( nc.wpEval( `echo wc_get_order( ${ Number( orderId ) } )->get_customer_id();` ) ).toBe( String( client.id ) );

	// The staff member's session no longer holds the client's details.
	expect( sessionEmail( adminId ) ).not.toBe( clientEmail );

	// And the next checkout does not open with them.
	await nc.addToCart( page, productId );
	await page.goto( checkoutBlocks );
	const email = page.locator( '#email' );
	await expect( email ).toBeVisible( { timeout: 20_000 } );
	await expect( email ).not.toHaveValue( clientEmail );
} );

test( 'a block Shop as Client checkout left without ordering does not reopen with the client\'s details', async ( { page } ) => {
	const clientEmail = `${ client.login }@example.com`;
	const cartReady = () => window.wp?.data?.select( 'wc/store/cart' )?.hasFinishedResolution( 'getCartData' );

	nc.emptyCart( adminId );
	await nc.login( page );
	await nc.addToCart( page, productId );
	await page.goto( checkoutBlocks );
	await page.waitForFunction( cartReady );
	await page.waitForTimeout( 1000 );

	await page.getByLabel( 'Shop as client', { exact: true } ).check();
	await page.locator( '#email' ).fill( clientEmail );
	await page.locator( '#email' ).blur();
	const city = page.locator( '#shipping-city' );
	if ( await city.count() ) {
		await city.fill( 'Faro' );
		await city.blur();
	}
	// The client's details reach the session.
	await expect.poll( () => sessionEmail( adminId ), { timeout: 15_000 } ).toBe( clientEmail );

	// Leave without ordering and come back.
	await page.goto( nc.env.url + '/' );
	await page.goto( checkoutBlocks );
	await page.waitForFunction( cartReady );
	await page.waitForTimeout( 1000 );

	expect( sessionEmail( adminId ) ).not.toBe( clientEmail );
	await expect( page.locator( '#email' ) ).not.toHaveValue( clientEmail );
	if ( await city.count() ) {
		await expect( city ).not.toHaveValue( 'Faro' );
	}
} );
