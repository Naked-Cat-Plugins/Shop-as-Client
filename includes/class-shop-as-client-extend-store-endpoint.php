<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for extending the WooCommerce Store API
 */
class ShopAsClient_Extend_Store_Endpoint {

	/**
	 * The name of the extension.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'ptwoo-shop-as-client';
	}

	/**
	 * When called invokes any initialization/setup for the extension.
	 */
	public function initialize() {
		woocommerce_store_api_register_update_callback(
			array(
				'namespace' => $this->get_name(),
				'callback'  => array( $this, 'store_api_update_callback' ),
			)
		);
	}

	/**
	 * Add Store API schema data.
	 *
	 * @return array
	 */
	public function store_api_schema_callback() {
		return array();
	}

	/**
	 * Add Store API endpoint data.
	 *
	 * @return array
	 */
	public function store_api_data_callback() {
		return array();
	}

	/**
	 * Update callback to be executed by the Store API.
	 *
	 * @param  array $data Extension data.
	 * @return void
	 */
	public function store_api_update_callback( $data ) {

		if ( ! ( isset( wc()->session ) && wc()->session->has_session() ) ) {
			wc()->session->set_customer_session_cookie( true );
		}

		$shop_as_client = isset( $data['shopAsClient'] ) ? $data['shopAsClient'] : null;
		$create_user    = isset( $data['createUser'] ) ? $data['createUser'] : null;

		wc()->session->set( $this->get_name() . '_shop_as_client', $shop_as_client );
		wc()->session->set( $this->get_name() . '_create_user', $create_user );
	}
}
