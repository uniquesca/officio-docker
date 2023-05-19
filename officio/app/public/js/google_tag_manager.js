// <!-- Google Tag Manager -->
(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer', googleTagManagerContainerId);
// <!-- End Google Tag Manager -->


function googleTagManagerLogin(){
    dataLayer.push({event: "login"});
}

function googleTagManagerPurchase(transaction_id, amount, tax_amount, currency, payment_term, item_id, item_name, month_price, annually_price, bi_price) {
    dataLayer.push({ecommerce: null});  // Clear the previous ecommerce object.
    dataLayer.push({
        event: "purchase",
        ecommerce: {
            transaction_id: transaction_id,
            value: amount,
            tax: tax_amount,
            currency: currency, // "CAD"
            items: [
                {
                    item_id: item_id,
                    item_name: item_name,
                    currency: currency, // "CAD"
                    item_category: +payment_term === 1 ? "Monthly" : "Annual",
                    price: +payment_term === 1 ? month_price : annually_price
                }
            ]
        }
    });
}