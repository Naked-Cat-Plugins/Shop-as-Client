/**
 * External dependencies
 */
import classnames from 'classnames';
import { __ } from '@wordpress/i18n';
import {
	useEffect,
	useState,
	createInterpolateElement,
} from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { ExternalLink } from '@wordpress/components';
import { applyFilters } from '@wordpress/hooks';
import { CheckboxControl } from '@woocommerce/blocks-components';
import { getSetting } from '@woocommerce/settings';
import { extensionCartUpdate } from '@woocommerce/blocks-checkout';
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

const Block = (props) => {
	const { stepTitle, stepDescription, showStepNumber, className, inEditor } =
		props;

	const [shopAsClient, setShopAsClient] = useState(defaultShopAsClient);
	const [createUser, setCreateUser] = useState(defaultCreateUser);

	const {
		__internalIncrementCalculating: disablePlaceOrderButton,
		__internalDecrementCalculating: enablePlaceOrderButton,
	} = useDispatch(CHECKOUT_STORE_KEY);

	useEffect(() => {
		if (canCheckout) {
			extensionCartUpdate({
				namespace: EXTENSION_NAMESPACE,
				data: {
					resetCustomerData: true,
				},
			});
		}
		return () => {};
	}, [canCheckout]);

	useEffect(() => {
		if (canCheckout) {
			disablePlaceOrderButton();
			extensionCartUpdate({
				namespace: EXTENSION_NAMESPACE,
				data: {
					shopAsClient,
					createUser,
				},
				cartPropsToReceive: ['extensions'],
			}).then(() => {
				enablePlaceOrderButton();
			});
		}
	}, [
		canCheckout,
		extensionCartUpdate,
		shopAsClient,
		createUser,
		disablePlaceOrderButton,
		enablePlaceOrderButton,
	]);

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
								<ExternalLink href="https://ptwooplugins.com/product/shop-as-client-for-woocommerce-pro-add-on/" />
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
