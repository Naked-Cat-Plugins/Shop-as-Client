/**
 * External dependencies
 */
import classnames from 'classnames';
import { __, sprintf } from '@wordpress/i18n';
import { useEffect, createInterpolateElement, useState } from '@wordpress/element';
import { ExternalLink } from '@wordpress/components';
import { applyFilters } from '@wordpress/hooks';
import { CheckboxControl } from '@woocommerce/blocks-components';
import { getSetting } from '@woocommerce/settings';
import apiFetch from '@wordpress/api-fetch';
import { dispatch } from '@wordpress/data';
import { CHECKOUT_STORE_KEY } from '@woocommerce/block-data';

/**
 * Internal dependencies
 */
import { withFilteredAttributes } from './../utils';
import attributes from './attributes';
import FormStep from './frontend/form-step';

const EXTENSION_NAMESPACE = 'ptwoo-shop-as-client';

const {
	canCheckout,
	defaultShopAsClient,
	defaultCreateUser,
	showProAddOnNotice,
	blockPosition,
} = getSetting('ptwoo_shop_as_client_data');

/**
 * Cross-bundle Shop as Client state.
 *
 * Stateless block checkout keeps no transient state on the server — it rides
 * each request instead. This single object is the client-side source of truth.
 */
const sac = (window.ShopAsClient = window.ShopAsClient || {});
sac.state = sac.state || {
	shopAsClient: false,
	createUser: false,
};

/**
 * Push the current SAC state onto the checkout request `extensions` payload, so
 * the server can assign the order at checkout time. Called whenever the state
 * changes.
 */
sac.sync = () => {
	try {
		dispatch(CHECKOUT_STORE_KEY).setExtensionData(EXTENSION_NAMESPACE, {
			...sac.state,
		});
	} catch (error) {
		// Checkout store not ready yet; the next sync will carry the state.
	}
};

/**
 * Inject the `X-SAC-Active` header on Store API requests while SAC is active.
 *
 * The core /cart/update-customer route carries no extension payload, so this
 * header is the only way to tell the server SAC is active on the cart-phase
 * requests. The server uses it to suppress overwriting the manager's own saved
 * billing/shipping (and third-party) profile with the client's values. It is
 * not an auth boundary — order assignment is capability-checked server-side.
 */
if (!sac.middlewareRegistered) {
	sac.middlewareRegistered = true;
	apiFetch.use((options, next) => {
		const path = options.path || options.url || '';
		const isStoreApi =
			typeof path === 'string' && path.indexOf('/wc/store/') !== -1;
		if (isStoreApi && sac.state.shopAsClient) {
			options.headers = {
				...options.headers,
				'X-SAC-Active': '1',
			};
		}
		return next(options);
	});
}

const Block = (props) => {
	const { stepTitle, stepDescription, showStepNumber, className, inEditor } =
		props;

	const [shopAsClient, setShopAsClient] = useState(defaultShopAsClient);
	const [createUser, setCreateUser] = useState(defaultCreateUser);

	// Mirror the toggles into the shared state and the checkout extensions.
	// No server round-trip: there is no server-side transient state to set.
	useEffect(() => {
		if (!canCheckout) {
			return;
		}
		sac.state.shopAsClient = shopAsClient;
		sac.state.createUser = createUser;
		sac.sync();
	}, [shopAsClient, createUser]);

	if (!canCheckout) {
		return null;
	}

	const ShopAsClientAddOns = applyFilters('shopAsClient.AddOns', null, {
		...props,
		canCheckout,
		shopAsClient,
		createUser,
	});

	let Component = (
		<div className={className}>
			<CheckboxControl
				label={__('Shop as client', 'shop-as-client')}
				checked={shopAsClient}
				onChange={(value) => {
					setShopAsClient(value);
					if (value === false) {
						setCreateUser(false);
					}
				}}
			/>
			{ShopAsClientAddOns}
			{shopAsClient && (
				<CheckboxControl
					label={__(
						'Create user (if not found by email)?',
						'shop-as-client'
					)}
					checked={createUser}
					onChange={setCreateUser}
				/>
			)}
			{showProAddOnNotice && (
				<div className="wc-block-components-notices">
					{createInterpolateElement(
						sprintf(
							'<a>%s<br/>%s</a>',
							__(
								'Do you want to load the customer details automatically?',
								'shop-as-client'
							),
							__('Get the PRO add-on!', 'shop-as-client')
						),
						{
							a: (
								<ExternalLink href="https://nakedcatplugins.com/product/shop-as-client-for-woocommerce-pro-add-on/" />
							),
							br: <br />,
						}
					)}
				</div>
			)}
		</div>
	);

	if (!inEditor) {
		Component = (
			<FormStep
				title={stepTitle}
				description={stepDescription}
				showStepNumber={showStepNumber}
				className={classnames(
					'wp-block-woocommerce-ptwoo-shop-as-client-block',
					className
				)}
				blockPosition={blockPosition}
			>
				{Component}
			</FormStep>
		);
	}

	return Component;
};

export default withFilteredAttributes(attributes)(Block);
