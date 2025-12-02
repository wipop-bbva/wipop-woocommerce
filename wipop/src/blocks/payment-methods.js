import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { getSetting } from '@woocommerce/settings';
import { createElement } from '@wordpress/element';
import { decodeEntities } from '@wordpress/html-entities';

const METHODS = ['wipop_card_gateway', 'wipop_bizum_gateway'];

const Label = ({ settings, components }) => {
	const { PaymentMethodLabel } = components;

	return createElement(PaymentMethodLabel, {
		text: decodeEntities(settings?.title || settings?.name || ''),
	});
};

const Content = ({ settings }) => {
	const description = settings?.description ? decodeEntities(settings.description) : '';

	if (!description) {
		return null;
	}

	return createElement('div', { className: 'wipop-blocks-description' }, description);
};

METHODS.forEach((name) => {
	const settings = getSetting(`${name}_data`, {});

	if (!settings?.is_active) {
		return;
	}

	registerPaymentMethod({
		name,
		label: createElement(Label, { settings }),
		ariaLabel: decodeEntities(settings?.title || name),
		content: createElement(Content, { settings }),
		edit: createElement(Content, { settings }),
		canMakePayment: () => true,
		supports: {
			features: settings?.supports && settings.supports.length > 0 ? settings.supports : ['products'],
			...(settings?.supportsFlags || {}),
		},
	});
});
