<?php
/**
 * The settings screen opened after activating the plugin.
 *
 * @package ShopAsClient
 */

/**
 * Activation redirect tests.
 */
class ActivationRedirectTest extends NakedCat_Tests_TestCase {

	/**
	 * An administrator in wp-admin, with nothing pending.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->as_user( $this->make_user( 'administrator' ) );
		$screen = $GLOBALS['current_screen'] ?? null;
		$this->on_teardown(
			function () use ( $screen ) {
				$GLOBALS['current_screen'] = $screen; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
				unset( $_REQUEST['checked'], $_GET['activate-multi'] );
				delete_transient( $this->key() );
			}
		);
		$this->screen( true );
		delete_transient( $this->key() );
	}

	/**
	 * In wp-admin or not.
	 *
	 * @param bool $admin In wp-admin.
	 */
	private function screen( $admin ) {
		$GLOBALS['current_screen'] = new class( $admin ) { // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			/**
			 * In wp-admin or not.
			 *
			 * @var bool
			 */
			private $admin;

			/**
			 * Constructor.
			 *
			 * @param bool $admin In wp-admin.
			 */
			public function __construct( $admin ) {
				$this->admin = $admin;
			}

			/**
			 * What is_admin() asks.
			 *
			 * @return bool
			 */
			public function in_admin() {
				return $this->admin;
			}
		};
	}

	/**
	 * The transient name for the current user.
	 *
	 * @return string
	 */
	private function key() {
		return 'shop_as_client_activation_redirect_' . get_current_user_id();
	}

	/**
	 * Where the next wp-admin page sends the user, or an empty string.
	 *
	 * @return string
	 */
	private function redirected_to() {
		$stop = function ( $location ) {
			throw new RuntimeException( $location ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		};
		add_filter( 'wp_redirect', $stop, PHP_INT_MAX );
		try {
			shop_as_client_activation_redirect();
			return '';
		} catch ( RuntimeException $e ) {
			return $e->getMessage();
		} finally {
			remove_filter( 'wp_redirect', $stop, PHP_INT_MAX );
		}
	}

	/**
	 * Both functions are hooked.
	 */
	public function test_hooked() {
		$this->assertNotFalse( has_action( 'activate_' . plugin_basename( WP_PLUGIN_DIR . '/shop-as-client/shop-as-client.php' ), 'shop_as_client_activation' ) );
		$this->assertNotFalse( has_action( 'admin_init', 'shop_as_client_activation_redirect' ) );
	}

	/**
	 * Activated alone in wp-admin: the settings open, once.
	 */
	public function test_single_activation() {
		shop_as_client_activation();
		$this->assertStringContainsString( 'section=shop_as_client', $this->redirected_to() );
		$this->assertSame( '', $this->redirected_to() );
	}

	/**
	 * Activated with other plugins: never, even when the page WordPress opens after it is not the next one.
	 */
	public function test_bulk_activation() {
		$_REQUEST['checked'] = array( 'shop-as-client/shop-as-client.php', 'another/another.php' );
		shop_as_client_activation();
		$this->assertSame( '', $this->redirected_to() );
	}

	/**
	 * The page WordPress opens after a bulk activation never redirects.
	 */
	public function test_bulk_activation_page() {
		set_transient( $this->key(), true, 30 );
		$_GET['activate-multi'] = 'true';
		$this->assertSame( '', $this->redirected_to() );
		unset( $_GET['activate-multi'] );
		$this->assertSame( '', $this->redirected_to() );
	}

	/**
	 * Activated outside wp-admin (REST API, remote management): never.
	 */
	public function test_outside_admin() {
		$this->screen( false );
		shop_as_client_activation();
		$this->screen( true );
		$this->assertSame( '', $this->redirected_to() );
	}

	/**
	 * Activated with WP-CLI: never.
	 */
	#[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
	#[\PHPUnit\Framework\Attributes\PreserveGlobalState( false )]
	public function test_wp_cli_activation() {
		define( 'WP_CLI', true );
		shop_as_client_activation();
		$this->assertFalse( get_transient( $this->key() ) );
	}

	/**
	 * A WP-CLI command right after an activation in wp-admin is not redirected, and wp-admin still is.
	 */
	#[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
	#[\PHPUnit\Framework\Attributes\PreserveGlobalState( false )]
	public function test_wp_cli_command_after_activation() {
		shop_as_client_activation();
		define( 'WP_CLI', true );
		$this->assertSame( '', $this->redirected_to() );
		$this->assertNotFalse( get_transient( $this->key() ) );
	}
}
