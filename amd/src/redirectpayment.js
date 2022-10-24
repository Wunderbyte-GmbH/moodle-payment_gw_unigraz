import Ajax from 'core/ajax';


export const init = (component,
    paymentarea,
    itemid, cartid, providerid) => {


    Ajax.call([{
        methodname: "paygw_unigraz_redirectpayment",
        args: {
            component,
            paymentarea,
            itemid,
            cartid,
            providerid
        },
        done: function(data) {
            location.href = data.url;
        }
    }]);

};