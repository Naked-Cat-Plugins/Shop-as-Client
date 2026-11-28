/**
 * External dependencies
 */
import { registerBlockType } from '@wordpress/blocks';
import { commentAuthorAvatar as icon } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import metadata from './block.json';
import attributes from './attributes';
import Edit from './edit';
import Save from './save';
import deprecated from './deprecated';
import './style.scss';

registerBlockType(
	{
		...metadata,
		attributes: {
			...attributes,
		},
	},
	{
		icon,
		edit: Edit,
		save: Save,
		deprecated,
	}
);
