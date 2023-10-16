import Ajax from 'core/ajax';

const SELECTORS = {
    PAYMENTOPTIONSBUTTONS: '[data-action="paymentoption"]',
};

export const init = () => {

    const buttons = document.querySelectorAll(SELECTORS.PAYMENTOPTIONSBUTTONS);

    // eslint-disable-next-line no-console
    console.log(buttons);

    buttons.forEach(button => {

        if (button.initialized) {
            return;
        }
        button.initialized = true;

        const component = button.dataset.component;
        const paymentarea = button.dataset.paymentarea;
        const itemid = button.dataset.itemid;
        const cartid = button.dataset.cartid;
        const providerid = button.dataset.index;

        button.addEventListener('click', e => {
            // eslint-disable-next-line no-console
            console.log(e, component, paymentarea, itemid, cartid, providerid);

            callRedirect(component, paymentarea, itemid, cartid, providerid);
        });

    });
};

/**
 * Redirect function.
 * @param {*} component
 * @param {*} paymentarea
 * @param {*} itemid
 * @param {*} cartid
 * @param {integer} providerid
 */
function callRedirect(component, paymentarea, itemid, cartid, providerid) {

    // eslint-disable-next-line no-console
    console.log('redirectpayment', component,
        paymentarea,
        itemid, cartid, providerid);

    Ajax.call([{
        methodname: "paygw_unigraz_redirectpayment",
        args: {
            component,
            paymentarea,
            itemid,
            cartid,
            providerid
        },
        done: function (data) {
            location.href = data.url;
        }
    }]);

}