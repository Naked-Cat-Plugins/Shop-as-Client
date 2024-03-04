/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, ToggleControl } from '@wordpress/components';

/**
 * Internal dependencies
 */
import Block from './block';
import FormStep from './edit/form-step';

export default function Edit({ attributes, setAttributes }) {
	const { showStepNumber } = attributes;
	const blockProps = useBlockProps();

	return (
		<>
			<InspectorControls>
				<PanelBody title={__('Form Step Options', 'shop-as-client')}>
					<ToggleControl
						label={__('Show step number', 'shop-as-client')}
						checked={showStepNumber}
						onChange={() =>
							setAttributes({
								showStepNumber: !showStepNumber,
							})
						}
					/>
				</PanelBody>
			</InspectorControls>

			<div {...blockProps}>
				<FormStep setAttributes={setAttributes} attributes={attributes}>
					<div
						aria-disabled="true"
						style={{
							userSelect: 'none',
							pointerEvents: 'none',
							cursor: 'normal',
						}}
					>
						<Block inEditor={true} />
					</div>
				</FormStep>
			</div>
		</>
	);
}
