const settings = window.wc.wcSettings.getSetting('bank_notify_data', {});
const label = window.wp.htmlEntities.decodeEntities(settings.title || 'Chuyển khoản ngân hàng');

const Content = () => {
    return window.wp.element.createElement('div', {
        dangerouslySetInnerHTML: { __html: settings.description || '' }
    });
};

const Block_Gateway = {
    name: 'bank_notify',
    label: label,
    content: Object(window.wp.element.createElement)(Content, null),
    edit: Object(window.wp.element.createElement)(Content, null),
    canMakePayment: () => true,
    ariaLabel: label,
    supports: {
        features: settings.supports || [],
    },
};
window.wc.wcBlocksRegistry.registerPaymentMethod(Block_Gateway);

document.addEventListener('DOMContentLoaded', () => {
    if (! settings.logo) {
        return;
    }

    const checkElement = setInterval(() => {
        const element = document.querySelector('#radio-control-wc-payment-method-options-bank_notify__label');
        if (element) {
            const wrapper = document.createElement('span');
            wrapper.style.width = '100%';

            const text = document.createTextNode(label);
            const logoWrapper = document.createElement('span');
            logoWrapper.style.float = 'right';

            const logo = document.createElement('img');
            logo.src = settings.logo;
            logo.alt = label;

            logoWrapper.appendChild(logo);
            wrapper.appendChild(text);
            wrapper.appendChild(logoWrapper);
            element.replaceChildren(wrapper);
            clearInterval(checkElement);
        }
    }, 100);
});
