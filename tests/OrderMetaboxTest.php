<?php
/**
 * The Shop as Client box on the order edit screen.
 *
 * @package ShopAsClient
 */

/**
 * Tests for shop_as_client_order_metabox().
 */
class OrderMetaboxTest extends NakedCat_Tests_TestCase {

	/**
	 * Render the box for an order.
	 *
	 * @param WC_Order $order The order.
	 * @return string
	 */
	private function render( $order ) {
		ob_start();
		shop_as_client_order_metabox( $order );
		return (string) ob_get_clean();
	}

	/**
	 * An order the customer placed themselves says so, instead of an empty box.
	 */
	public function test_order_not_placed_as_client_says_no() {
		$html = $this->render( $this->make_order() );
		$this->assertStringContainsString( 'Shop as client', $html );
		$this->assertStringContainsString( 'No', $html );
		$this->assertStringNotContainsString( 'Order handled by', $html );
	}

	/**
	 * An order placed as a client says yes and names the staff member who handled it.
	 */
	public function test_order_placed_as_client_shows_the_handler() {
		$handler = $this->make_user( 'shop_manager', array( 'display_name' => 'Handler Person' ) );
		$order   = $this->make_order();
		$order->update_meta_data( '_billing_shop_as_client', 'yes' );
		$order->update_meta_data( '_billing_shop_as_client_handler_user_id', $handler->ID );
		$order->save();
		$html = $this->render( $order );
		$this->assertStringContainsString( 'Yes', $html );
		$this->assertStringContainsString( 'Order handled by', $html );
		$this->assertStringContainsString( 'Handler Person', $html );
	}

	/**
	 * Pro's extra details only appear on orders placed as a client.
	 */
	public function test_after_order_details_only_runs_for_orders_placed_as_client() {
		$calls = 0;
		$this->add_test_filter(
			'shop_as_client_after_order_details',
			function () use ( &$calls ) {
				++$calls;
			}
		);
		$this->render( $this->make_order() );
		$this->assertSame( 0, $calls );
	}
}
