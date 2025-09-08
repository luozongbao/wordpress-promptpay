/**
 * External dependencies
 */
const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
const { createElement } = window.wp.element;
const { __ } = window.wp.i18n;
const { getSetting } = window.wc.wcSettings;

/**
 * Internal dependencies
 */
const settings = getSetting( 'promptpay_data', {} );

/**
 * Label component
 */
const Label = ( props ) => {
    const { PaymentMethodLabel } = props.components;
    return createElement( PaymentMethodLabel, { text: settings.title } );
};

/**
 * Content component
 */
const Content = () => {
    return createElement( 'div', {
        className: 'wc-block-promptpay-content'
    }, settings.description );
};

/**
 * PromptPay payment method config object.
 */
const promptPayPaymentMethod = {
    name: 'promptpay',
    label: createElement( Label ),
    content: createElement( Content ),
    edit: createElement( Content ),
    canMakePayment: () => true,
    ariaLabel: settings.title,
    supports: {
        features: settings.supports,
    },
};

registerPaymentMethod( promptPayPaymentMethod );
