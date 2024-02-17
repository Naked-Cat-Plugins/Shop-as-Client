/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { getSetting } from '@woocommerce/settings';

export default {
	lock: {
		type: 'object',
		default: {
			move: false,
			remove: true,
		},
	},
	className: {
		type: 'string',
		default: '',
	},
};
