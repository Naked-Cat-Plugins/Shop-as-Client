<?php
/**
 * Which account an order is assigned to when no account has the email as its own: the one with that
 * WooCommerce billing email, on both checkouts.
 *
 * @package ShopAsClient
 */

/**
 * Billing email lookup tests.
 */
class BillingEmailTest extends NakedCat_Tests_TestCase {

	/**
	 * The shared billing email.
	 */
	const EMAIL = 'nakedcat-tests-billing@example.net';

	/**
	 * A user with the billing email.
	 *
	 * @param string $role The role.
	 * @param string $login Login, as the lookup sorts by it.
	 * @return int
	 */
	private function user_with_email( $role, $login ) {
		$user = $this->make_user( $role, array( 'user_login' => $login . wp_rand() ) );
		$id   = $user instanceof WP_User ? $user->ID : (int) $user;
		update_user_meta( $id, 'billing_email', self::EMAIL );
		return $id;
	}

	/**
	 * The user the classic checkout assigns the order to.
	 *
	 * @return int
	 */
	private function classic() {
		$post  = $_POST; // phpcs:ignore WordPress.Security.NonceVerification
		$_POST = array(
			'billing_shop_as_client' => 'yes',
			'billing_email'          => self::EMAIL,
		);
		$id    = shop_as_client_woocommerce_checkout_customer_id( get_current_user_id() );
		$_POST = $post;
		return (int) $id;
	}

	/**
	 * The user the block checkout assigns the order to.
	 *
	 * @return int
	 */
	private function blocks() {
		$order = new WC_Order();
		$order->set_billing_email( self::EMAIL );
		return (int) $this->call_private( new ShopAsClient_Extend_Store_Endpoint(), 'resolve_user', $order, false );
	}

	/**
	 * A customer with the billing email is found.
	 */
	public function test_customer_found() {
		$customer = $this->user_with_email( 'customer', 'zzz' );
		$this->as_user( $this->make_user( 'shop_manager' ) );
		$this->assertSame( array( $customer ), shop_as_client_get_user_ids_by_billing_email( self::EMAIL ) );
		$this->assertSame( $customer, $this->classic() );
		$this->assertSame( $customer, $this->blocks() );
	}

	/**
	 * The staff member has the same billing email: the order is the customer's.
	 */
	public function test_customer_before_the_staff_member() {
		$manager  = $this->user_with_email( 'shop_manager', 'aaa' );
		$customer = $this->user_with_email( 'customer', 'zzz' );
		$this->as_user( get_user_by( 'id', $manager ) );
		$this->assertSame( $customer, $this->classic() );
		$this->assertSame( $customer, $this->blocks() );
	}

	/**
	 * Only the staff member has it: their own order on the classic checkout, a guest order on blocks.
	 */
	public function test_only_the_staff_member() {
		$manager = $this->user_with_email( 'shop_manager', 'aaa' );
		$this->as_user( get_user_by( 'id', $manager ) );
		$this->assertSame( $manager, $this->classic() );
		$this->assertSame( 0, $this->blocks() );
	}

	/**
	 * Nobody has it: a guest order.
	 */
	public function test_nobody() {
		$this->as_user( $this->make_user( 'shop_manager' ) );
		$this->assertSame( 0, $this->classic() );
		$this->assertSame( 0, $this->blocks() );
	}
}
