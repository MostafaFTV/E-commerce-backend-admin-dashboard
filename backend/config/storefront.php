<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Online Checkout
    |--------------------------------------------------------------------------
    |
    | false:
    | - Products and variants remain visible and selectable.
    | - Customers can add products to the cart.
    | - Inventory does not block cart operations.
    | - Backend checkout / order placement / payment creation are disabled.
    | - Frontend can use the existing contact checkout overlay.
    |
    | true:
    | - Normal inventory enforcement returns.
    | - Checkout and payment APIs become available again.
    |
    */

    'online_checkout_enabled' => env(
        'ONLINE_CHECKOUT_ENABLED',
        false
    ),

];