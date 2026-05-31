/**
 * External dependencies
 */
import { registerCheckoutBlock } from '@woocommerce/blocks-checkout';

/**
 * Internal dependencies
 */
import Block from './block';
import metadata from './block.json';
import './style.scss';

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

	let moved = false;

	const moveBlock = () => {
		if (moved) {
			return;
		}

		const block = document.querySelector(
			'.wp-block-woocommerce-ptwoo-shop-as-client-block'
		);
		if (!block) {
			return;
		}

		const actionsBlock = document.querySelector(
			'.wp-block-woocommerce-checkout-actions-block'
		);

		const position = block.dataset.position;
		if (!position) {
			return;
		}

		const targetBlock = document.querySelector(position);
		if (!actionsBlock || !targetBlock) {
			return;
		}

		const isAfterActionsBlock =
			actionsBlock.compareDocumentPosition(block) &
			Node.DOCUMENT_POSITION_FOLLOWING;

		if (isAfterActionsBlock) {
			requestAnimationFrame(() => {
				targetBlock.parentNode.insertBefore(block, targetBlock);
				moved = true;
				observer.disconnect();
			});
		}
	};

	const observer = new MutationObserver(() => {
		moveBlock();
	});

	const config = { childList: true, subtree: true };
	observer.observe(checkoutBlock, config);

	// Safety timeout: disconnect observer after 10 seconds to prevent indefinite observation.
	setTimeout(() => {
		observer.disconnect();
	}, 10000);
});
