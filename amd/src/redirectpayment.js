import Ajax from 'core/ajax';


export const init = (component,
    paymentarea,
    itemid,
    tid, cartid, providerid) => {


    Ajax.call([{
        methodname: "paygw_unigraz_redirectpayment",
        args: {
            component,
            paymentarea,
            itemid,
            tid,
            cartid,
            providerid
        },
        done: function(data) {
            location.href = data.url;
        }
    }]);

};