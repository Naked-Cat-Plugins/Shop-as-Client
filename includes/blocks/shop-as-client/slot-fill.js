/**
 * External dependencies
 */
import { createSlotFill } from '@wordpress/components';

const { Fill, Slot } = createSlotFill('ShopAsClientAddOns');

const ShopAsClientAddOns = ({ children }) => <Fill>{children}</Fill>;

ShopAsClientAddOns.Slot = Slot;

export { ShopAsClientAddOns };
