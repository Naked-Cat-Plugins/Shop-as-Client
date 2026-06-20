<?php
/**
 * Class for extending the WooCommerce Store API (stateless block checkout).
 *
 * Shop as Client keeps NO transient state on the server. The state rides each
 * request instead:
 *
 *  - Cart phase: the block sends an `X-SAC-Active` HTTP header (via an
 *    api-fetch middleware) while the toggle is on. The core
 *    /cart/update-customer route is sealed (no extension payload), so the
 *    header is the only way to know SAC is active on those requests. It drives
 *    suppression of the manager's own billing/shipping profile writes.
 *
 *  - Checkout phase: the checkout request carries an `extensions` payload
 *    `{ shopAsClient, createUser }`, captured from the
 *    `woocommerce_store_api_checkout_update_customer_from_request` action and
 *    consumed when the order is processed. The client is resolved by the
 *    submitted billing email.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for extending the WooCommerce Store API.
 */
class ShopAsClient_Extend_Store_Endpoint {

	/**
	 * Extension name (Store API namespace).
	 *
	 * @var string
	 */
	public static $name = 'ptwoo-shop-as-client';

	/**
	 * Default (standard) checkout address keys, used to classify fields.
	 *
	 * @var array
	 */
	public static $default_checkout_keys = array(
		'billing_first_name',
		'billing_last_name',
		'billing_company',
		'billing_address_1',
		'billing_address_2',
		'billing_city',
		'billing_state',
		'billing_postcode',
		'billing_country',
		'billing_email',
		'billing_phone',
		'shipping_first_name',
		'shipping_last_name',
		'shipping_company',
		'shipping_address_1',
		'shipping_address_2',
		'shipping_city',
		'shipping_state',
		'shipping_postcode',
		'shipping_country',
		'shipping_phone',
	);

	/**
	 * Checkout-request extension payload captured for the current request.
	 *
	 * Request-scoped only; never persisted.
	 *
	 * @var array
	 */
	public $checkout_state = array();

	/**
	 * The name of the extension.
	 *
	 * @return string
	 */
	public function get_name() {
		return static::$name;
	}

	/**
	 * Initialise the extension.
	 *
	 * @return void
	 */
	public function initialize() {

		// Accept our `{ shopAsClient, createUser }` payload on the checkout
		// endpoint's `extensions` field.
		if ( function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			woocommerce_store_api_register_endpoint_data(
				array(
					'endpoint'        => 'checkout',
					'namespace'       => $this->get_name(),
					'schema_callback' => array( $this, 'checkout_schema' ),
					'data_callback'   => '__return_empty_array',
					'schema_type'     => ARRAY_A,
				)
			);
		}

		add_action( 'woocommerce_store_api_checkout_update_customer_from_request', array( $this, 'capture_checkout_state' ), 10, 2 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'process_order' ), 100 );

