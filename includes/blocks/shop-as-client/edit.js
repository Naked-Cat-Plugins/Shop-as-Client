/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';
import { Disabled } from '@wordpress/components';

/**
 * Internal dependencies
 */
import Block from './block';

export default function Edit() {
	const { className } = useBlockProps();
	return (
		<>
			<Disabled>
				<Block className={className} />
			</Disabled>
		</>
	);
}
