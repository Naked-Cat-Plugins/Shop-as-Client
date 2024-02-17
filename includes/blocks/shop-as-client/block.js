/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { useState, createInterpolateElement } from '@wordpress/element';
import { ExternalLink } from '@wordpress/components';
import { CheckboxControl } from '@woocommerce/blocks-components';
import { getSetting } from '@woocommerce/settings';

/**
 * Internal dependencies
 */
import { withFilteredAttributes } from './../utils';
import attributes from './attributes';
import { ShopAsClientAddOns } from './slot-fill';

const { defaultShopAsClient, defaultCreateUser, showProAddOnNotice } =
	getSetting('ptwoo_shop_as_client_data');

const Block = (props) => {
	const { className } = props;

	const [shopAsClient, setShopAsClient] = useState(defaultShopAsClient);
	const [createUser, setCreateUser] = useState(defaultCreateUser);

	return (
		<div className={className}>
			<CheckboxControl
				label={__('Shop as client', 'shop-as-client')}
				checked={shopAsClient}
				onChange={setShopAsClient}
			/>
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
			<ShopAsClientAddOns.Slot fillProps={{ props }} />
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
};

export default withFilteredAttributes(attributes)(Block);
