/**
 * External dependencies
 */
import classnames from 'classnames';
import { __ } from '@wordpress/i18n';
import { PlainText } from '@wordpress/block-editor';

/**
 * Internal dependencies
 */
import FormStepHeading from './form-step-heading';

const FormStep = ({ attributes, setAttributes, className = '', children }) => {
	const { stepTitle, stepDescription, showStepNumber } = attributes;

	return (
		<div
			className={classnames(
				'wc-block-components-checkout-step',
				className,
				{
					'wc-block-components-checkout-step--with-step-number':
						showStepNumber,
				}
			)}
		>
			<FormStepHeading>
				<PlainText
					value={stepTitle}
					onChange={(value) => setAttributes({ stepTitle: value })}
					style={{ backgroundColor: 'transparent' }}
				/>
			</FormStepHeading>
			<div className="wc-block-components-checkout-step__container">
				<p className="wc-block-components-checkout-step__description">
					<PlainText
						className={
							!stepDescription
								? 'wc-block-components-checkout-step__description-placeholder'
								: ''
						}
						value={stepDescription}
						placeholder={__(
							'Optional text for this form step.',
							'shop-as-client'
						)}
						onChange={(value) =>
							setAttributes({
								stepDescription: value,
							})
						}
						style={{ backgroundColor: 'transparent' }}
					/>
				</p>
				<div className="wc-block-components-checkout-step__content">
					{children}
				</div>
			</div>
		</div>
	);
};

export default FormStep;
