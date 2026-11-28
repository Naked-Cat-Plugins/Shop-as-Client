/**
 * External dependencies
 */
import { useBlockProps } from '@wordpress/block-editor';

/**
 * Internal dependencies
 */
import attributes from './attributes';

/**
 * Before 8.0 the block saved an empty wrapper div, which the current save (null) no longer
 * matches, so the editor reported checkout pages created before then as invalid. This entry
 * accepts the old markup and lets the editor migrate it to the current, markup-less form.
 */
const v1 = {
	attributes,
	supports: {
		html: false,
		align: false,
		multiple: false,
		reusable: false,
	},
	save() {
		return <div {...useBlockProps.save()} />;
	},
};

export default [v1];
