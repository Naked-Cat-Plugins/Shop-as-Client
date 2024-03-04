/**
 * External dependencies
 */
import { registerCheckoutBlock } from '@woocommerce/blocks-checkout';

/**
 * Internal dependencies
 */
import Block from './block';
import metadata from './block.json';

registerCheckoutBlock({
	metadata,
	component: Block,
});

document.addEventListener('DOMContentLoaded', () => {
	const checkoutBlock = document.querySelector(
		'.wp-block-woocommerce-checkout'
	);
	if (!checkoutBlock) {
		return;
	}

	const moveBlock = () => {
		const block = document.querySelector(
			'.wp-block-woocommerce-ptwoo-shop-as-client'
		);
		if (block) {
			const actionsBlock = document.querySelector(
				'.wp-block-woocommerce-checkout-actions-block'
			);
			const targetBlock = document.querySelector(block.dataset.position);

			if (block && actionsBlock && targetBlock) {
				const isAfterActionsBlock =
					actionsBlock.compareDocumentPosition(block) &
					Node.DOCUMENT_POSITION_FOLLOWING;

				if (isAfterActionsBlock) {
					targetBlock.parentNode.insertBefore(block, targetBlock);
					observer.disconnect();
				}
			}
		}
	};

	const observer = new MutationObserver((mutationList, observer) => {
		for (const mutation of mutationList) {
			if (mutation.type === 'childList') {
				moveBlock();
			}
		}
	});

	const config = { childList: true, subtree: true };
	observer.observe(checkoutBlock, config);
});
