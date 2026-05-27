<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Add Simple Order Approval for WooCommerce nag
 */
function ptwoo_simple_order_approval_nag() {
	?>
	<script type="text/javascript">
	jQuery(function($) {
		$( document ).on( 'click', '#ptwoo_simple_order_approval_nag .notice-dismiss', function () {
			//AJAX SET TRANSIENT FOR 90 DAYS
			$.ajax( ajaxurl, {
				type: 'POST',
				data: {
					action: 'dismiss_ptwoo_simple_order_approval_nag',
				}
			});
		});
	});
	</script>
	<div id="ptwoo_simple_order_approval_nag" class="notice notice-info is-dismissible">
		<p style="line-height: 1.4em; font-size: 1.1em;">
			<img src="<?php echo esc_url( plugin_dir_url( __FILE__ ) . 'simple-woocommerce-order-approval-logo.png' ); ?>" style="float: left; max-width: 80px; height: auto; margin-right: 1em;" width="600" height="600"/>
			<strong><?php esc_html_e( 'Do you need to set order approval on your website?', 'shop-as-client' ); ?></strong>
			<br/>
		<?php
		echo wp_kses_post(
			sprintf(
				/* translators: %1$s: link opening tag, %2$s: link closing tag */
				__( 'Check out our new plugin "%1$sSimple Order Approval for WooCommerce%2$s", the hassle-free solution for WooCommerce orders approval before payment.', 'shop-as-client' ),
				sprintf(
					'<a href="%s" target="_blank">',
					esc_url( 'https://nakedcatplugins.com/product/simple-woocommerce-order-approval/?utm_source=wp-admin&utm_medium=banner&utm_campaign=shop-as-client-simple-order-approval' )
				),
				'</a>'
			)
		);
		?>
		</p>
		<p style="line-height: 1.4em;">
			- <?php esc_html_e( 'Quickly create approval rules based on the customer origin, shipping destination, product categories, single products, or products on backorder.', 'shop-as-client' ); ?>
			<br/>
			- <?php esc_html_e( 'Simple workflow, email notifications for both customer and shop owner, and lightweight plugin without complicated and unnecessary options.', 'shop-as-client' ); ?>
		</p>
	</div>
	<?php
}
add_action( 'admin_notices', 'ptwoo_simple_order_approval_nag' );

/**
 * Dismiss Simple Order Approval for WooCommerce nag
 */
function dismiss_ptwoo_simple_order_approval_nag() {
	// Transient for everyone
	$days       = 90;
	$expiration = $days * DAY_IN_SECONDS;
	set_transient( 'ptwoo_simple_order_approval_nag', 1, $expiration );
	// Per user
	$days       = 180;
	$expiration = $days * DAY_IN_SECONDS;
	update_user_meta( get_current_user_id(), 'ptwoo_simple_order_approval_nag_dismiss_until', time() + $expiration );
	// Exit
	wp_die();
}
add_action( 'wp_ajax_dismiss_ptwoo_simple_order_approval_nag', 'dismiss_ptwoo_simple_order_approval_nag' );