<?php
/**
 * Class for extending the WooCommerce Store API
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for extending the WooCommerce Store API
 */
class ShopAsClient_Extend_Store_Endpoint {

	/**
	 * Extension name.
	 *
	 * @var string
	 */
	public static $name = 'ptwoo-shop-as-client';

	/**
	 * List of default checkout keys.
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
	 * The name of the extension.
	 *
	 * @return string
	 */
	public function get_name() {
		return static::$name;
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

		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'process_order' ), 100 );
		add_filter( 'update_user_metadata', array( __CLASS__, 'prevent_manager_address_meta_overwrite' ), 10, 5 );
		add_filter( 'add_user_metadata', array( __CLASS__, 'prevent_manager_address_meta_overwrite' ), 10, 5 );
	}

	/**
	 * Update callback to be executed by the Store API.
	 *
	 * @param  array $data Extension data.
	 * @return void
	 */
	public function store_api_update_callback( $data ) {

		if ( isset( wc()->session ) && ! wc()->session->has_session() ) {
			wc()->session->set_customer_session_cookie( true );
		}

		$user_id = get_current_user_id();

		if ( ! empty( $data['resetCustomerData'] ) ) {
			static::restore_customer_data();
		}

		// Persist "Shop As Client" option in user_meta to avoid WC session whole-blob write races.
		if ( $user_id && array_key_exists( 'shopAsClient', $data ) ) {
			if ( $data['shopAsClient'] ) {
				update_user_meta( $user_id, static::$name . '_shop_as_client', '1' );
			} else {
				delete_user_meta( $user_id, static::$name . '_shop_as_client' );
			}
		}

		// Persist "Create User" option.
		if ( $user_id && array_key_exists( 'createUser', $data ) ) {
			if ( $data['createUser'] ) {
				update_user_meta( $user_id, static::$name . '_create_user', '1' );
			} else {
				delete_user_meta( $user_id, static::$name . '_create_user' );
			}
		}

		/**
		 * Persist current customer data so it can be restored after the purchase.
		 */
		if ( $user_id && ! class_exists( 'ShopAsClientPro_Extend_Store_Endpoint' ) ) {
			$customer_data = get_user_meta( $user_id, static::$name . '_current_customer_data', true );
			if ( empty( $customer_data ) ) {
				$customer_data = static::get_customer_data_by_user_id( $user_id );
				update_user_meta( $user_id, static::$name . '_current_customer_data', $customer_data );
			}
		}
	}

	/**
	 * Check if the current request is acting as SAC for the logged-in user.
	 *
	 * @return bool
	 */
	public static function is_active() {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}
		return (bool) get_user_meta( $user_id, static::$name . '_shop_as_client', true );
	}

	/**
	 * Check if "create user" is enabled in the current SAC request.
	 *
	 * @return bool
	 */
	public static function is_create_user() {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}
		return (bool) get_user_meta( $user_id, static::$name . '_create_user', true );
	}

	/**
	 * Process order.
	 *
	 * @param  \WC_Order $order Order object.
	 * @return void
	 */
	public function process_order( $order ) {

		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		if ( ! shop_as_client_can_checkout() ) {
			return;
		}

		// If Pro already processed this order, skip Free's processing.
		if ( $order->get_meta( '_billing_shop_as_client' ) === 'yes' ) {
			return;
		}

		$shop_as_client = static::is_active();
		$create_user    = static::is_create_user();

		if ( ! $shop_as_client ) {
			return;
		}

		$user_id    = 0;
		$user_email = $order->get_billing_email();

		if ( empty( $user_email ) ) {
			$user_email = apply_filters( 'shop_as_client_user_email_if_empty', $user_email, $order );
		}

		if ( empty( $user_email ) ) {
			return;
		}

		$user = get_user_by( 'email', $user_email );

		if ( $user instanceof \WP_User ) {
			$user_id = $user->ID;
		} else {

			$query_args = array(
				'fields'     => 'ID',
				'exclude'    => array( get_current_user_id() ), // Exclude the current user.
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => 'billing_email',
						'value'   => $user_email,
						'compare' => '=',
					),
				),
			);

			$query    = new \WP_User_Query( $query_args );
			$user_ids = $query->get_results();

			if ( ! empty( $user_ids ) ) {
				$user_id = reset( $user_ids );
				$user_id = absint( $user_id );
			} elseif ( $create_user ) {
				$user_id = shop_as_client_create_customer(
					$user_email,
					$order->get_billing_first_name(),
					$order->get_billing_last_name()
				);
			}
		}

		if ( is_wp_error( $user_id ) ) {
			static::restore_customer_data();

			throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
				'shop_as_client_checkout_order_process_error',
				sprintf(
					/* translators: %s: error message */
					esc_html__( 'Shop as Client failed to create user: %s', 'shop-as-client' ),
					esc_html( $user_id->get_error_message() )
				),
				400
			);
		}

		$order->update_meta_data( '_billing_shop_as_client', 'yes' );
		$order->update_meta_data( '_billing_shop_as_client_handler_user_id', get_current_user_id() );
		$order->update_meta_data( '_billing_shop_as_client_checkout', 'blocks' );

		$order->set_customer_id( $user_id );
		$order->save();

		// Update customer data.
		if ( ! empty( $user_id ) && apply_filters( 'shop_as_client_update_customer_data', false ) ) {
			$customer      = new \WC_Customer( $user_id );
			$customer_data = static::get_customer_data_by_order_id( $order->get_id() );
			static::switch_customer_data( $customer, $customer_data );
			$customer->save();
		}

		do_action( 'shop_as_client_checkout_order_processed', $order, $user_id );

		$this->reset_session();
	}

	/**
	 * Clear the extension's session data and restore customer data.
	 *
	 * @return void
	 */
	public function reset_session() {
		static::restore_customer_data();

		$user_id = get_current_user_id();
		if ( $user_id ) {
			delete_user_meta( $user_id, static::$name . '_shop_as_client' );
			delete_user_meta( $user_id, static::$name . '_create_user' );
			delete_user_meta( $user_id, static::$name . '_current_customer_data' );
		}
	}

	/**
	 * Prevent the manager's own billing/shipping user meta from being
	 * overwritten while they are shopping as a client on the block checkout.
	 *
	 * Short-circuits the {add,update}_user_metadata filters for the current
	 * user's billing_ and shipping_ keys when SAC is active, so WooCommerce's
	 * block-checkout customer sync can't clobber the manager's saved address.
	 *
	 * @param  null|bool $check      The short-circuit value (null to proceed).
	 * @param  int       $object_id  User ID the meta write targets.
	 * @param  string    $meta_key   Meta key being written.
	 * @param  mixed     $meta_value Meta value being written (unused).
	 * @param  mixed     $prev_value Previous value (unused).
	 * @return null|bool `true` to silently skip the write, else $check.
	 */
	public static function prevent_manager_address_meta_overwrite( $check, $object_id, $meta_key, $meta_value, $prev_value ) {
		if ( null !== $check ) {
			return $check;
		}
		if ( ! static::is_active() ) {
			return $check;
		}
		if ( (int) $object_id !== (int) get_current_user_id() ) {
			return $check;
		}
		if ( ! is_string( $meta_key ) ) {
			return $check;
		}
		if ( preg_match( '/^(billing|shipping)_/', $meta_key ) ) {
			// Short-circuit: pretend the meta write succeeded but do not persist.
			return true;
		}
		return $check;
	}

	/**
	 * Restore customer data to its state before the purchase.
	 *
	 * @return void
	 */
	public static function restore_customer_data() {
		$user_id  = get_current_user_id();
		$customer = new \WC_Customer( $user_id );

		$customer_data = get_user_meta( $user_id, static::$name . '_current_customer_data', true );
		if ( empty( $customer_data ) ) {
			$customer_data = null;
		}

		static::switch_customer_data( $customer, $customer_data );

		$customer->save();

		wc()->customer = $customer; // This is required to trigger the fields update on the checkout when the current customer data is restored ¯\_(ツ)_/¯.
	}

	/**
	 * Get customer data by user ID.
	 *
	 * @param  int|\WC_Customer $user_id The user ID, or the WC_Customer object.
	 * @return array
	 */
	public static function get_customer_data_by_user_id( $user_id ) {
		$customer = new \WC_Customer( $user_id );

		$customer_data = array(
			'customer_id' => $customer->get_id(),
		);

		foreach ( static::$default_checkout_keys as $key ) {
			if ( is_callable( array( $customer, "get_$key" ) ) ) {
				$customer_data[ $key ] = $customer->{"get_$key"}();
			}
		}

		return $customer_data;
	}

	/**
	 * Get customer data by order ID.
	 *
	 * @param  int $order_id The order ID, or the WC_Order object.
	 * @return array
	 */
	public static function get_customer_data_by_order_id( $order_id ) {
		$order = new \WC_Order( $order_id );

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
	 * Switch customer data.
	 *
	 * @param  \WC_Customer $customer Customer object.
	 * @param  array        $data     Customer data.
	 * @return void
	 */
	public static function switch_customer_data( $customer, $data ) {

		if ( ! $customer instanceof \WC_Customer ) {
			return;
		}

		if ( ! isset( $data ) ) {
			return;
		}

		foreach ( $data as $key => $value ) {
			if ( is_callable( array( $customer, "set_$key" ) ) ) {
				$customer->{"set_$key"}( $value );
			}
		}
	}
}
