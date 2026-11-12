<?php

use Illuminate\Support\Facades\Route;

use App\Http\Middleware\ContactMessageRateLimit;

use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BannerController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\CollectionController;
use App\Http\Controllers\Api\ContactPageContentController;
use App\Http\Controllers\Api\FaqController;
use App\Http\Controllers\Api\ContactMessageController;
use App\Http\Controllers\Api\HomepageController;
use App\Http\Controllers\Api\HomepageSectionController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PageController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\PhoneChangeController;
use App\Http\Controllers\Api\ShippingMethodController;
use App\Http\Controllers\Api\StoreController;
use App\Http\Controllers\Api\SupportTicketController;
use App\Http\Controllers\Api\WishlistController;
use App\Http\Controllers\Api\CustomerDiscountController;
use App\Http\Controllers\Api\ShoppingAssistantController;


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Register
    |--------------------------------------------------------------------------
    */

    Route::post(
        '/register/request-otp',
        [
            AuthController::class,
            'requestRegistrationOtp',
        ]
    )->middleware(
        'throttle:otp-send'
    );

    Route::post(
        '/register/verify-otp',
        [
            AuthController::class,
            'verifyRegistrationOtp',
        ]
    )->middleware(
        'throttle:otp-register-verify'
    );

    /*
    |--------------------------------------------------------------------------
    | Login
    |--------------------------------------------------------------------------
    */

    Route::post(
        '/login',
        [
            AuthController::class,
            'login',
        ]
    )->middleware(
        'throttle:login'
    );

    /*
    |--------------------------------------------------------------------------
    | Login With OTP
    |--------------------------------------------------------------------------
    */

    Route::post(
        '/login/request-otp',
        [
            AuthController::class,
            'requestLoginOtp',
        ]
    )->middleware(
        'throttle:otp-send'
    );

    Route::post(
        '/login/verify-otp',
        [
            AuthController::class,
            'verifyLoginOtp',
        ]
    )->middleware(
        'throttle:otp-login-verify'
    );
    /*
    |--------------------------------------------------------------------------
    | Forgot Password
    |--------------------------------------------------------------------------
    */

    Route::post(
        '/password/forgot/request-otp',
        [
            AuthController::class,
            'requestForgotPasswordOtp',
        ]
    )->middleware(
        'throttle:otp-send'
    );

    Route::post(
        '/password/forgot/reset',
        [
            AuthController::class,
            'resetPassword',
        ]
    )->middleware(
        'throttle:otp-forgot-password-verify'
    );

    /*
    |--------------------------------------------------------------------------
    | Authenticated Account APIs
    |--------------------------------------------------------------------------
    */

    Route::middleware([
        'auth:sanctum',
        'active',
    ])->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Change Password
        |--------------------------------------------------------------------------
        */

        Route::post(
            '/password/change/request-otp',
            [
                AuthController::class,
                'requestChangePasswordOtp',
            ]
        )->middleware(
            'throttle:otp-send'
        );

        Route::post(
            '/password/change/confirm',
            [
                AuthController::class,
                'confirmChangePassword',
            ]
        )->middleware(
            'throttle:otp-change-password-verify'
        );

        /*
        |--------------------------------------------------------------------------
        | Logout
        |--------------------------------------------------------------------------
        */

        Route::post(
            '/logout',
            [
                AuthController::class,
                'logout',
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Current User
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/me',
            [
                AuthController::class,
                'me',
            ]
        );
    });

});

/*
|--------------------------------------------------------------------------
| Customer Support Tickets
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth:sanctum',
    'active',
    'role:customer',
])->prefix('support/tickets')->group(function () {
    Route::get('/', [SupportTicketController::class, 'index']);
    Route::post('/', [SupportTicketController::class, 'store']);
    Route::get('/{ticketId}', [SupportTicketController::class, 'show'])
        ->whereNumber('ticketId');
    Route::post('/{ticketId}/messages', [SupportTicketController::class, 'reply'])
        ->whereNumber('ticketId');
});

Route::middleware([
    'auth:sanctum',
    'active',
    'role:customer',
])->get('/discounts', [CustomerDiscountController::class, 'index']);

Route::post(
    '/assistant/chat',
    ShoppingAssistantController::class
)->middleware('throttle:shopping-assistant');


/*
|--------------------------------------------------------------------------
| Cart
|--------------------------------------------------------------------------
|
| Cart is available for both guests and authenticated customers.
|
| GET cart:
| - throttle:cart-read
|
| Cart mutations:
| - throttle:cart-write
|
|--------------------------------------------------------------------------
*/

Route::group([], function () {

    Route::get(
        '/cart',
        [CartController::class, 'index']
    )->middleware(
        'throttle:cart-read'
    );

    Route::post(
        '/cart/items',
        [CartController::class, 'store']
    )->middleware(
        'throttle:cart-write'
    );

    Route::put(
        '/cart/items/{item}',
        [CartController::class, 'update']
    )->middleware(
        'throttle:cart-write'
    );

    Route::delete(
        '/cart/items/{item}',
        [CartController::class, 'destroyItem']
    )->middleware(
        'throttle:cart-write'
    );

    Route::delete(
        '/cart',
        [CartController::class, 'destroy']
    )->middleware(
        'throttle:cart-write'
    );

});


