/**
 * External dependencies
 */
import { Title } from '@woocommerce/blocks-components';

const FormStepHeading = ({ children, stepHeadingContent }) => (
	<div className="wc-block-components-checkout-step__heading">
		<Title
			aria-hidden="true"
			className="wc-block-components-checkout-step__title"
			headingLevel="2"
		>
			{children}
		</Title>
		{!!stepHeadingContent && (
			<span className="wc-block-components-checkout-step__heading-content">
				{stepHeadingContent}
			</span>
		)}
	</div>
);

export default FormStepHeading;
