/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

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
	stepTitle: {
		type: 'string',
		default: __('Shop as client', 'shop-as-client'),
	},
	stepDescription: {
		type: 'string',
		default: '',
	},
	showStepNumber: {
		type: 'boolean',
		default: true,
	},
};