/*
|--------------------------------------------------------------------------
| Public Homepage
|--------------------------------------------------------------------------
*/

Route::get(
    '/homepage',
    [
        HomepageController::class,
        'index'
    ]
);


/*
|--------------------------------------------------------------------------
| Contact Messages
|--------------------------------------------------------------------------
*/

Route::post(
    '/contact-messages',
    [
        ContactMessageController::class,
        'store'
    ]
)->middleware(
    ContactMessageRateLimit::class
);

/*
|--------------------------------------------------------------------------
| Public Categories
|--------------------------------------------------------------------------
*/

Route::get(
    '/categories',
    [
        CategoryController::class,
        'index'
    ]
);

Route::get(
    '/categories/tree',
    [
        CategoryController::class,
        'tree'
    ]
);


/*
|--------------------------------------------------------------------------
| Public Products
|--------------------------------------------------------------------------
*/

Route::get(
    '/products',
    [
        ProductController::class,
        'index'
    ]
);

Route::get(
    '/products/{slug}',
    [
        ProductController::class,
        'show'
    ]
);


/*
|--------------------------------------------------------------------------
| Public Collections
|--------------------------------------------------------------------------
*/

Route::get(
    '/collections',
    [
        CollectionController::class,
        'index'
    ]
);

Route::get(
    '/collections/{slug}',
    [
        CollectionController::class,
        'show'
    ]
);


/*
|--------------------------------------------------------------------------
| Public Contact Content
|--------------------------------------------------------------------------
*/

Route::get(
    '/contact-content',
    [
        ContactPageContentController::class,
        'index'
    ]
);


/*
|--------------------------------------------------------------------------
| Public FAQ
|--------------------------------------------------------------------------
*/

Route::get(
    '/faqs',
    [
        FaqController::class,
        'index'
    ]
);

/*
|--------------------------------------------------------------------------
| Public Pages
|--------------------------------------------------------------------------
*/

Route::get(
    '/pages',
    [
        PageController::class,
        'index'
    ]
);

Route::get(
    '/pages/{slug}',
    [
        PageController::class,
        'show'
    ]
);


/*
|--------------------------------------------------------------------------
| Public Menus
|--------------------------------------------------------------------------
*/

Route::get(
    '/menus/{slug}',
    [
        MenuController::class,
        'show'
    ]
);


/*
|--------------------------------------------------------------------------
| Public Banners
|--------------------------------------------------------------------------
|
| Only currently active banners are returned.
|
|--------------------------------------------------------------------------
*/

Route::get(
    '/banners',
    [
        BannerController::class,
        'index'
    ]
);


/*
|--------------------------------------------------------------------------
| Public Homepage Sections
|--------------------------------------------------------------------------
|
| Only active homepage sections are returned.
|
|--------------------------------------------------------------------------
*/

Route::get(
    '/homepage-sections',
    [
        HomepageSectionController::class,
        'index'
    ]
);


/*
|--------------------------------------------------------------------------
| Public Shipping Methods
|--------------------------------------------------------------------------
|
| Only active shipping methods are returned.
|
|--------------------------------------------------------------------------
*/

Route::get(
    '/shipping-methods',
    [
        ShippingMethodController::class,
        'index'
    ]
);


/*
|--------------------------------------------------------------------------
| Public Stores
|--------------------------------------------------------------------------
|
| Only active stores are returned to customers.
|
|--------------------------------------------------------------------------
*/

Route::get(
    '/stores',
    [
        StoreController::class,
        'index'
    ]
);

Route::get(
    '/stores/{slug}',
    [
        StoreController::class,
        'show'
    ]
);


