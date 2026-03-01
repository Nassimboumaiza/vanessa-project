<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Tax Rate
    |--------------------------------------------------------------------------
    |
    | The default tax rate to apply to cart totals. Set as a decimal value
    | (e.g., 0.10 for 10% tax).
    |
    */
    'tax_rate' => env('CART_TAX_RATE', 0.10),

    /*
    |--------------------------------------------------------------------------
    | Free Shipping Threshold
    |--------------------------------------------------------------------------
    |
    | The minimum order subtotal that qualifies for free shipping.
    |
    */
    'free_shipping_threshold' => env('CART_FREE_SHIPPING_THRESHOLD', 100),

    /*
    |--------------------------------------------------------------------------
    | Default Shipping Cost
    |--------------------------------------------------------------------------
    |
    | The default shipping cost when the order doesn't qualify for free shipping.
    |
    */
    'default_shipping_cost' => env('CART_DEFAULT_SHIPPING_COST', 15),
];
