import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { getSetting } from '@woocommerce/settings';
import { createElement, useEffect } from '@wordpress/element';
import { decodeEntities } from '@wordpress/html-entities';

const METHODS = ['wipop_card_gateway', 'wipop_bizum_gateway'];

const Label = ({ settings, components }) => {
	const { PaymentMethodLabel } = components;

	return createElement(PaymentMethodLabel, {
		text: decodeEntities(settings?.title || settings?.name || ''),
	});
};

const Content = ({ settings, paymentMethodId, eventRegistration, emitResponse, shouldSavePayment }) => {
	const description = settings?.description ? decodeEntities(settings.description) : '';
	const onPaymentProcessing = eventRegistration?.onPaymentProcessing;
	const responseTypes = emitResponse?.responseTypes;

	useEffect(() => {
		if (!onPaymentProcessing || !responseTypes) {
			return undefined;
		}

		const unsubscribe = onPaymentProcessing(() => {
			if (shouldSavePayment) {
				return {
					type: responseTypes.SUCCESS,
					meta: {
						paymentMethodData: {
							[`wc-${paymentMethodId}-new-payment-method`]: '1',
						},
					},
				};
			}

			return { type: responseTypes.SUCCESS };
		});

		return () => unsubscribe();
	}, [onPaymentProcessing, responseTypes, shouldSavePayment, paymentMethodId]);

	if (!description) {
		return null;
	}

	return createElement('div', { className: 'wipop-blocks-description' }, description);
};

const SavedTokenContent = ({ paymentMethodId, eventRegistration, emitResponse, token }) => {
	const onPaymentProcessing = eventRegistration?.onPaymentProcessing;
	const responseTypes = emitResponse?.responseTypes;

	useEffect(() => {
		if (!onPaymentProcessing || !responseTypes || !token) {
			return undefined;
		}

		const unsubscribe = onPaymentProcessing(() => ({
			type: responseTypes.SUCCESS,
			meta: {
				paymentMethodData: {
					[`wc-${paymentMethodId}-payment-token`]: String(token),
				},
			},
		}));

		return () => unsubscribe();
	}, [onPaymentProcessing, responseTypes, token, paymentMethodId]);

	return null;
};

METHODS.forEach((name) => {
	const settings = getSetting(`${name}_data`, {});

	if (!settings?.is_active) {
		return;
	}

	const content = createElement(Content, { settings, paymentMethodId: name });
	const savedTokenComponent = name === 'wipop_card_gateway'
		? createElement(SavedTokenContent, { paymentMethodId: name })
		: null;

	registerPaymentMethod({
		name,
		label: createElement(Label, { settings }),
		ariaLabel: decodeEntities(settings?.title || name),
		content,
		edit: content,
		canMakePayment: () => true,
		savedTokenComponent,
		supports: {
			features: settings?.supports && settings.supports.length > 0 ? settings.supports : ['products'],
			...(settings?.supportsFlags || {}),
		},
	});
});
