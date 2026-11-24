<?php
/**
 * The settings in the WordPress Command Palette.
 *
 * @package ShopAsClient
 */

/**
 * Command Palette tests.
 */
class CommandPaletteTest extends NakedCat_Tests_TestCase {

	/**
	 * What a user's admin screens add to core's palette script.
	 *
	 * @param string $role The role.
	 * @return string
	 */
	private function added_for( $role ) {
		$this->as_user( $this->make_user( $role ) );
		$before = (array) wp_scripts()->get_data( 'wp-commands', 'after' );
		$this->on_teardown(
			function () use ( $before ) {
				wp_scripts()->add_data( 'wp-commands', 'after', $before );
			}
		);
		wp_scripts()->add_data( 'wp-commands', 'after', array() );
		shop_as_client_command_palette();
		return implode( '', array_filter( (array) wp_scripts()->get_data( 'wp-commands', 'after' ) ) );
	}

	/**
	 * Hooked on every admin screen.
	 */
	public function test_hooked() {
		$this->assertNotFalse( has_action( 'admin_enqueue_scripts', 'shop_as_client_command_palette' ) );
	}

	/**
	 * Staff who can open the settings get the command, with the settings address.
	 */
	public function test_staff() {
		foreach ( array( 'administrator', 'shop_manager' ) as $role ) {
			$js = $this->added_for( $role );
			$this->assertStringContainsString( 'shop-as-client\/settings', $js, $role );
			$this->assertStringContainsString( 'section=shop_as_client', $js, $role );
		}
	}

	/**
	 * Nobody else does.
	 */
	public function test_others() {
		foreach ( array( 'customer', 'subscriber', 'editor' ) as $role ) {
			$this->assertSame( '', $this->added_for( $role ), $role );
		}
	}
}