		// Request-driven protection of the manager's own address profile.
		add_filter( 'update_user_metadata', array( $this, 'suppress_address_meta' ), 10, 3 );
		add_filter( 'add_user_metadata', array( $this, 'suppress_address_meta' ), 10, 3 );
	}

	/**
	 * Schema for the checkout `extensions` payload (inbound only).
	 *
	 * @return array
	 */
	public function checkout_schema() {
		return array(
			'shopAsClient' => array(
				'description' => __( 'Whether the order is being placed shopping as a client.', 'shop-as-client' ),
				'type'        => 'boolean',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => false,
				'optional'    => true,
			),
			'createUser'   => array(
				'description' => __( 'Whether to create a customer account when none matches.', 'shop-as-client' ),
				'type'        => 'boolean',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => false,
				'optional'    => true,
			),
		);
	}

	/**
	 * Capture the checkout-request SAC extension payload for this request.
	 *
	 * @param  \WC_Customer     $customer Customer (unused).
	 * @param  \WP_REST_Request $request  Checkout request.
	 * @return void
	 */
	public function capture_checkout_state( $customer, $request ) {

		$extensions = isset( $request['extensions'] ) ? $request['extensions'] : array();

		if ( isset( $extensions[ static::$name ] ) && is_array( $extensions[ static::$name ] ) ) {
			$this->checkout_state = $extensions[ static::$name ];
		}
	}

	/**
	 * Whether the current request is flagged as an active SAC request.
	 *
	 * Driven by the `X-SAC-Active` header injected by the block's api-fetch
	 * middleware while the toggle is on.
	 *
	 * @return bool
	 */
	public static function is_sac_request() {
		return ! empty( $_SERVER['HTTP_X_SAC_ACTIVE'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- boolean presence check only.
	}

	/**
	 * Suppress writes to the manager's own billing/shipping profile while
	 * shopping as a client.
	 *
	 * All WooCommerce block-checkout customer-profile writes (cart phase and
	 * checkout phase) funnel through {add,update}_user_metadata for the current
	 * user's address keys. When SAC is active for this request, short-circuit
	 * the writes for the standard checkout address fields this plugin manages,
	 * so the client's address never overwrites the manager's saved address. The
	 * order address is unaffected (it comes from the in-memory customer +
	 * request, not the user_meta row).
	 *
	 * @param  null|bool $check     Short-circuit value (null to proceed).
	 * @param  int       $object_id User ID the meta write targets.
	 * @param  string    $meta_key  Meta key being written.
	 * @return null|bool|int Short-circuit value to silently skip the write
	 *                       (integer meta_id for `add_user_metadata`, `true`
	 *                       for `update_user_metadata`), else $check.
	 */
	public function suppress_address_meta( $check, $object_id, $meta_key ) {

		if ( null !== $check ) {
			return $check;
		}

		// Active either via the cart-phase header or the captured checkout state.
		if ( ! static::is_sac_request() && empty( $this->checkout_state['shopAsClient'] ) ) {
			return $check;
		}

		// The header is attacker-controllable, so it is not an authorization
		// boundary: only suppress for a user actually allowed to shop as a client.
		if ( ! shop_as_client_can_checkout() ) {
			return $check;
		}

		if ( (int) $object_id !== (int) get_current_user_id() ) {
			return $check;
		}

		if ( ! is_string( $meta_key ) ) {
			return $check;
		}

		if ( in_array( $meta_key, static::$default_checkout_keys, true ) ) {
			// Short-circuit: pretend the write succeeded but persist nothing. The
			// return value must match each filter's contract: `add_user_metadata`
			// callers expect the integer meta_id that `add_user_meta()` returns,
			// while `update_user_metadata` callers expect a boolean.
			return 'add_user_metadata' === current_filter() ? 1 : true;
		}

		return $check;
	}

	/**
	 * Process a block-checkout order placed shopping as a client.
	 *
	 * @param  \WC_Order $order Order object.
	 * @return void
	 *
	 * @throws \Automattic\WooCommerce\StoreApi\Exceptions\RouteException When user creation fails.
	 */
	public function process_order( $order ) {

		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		if ( ! shop_as_client_can_checkout() ) {
			return;
		}

		// Idempotency: if this order was already processed, skip.
		if ( $order->get_meta( '_billing_shop_as_client' ) === 'yes' ) {
			return;
		}

		$state = $this->checkout_state;

		if ( empty( $state['shopAsClient'] ) ) {
			return;
		}

		$create_user = ! empty( $state['createUser'] );

		$user_id = $this->resolve_user( $order, $create_user );

		if ( is_wp_error( $user_id ) ) {
			throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
				'shop_as_client_checkout_order_process_error',
				sprintf(
					/* translators: %s: error message */
					__( 'Shop as Client failed to create user: %s', 'shop-as-client' ),
					// Store API serializes this into a JSON response, so HTML-escaping
					// is the wrong layer; sanitize the dynamic message instead.
					sanitize_text_field( $user_id->get_error_message() )
				),
				400
			);
		}

		// Stamp the order as a shop-as-client order regardless of whether a
		// customer was resolved: when no user matches and create-user is off the
		// order is still placed on a client's behalf (as a guest), so the
		// handler attribution and the order address must reflect that.
		$order->update_meta_data( '_billing_shop_as_client', 'yes' );
		$order->update_meta_data( '_billing_shop_as_client_handler_user_id', get_current_user_id() );
		$order->update_meta_data( '_billing_shop_as_client_checkout', 'blocks' );

		$order->set_customer_id( (int) $user_id );
		$order->save();

		// Optionally write the order's data back onto the (client) customer.
		if ( ! empty( $user_id ) && apply_filters( 'shop_as_client_update_customer_data', false ) ) {
			$customer = new \WC_Customer( $user_id );
			static::switch_customer_data( $customer, static::get_customer_data_by_order_id( $order ) );
			$customer->save();
		}

		do_action( 'shop_as_client_checkout_order_processed', $order, $user_id );
	}

	/**
	 * Resolve the target customer for the order by the order's billing email,
	 * creating a user when enabled and no match is found.
	 *
	 * @param  \WC_Order $order       Order object.
	 * @param  bool      $create_user Whether to create a user when no match.
	 * @return int|\WP_Error Resolved user ID (0 if none), or WP_Error on creation failure.
	 */
	protected function resolve_user( $order, $create_user ) {

		$user_email = $order->get_billing_email();

		if ( empty( $user_email ) ) {
			$user_email = apply_filters( 'shop_as_client_user_email_if_empty', $user_email, $order );
		}

		if ( empty( $user_email ) ) {
			return 0;
		}

		$user = get_user_by( 'email', $user_email );

		if ( $user instanceof \WP_User ) {
			return $user->ID;
		}

		$query = new \WP_User_Query(
			array(
				'fields'     => 'ID',
				'exclude'    => array( get_current_user_id() ),
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => 'billing_email',
						'value'   => $user_email,
						'compare' => '=',
					),
				),
			)
		);

		$user_ids = $query->get_results();

		if ( ! empty( $user_ids ) ) {
			return absint( reset( $user_ids ) );
		}

		if ( $create_user ) {
			return shop_as_client_create_customer(
				$user_email,
				$order->get_billing_first_name(),
				$order->get_billing_last_name()
			);
		}

		return 0;
	}

	/**
	 * Get customer data from an order.
	 *
	 * @param  int|\WC_Order $order The order ID or order object.
	 * @return array
	 */
	public static function get_customer_data_by_order_id( $order ) {

		$order = $order instanceof \WC_Order ? $order : new \WC_Order( $order );

		$customer_data = array(
			'customer_id' => $order->get_customer_id(),
		);

		foreach ( static::$default_checkout_keys as $key ) {
			if ( is_callable( array( $order, "get_$key" ) ) ) {
				$customer_data[ $key ] = $order->{"get_$key"}();
			}
		}

		return $customer_data;
	}

	/**
	 * Apply a keyed data array onto a customer via its setters.
	 *
	 * @param  \WC_Customer $customer Customer object.
	 * @param  array        $data     Customer data.
	 * @return void
	 */
	public static function switch_customer_data( $customer, $data ) {

		if ( ! $customer instanceof \WC_Customer ) {
			return;
		}

		if ( ! is_array( $data ) ) {
			return;
		}

		foreach ( $data as $key => $value ) {
			if ( is_callable( array( $customer, "set_$key" ) ) ) {
				$customer->{"set_$key"}( $value );
			}
		}
	}
}