/*
|--------------------------------------------------------------------------
| Authenticated Customer APIs
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth:sanctum',
    'active',
])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Customer Profile
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/profile',
        [ProfileController::class, 'show']
    );

    Route::put(
        '/profile',
        [ProfileController::class, 'update']
    );

    Route::post(
        '/profile/phone-change/request-otp',
        [PhoneChangeController::class, 'requestOtp']
    )->middleware('throttle:otp-send');

    Route::post(
        '/profile/phone-change/resend-otp',
        [PhoneChangeController::class, 'resendOtp']
    )->middleware('throttle:otp-send');

    Route::post(
        '/profile/phone-change/verify-otp',
        [PhoneChangeController::class, 'verifyOtp']
    )->middleware('throttle:phone-change-verify');

    /*
    |--------------------------------------------------------------------------
    | Legacy Profile Password
    |--------------------------------------------------------------------------
    |
    | Kept temporarily for backward compatibility with the existing
    | ProfileController implementation and current tests.
    |
    | The new OTP-secured password-change flow is available under:
    | /api/auth/password/change/request-otp
    | /api/auth/password/change/confirm
    |
    |--------------------------------------------------------------------------
    */

    Route::put(
        '/profile/password',
        [ProfileController::class, 'updatePassword']
    );


    /*
    |--------------------------------------------------------------------------
    | Customer Addresses
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/addresses',
        [AddressController::class, 'index']
    );

    Route::post(
        '/addresses',
        [AddressController::class, 'store']
    );

    Route::get(
        '/addresses/{address}',
        [AddressController::class, 'show']
    );

    Route::put(
        '/addresses/{address}',
        [AddressController::class, 'update']
    );

    Route::delete(
        '/addresses/{address}',
        [AddressController::class, 'destroy']
    );


    /*
    |--------------------------------------------------------------------------
    | Checkout
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/checkout',
        [CheckoutController::class, 'show']
    );

    Route::put(
        '/checkout/shipping-address',
        [CheckoutController::class, 'setShippingAddress']
    );

    Route::put(
        '/checkout/billing-address',
        [CheckoutController::class, 'setBillingAddress']
    );

    Route::put(
        '/checkout/shipping-method',
        [CheckoutController::class, 'setShippingMethod']
    );

    /*
     * Customer Wallet
     */
    Route::get('/wallet', [\App\Http\Controllers\Api\WalletController::class, 'show']);
    Route::get('/wallet/credits', [\App\Http\Controllers\Api\WalletController::class, 'credits']);
    Route::get('/wallet/transactions', [\App\Http\Controllers\Api\WalletController::class, 'transactions']);
    Route::put(
        '/checkout/wallet-credit',
        [CheckoutController::class, 'setWalletCredit']
    );

    Route::post(
        '/checkout/place-order',
        [CheckoutController::class, 'placeOrder']
    );


    /*
    |--------------------------------------------------------------------------
    | Customer Orders
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/orders',
        [OrderController::class, 'index']
    );

    Route::get(
        '/orders/{order}',
        [OrderController::class, 'show']
    );

    Route::get(
        '/orders/{order}/history',
        [OrderController::class, 'history']
    );


    /*
    |--------------------------------------------------------------------------
    | Payments
    |--------------------------------------------------------------------------
    */

    Route::post(
        '/payments/{order}/pay',
        [PaymentController::class, 'create']
    );


    /*
    |--------------------------------------------------------------------------
    | Customer Wishlist
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/wishlist',
        [WishlistController::class, 'index']
    )->middleware(
        'throttle:wishlist-read'
    );

    Route::post(
        '/wishlist/items',
        [WishlistController::class, 'store']
    )->middleware(
        'throttle:wishlist-write'
    );

    Route::delete(
        '/wishlist/items/{item}',
        [WishlistController::class, 'destroyItem']
    )->middleware(
        'throttle:wishlist-write'
    );

    Route::delete(
        '/wishlist',
        [WishlistController::class, 'destroy']
    )->middleware(
        'throttle:wishlist-write'
    );

});


/*
|--------------------------------------------------------------------------
| Payment Callback
|--------------------------------------------------------------------------
|
| The customer returns here from the payment gateway.
| This endpoint must NOT require Sanctum authentication.
|
|--------------------------------------------------------------------------
*/

Route::get(
    '/payment/callback',
    [PaymentController::class, 'callback']
)->middleware(
    'throttle:payment-callback'
);

/* Cashback management API; existing order permissions gate finance-related changes. */
Route::prefix('admin/cashback')
    ->middleware(['auth:sanctum', 'active', 'role:super_admin,admin'])
    ->group(function () {
        Route::get('/rules', [\App\Http\Controllers\Api\Admin\CashbackAdminController::class, 'index'])
            ->middleware('permission:orders.view');
        Route::get('/report', [\App\Http\Controllers\Api\Admin\CashbackAdminController::class, 'report'])
            ->middleware('permission:orders.view');
        Route::get('/rules/{rule}', [\App\Http\Controllers\Api\Admin\CashbackAdminController::class, 'show'])
            ->middleware('permission:orders.view');
        Route::post('/rules', [\App\Http\Controllers\Api\Admin\CashbackAdminController::class, 'store'])
            ->middleware('permission:orders.manage');
        Route::put('/rules/{rule}', [\App\Http\Controllers\Api\Admin\CashbackAdminController::class, 'update'])
            ->middleware('permission:orders.manage');
        Route::delete('/rules/{rule}', [\App\Http\Controllers\Api\Admin\CashbackAdminController::class, 'destroy'])
            ->middleware('permission:orders.manage');
    });


// TKB address location lists.
Route::get(
    '/address-locations/provinces',
    [\App\Http\Controllers\Api\AddressLocationController::class, 'provinces']
);

Route::get(
    '/address-locations/provinces/{province}/cities',
    [\App\Http\Controllers\Api\AddressLocationController::class, 'cities']
)->whereNumber('province');
