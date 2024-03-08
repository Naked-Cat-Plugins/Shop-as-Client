/**
 * External dependencies
 */
import classnames from 'classnames';

/**
 * Internal dependencies
 */
import FormStepHeading from './form-step-heading';

const FormStep = ({
	title = '',
	description = '',
	showStepNumber = true,
	className = '',
	children,
	stepHeadingContent = () => undefined,
	blockPosition = '',
}) => {
	/**
	 * If the form step doesn't have a legend or title, render a <div> instead of a <fieldset>.
	 */
	const Element = title ? 'fieldset' : 'div';

	return (
		<Element
			className={classnames(
				className,
				'wc-block-components-checkout-step',
				{
					'wc-block-components-checkout-step--with-step-number':
						showStepNumber,
				}
			)}
			data-position={blockPosition}
		>
			{title && <legend className="screen-reader-text">{title}</legend>}
			{title && (
				<FormStepHeading
					title={title}
					stepHeadingContent={stepHeadingContent()}
				/>
			)}
			<div className="wc-block-components-checkout-step__container">
				{description && (
					<p className="wc-block-components-checkout-step__description">
						{description}
					</p>
				)}
				<div className="wc-block-components-checkout-step__content">
					{children}
				</div>
			</div>
		</Element>
	);
};

export default FormStep;
