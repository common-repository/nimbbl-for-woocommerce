import Checkout from "https://cdn.jsdelivr.net/npm/nimbbl_sonic@latest";
jQuery(document).ready(function() {
    var options = {
        "callback_url": nimbbl_wc_checkout_vars.callback_url,
        "redirect": true
    };
    var OrderToken = nimbbl_wc_checkout_vars.token
    var checkout = new Checkout({ token: OrderToken });
    checkout.open(options);
});