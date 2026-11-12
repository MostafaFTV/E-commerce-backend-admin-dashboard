<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Admin\ActivityLogController;use App\Http\Controllers\Admin\AdminAuthController;

use App\Http\Controllers\Admin\AttributeController;
use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CollectionController;
use App\Http\Controllers\Admin\ContactPageContentController;
use App\Http\Controllers\Admin\FaqController;
use App\Http\Controllers\Admin\ContactMessageController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DiscountCodeController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\HomepageSectionController;
use App\Http\Controllers\Admin\HomepageMediaController;
use App\Http\Controllers\Admin\InspirationVideoController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\MenuItemController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductExcelController;
use App\Http\Controllers\Admin\ProductExcelImportController;
use App\Http\Controllers\Admin\ProductImageController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\ShippingMethodController;
use App\Http\Controllers\Admin\StoreController;
use App\Http\Controllers\Admin\SupportTicketController;
use App\Http\Controllers\Admin\UserController;


/*
|--------------------------------------------------------------------------
| Admin Authentication
|--------------------------------------------------------------------------
*/

Route::prefix('admin/auth')
    ->group(function () {

        Route::post(
            '/login',
            [
                AdminAuthController::class,
                'login',
            ]
        )->middleware(
            'throttle:login'
        );

        Route::middleware([
            'auth:sanctum',
            'active',
            'role:super_admin,admin,accountant,support',
        ])->group(function () {

            Route::get(
                '/me',
                [
                    AdminAuthController::class,
                    'me',
                ]
            );

            Route::post(
                '/logout',
                [
                    AdminAuthController::class,
                    'logout',
                ]
            );
        });
    });

/*
|--------------------------------------------------------------------------
| Admin Panel API
|--------------------------------------------------------------------------
|
| General admin routes.
| Orders, Collections, Customers, Pages, Menus, Banners,
| Homepage Sections, Store management and Activity Logs
| use dedicated permission-based middleware below.
|
|--------------------------------------------------------------------------
*/

Route::prefix('admin')
    ->middleware([
        'auth:sanctum',
        'active',
        'role:super_admin,admin'
    ])
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Category Tree
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/categories/tree',
            [
                CategoryController::class,
                'tree'
            ]
        );


        /*
        |--------------------------------------------------------------------------
        | Categories Management
        |--------------------------------------------------------------------------
        */

        Route::apiResource(
            'categories',
            CategoryController::class
        );


        /*
        |--------------------------------------------------------------------------
        | Products Management
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/products/excel/export',
            [
                ProductExcelController::class,
                'export'
            ]
        )->middleware([
            'permission:products.view',
            'permission:inventory.view',
        ]);

        Route::post(
            '/products/excel/preview',
            [
                ProductExcelImportController::class,
                'preview'
            ]
        )->middleware([
            'permission:products.update',
            'permission:inventory.manage',
        ]);

        Route::post(
            '/products/excel/apply',
            [
                ProductExcelImportController::class,
                'apply'
            ]
        )->middleware([
            'permission:products.update',
            'permission:inventory.manage',
        ]);


        Route::apiResource(
            'products',
            ProductController::class
        );

        Route::get(
            '/discount-codes/options',
            [DiscountCodeController::class, 'options']
        )->middleware('permission:customers.view');

        Route::get(
            '/discount-codes',
            [DiscountCodeController::class, 'index']
        )->middleware('permission:customers.view');

        Route::post(
            '/discount-codes',
            [DiscountCodeController::class, 'store']
        )->middleware('permission:customers.manage');

        Route::put(
            '/discount-codes/{discountCode}',
            [DiscountCodeController::class, 'update']
        )->middleware('permission:customers.manage');

        Route::patch(
            '/discount-codes/{discountCode}/deactivate',
            [DiscountCodeController::class, 'deactivate']
        )->middleware('permission:customers.manage');

    });


/*
|--------------------------------------------------------------------------
| Order / Collection / Customer / Page / Banner / Homepage Section /
| Menu / Shipping Method / Store / Activity Log Management API
|--------------------------------------------------------------------------
|
| These endpoints are permission-based.
|
|--------------------------------------------------------------------------
*/

Route::prefix('admin')
    ->middleware([
        'auth:sanctum',
        'active',
    ])
    ->group(function () {

        Route::get(
            '/notifications',
            [NotificationController::class, 'index']
        );

        Route::post(
            '/notifications/read-all',
            [NotificationController::class, 'markAllRead']
        );

        /*
        |--------------------------------------------------------------------------
        | View Orders
        |--------------------------------------------------------------------------
        */

        Route::middleware(
            'permission:orders.view'
        )->group(function () {

            Route::get(
                '/orders',
                [
                    OrderController::class,
                    'index'
                ]
            );

            Route::get(
                '/orders/{order}',
                [
                    OrderController::class,
                    'show'
                ]
            );

            Route::get(
                '/orders/{order}/history',
                [
                    OrderController::class,
                    'history'
                ]
            );

        });


        /*
        |--------------------------------------------------------------------------
        | Update Order Status
        |--------------------------------------------------------------------------
        */

        Route::patch(
            '/orders/{order}/status',
            [
                OrderController::class,
                'updateStatus'
            ]
        )->middleware(
            'permission:orders.update_status'
        );


        /*
        |--------------------------------------------------------------------------
        | Cancel Order
        |--------------------------------------------------------------------------
        |
        | Only users with:
        |
        | orders.cancel
        |
        | can explicitly cancel an order from the admin panel.
        |
        |--------------------------------------------------------------------------
        */

        Route::patch(
            '/orders/{order}/cancel',
            [
                OrderController::class,
                'cancel'
            ]
        )->middleware(
            'permission:orders.cancel'
        );


        /*
        |--------------------------------------------------------------------------
        | Return Order
        |--------------------------------------------------------------------------
        |
        | Only users with:
        |
        | orders.return
        |
        | can explicitly return an order from the admin panel.
        |
        |--------------------------------------------------------------------------
        */

        Route::patch(
            '/orders/{order}/return',
            [
                OrderController::class,
                'returnOrder'
            ]
        )->middleware(
            'permission:orders.return'
        );


        /*
        |--------------------------------------------------------------------------
        | Product Media Management
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/products/{product}/images',
            [
                ProductImageController::class,
                'index'
            ]
        )->middleware(
            'permission:products.view'
        );

        Route::post(
            '/products/{product}/images',
            [
                ProductImageController::class,
                'store'
            ]
        )->middleware(
            'permission:products.update'
        );

        Route::post(
            '/products/{product}/images/reorder',
            [
                ProductImageController::class,
                'reorder'
            ]
        )->middleware(
            'permission:products.update'
        );

        Route::match(
            ['put', 'patch'],
            '/products/{product}/images/{image}',
            [
                ProductImageController::class,
                'update'
            ]
        )->middleware(
            'permission:products.update'
        );

        Route::delete(
            '/products/{product}/images/{image}',
            [
                ProductImageController::class,
                'destroy'
            ]
        )->middleware(
            'permission:products.update'
        );

        /*
        |--------------------------------------------------------------------------
        | Brands / Attributes Management
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/brands',
            [
                BrandController::class,
                'index'
            ]
        )->middleware(
            'permission:products.view'
        );

        Route::post(
            '/brands',
            [
                BrandController::class,
                'store'
            ]
        )->middleware(
            'permission:products.create,products.update'
        );

        Route::match(
            ['put', 'patch'],
            '/brands/{brand}',
            [
                BrandController::class,
                'update'
            ]
        )->middleware(
            'permission:products.update'
        );

        Route::delete(
            '/brands/{brand}',
            [
                BrandController::class,
                'destroy'
            ]
        )->middleware(
            'permission:products.update'
        );


        Route::get(
            '/attributes',
            [
                AttributeController::class,
                'index'
            ]
        )->middleware(
            'permission:products.view'
        );

        Route::post(
            '/attributes',
            [
                AttributeController::class,
                'store'
            ]
        )->middleware(
            'permission:products.create,products.update'
        );

        Route::match(
            ['put', 'patch'],
            '/attributes/{attribute}',
            [
                AttributeController::class,
                'update'
            ]
        )->middleware(
            'permission:products.update'
        );

        Route::delete(
            '/attributes/{attribute}',
            [
                AttributeController::class,
                'destroy'
            ]
        )->middleware(
            'permission:products.update'
        );

        Route::post(
            '/attributes/{attribute}/values',
            [
                AttributeController::class,
                'storeValue'
            ]
        )->middleware(
            'permission:products.create,products.update'
        );

        Route::match(
            ['put', 'patch'],
            '/attribute-values/{attributeValue}',
            [
                AttributeController::class,
                'updateValue'
            ]
        )->middleware(
            'permission:products.update'
        );

        Route::delete(
            '/attribute-values/{attributeValue}',
            [
                AttributeController::class,
                'destroyValue'
            ]
        )->middleware(
            'permission:products.update'
        );

        /*
        |--------------------------------------------------------------------------
        | Collections Management
        |--------------------------------------------------------------------------
        |
        | collections.view
        | collections.create
        | collections.update
        | collections.delete
        |
        |--------------------------------------------------------------------------
        */

        /*
        |--------------------------------------------------------------------------
        | Inventory Management
        |--------------------------------------------------------------------------
        |
        | inventory.view
        | inventory.manage
        |
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/inventory/products',
            [
                InventoryController::class,
                'products'
            ]
        )->middleware(
            'permission:inventory.view'
        );

        Route::get(
            '/inventory/products/{product}',
            [
                InventoryController::class,
                'showProduct'
            ]
        )->middleware(
            'permission:inventory.view'
        );

        Route::get(
            '/inventory',
            [
                InventoryController::class,
                'index'
            ]
        )->middleware(
            'permission:inventory.view'
        );

        Route::get(
            '/inventory/{inventory}',
            [
                InventoryController::class,
                'show'
            ]
        )->middleware(
            'permission:inventory.view'
        );

        Route::post(
            '/inventory/{inventory}/adjust',
            [
                InventoryController::class,
                'adjust'
            ]
        )->middleware(
            'permission:inventory.manage'
        );

        Route::patch(
            '/inventory/{inventory}/threshold',
            [
                InventoryController::class,
                'updateThreshold'
            ]
        )->middleware(
            'permission:inventory.manage'
        );

        Route::get(
            '/collections',
            [
                CollectionController::class,
                'index'
            ]
        )->middleware(
            'permission:collections.view'
        );

        Route::get(
            '/collections/{collection}',
            [
                CollectionController::class,
                'show'
            ]
        )->middleware(
            'permission:collections.view'
        );

        Route::post(
            '/collections',
            [
                CollectionController::class,
                'store'
            ]
        )->middleware(
            'permission:collections.create'
        );

        Route::put(
            '/collections/{collection}',
            [
                CollectionController::class,
                'update'
            ]
        )->middleware(
            'permission:collections.update'
        );

        Route::delete(
            '/collections/{collection}',
            [
                CollectionController::class,
                'destroy'
            ]
        )->middleware(
            'permission:collections.delete'
        );


        /*
        |--------------------------------------------------------------------------
        | Customer Management
        |--------------------------------------------------------------------------
        |
        | customers.view
        | customers.manage
        |
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/customers',
            [
                CustomerController::class,
                'index'
            ]
        )->middleware(
            'permission:customers.view'
        );

        Route::get(
            '/customers/{user}',
            [
                CustomerController::class,
                'show'
            ]
        )->middleware(
            'permission:customers.view'
        );

        Route::patch(
            '/customers/{user}',
            [
                CustomerController::class,
                'update'
            ]
        )->middleware(
            'permission:customers.manage'
        );


        /*
        |--------------------------------------------------------------------------
        | Contact Content Management
        |--------------------------------------------------------------------------
        |
        | cms.manage
        |
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/contact-content',
            [
                ContactPageContentController::class,
                'show'
            ]
        )->middleware(
            'permission:cms.manage'
        );

        Route::put(
            '/contact-content',
            [
                ContactPageContentController::class,
                'update'
            ]
        )->middleware(
            'permission:cms.manage'
        );


        /*
        |--------------------------------------------------------------------------
        | FAQ Management
        |--------------------------------------------------------------------------
        |
        | cms.manage
        |
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/faqs',
            [
                FaqController::class,
                'show'
            ]
        )->middleware(
            'permission:cms.manage'
        );

        Route::put(
            '/faqs',
            [
                FaqController::class,
                'update'
            ]
        )->middleware(
            'permission:cms.manage'
        );

        /*
        |--------------------------------------------------------------------------
        | Page Management
        |--------------------------------------------------------------------------
        |
        | CMS page access is controlled by:
        |
        | cms.manage
        |
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/pages',
            [
                PageController::class,
                'index'
            ]
        )->middleware(
            'permission:cms.manage'
        );

        Route::get(
            '/pages/{page}',
            [
                PageController::class,
                'show'
            ]
        )->middleware(
            'permission:cms.manage'
        );

        Route::post(
            '/pages',
            [
                PageController::class,
                'store'
            ]
        )->middleware(
            'permission:cms.manage'
        );

        Route::patch(
            '/pages/{page}',
            [
                PageController::class,
                'update'
            ]
        )->middleware(
            'permission:cms.manage'
        );

        Route::delete(
            '/pages/{page}',
            [
                PageController::class,
                'destroy'
            ]
        )->middleware(
            'permission:cms.manage'
        );


        /*
        |--------------------------------------------------------------------------
        | Banner Management
        |--------------------------------------------------------------------------
        |
        | All banner operations require:
        |
        | cms.manage
        |
        |--------------------------------------------------------------------------
        */

        Route::middleware(
            'permission:cms.manage'
        )->group(function () {

            Route::get(
                '/banners',
                [
                    BannerController::class,
                    'index'
                ]
            );

            Route::post(
                '/banners',
                [
                    BannerController::class,
                    'store'
                ]
            );

            Route::get(
                '/banners/{banner}',
                [
                    BannerController::class,
                    'show'
                ]
            );

            Route::patch(
                '/banners/{banner}',
                [
                    BannerController::class,
                    'update'
                ]
            );

            Route::delete(
                '/banners/{banner}',
                [
                    BannerController::class,
                    'destroy'
                ]
            );

        });


        /*
        |--------------------------------------------------------------------------
        | Homepage Sections Management
        |--------------------------------------------------------------------------
        |
        | All homepage section operations require:
        |
        | cms.manage
        |
        |--------------------------------------------------------------------------
        */

        Route::middleware(
            'permission:cms.manage'
        )->group(function () {

            Route::get(
                '/inspiration-video',
                [InspirationVideoController::class, 'show']
            )->middleware(
                'permission:homepage.manage'
            );

            Route::post(
                '/inspiration-video',
                [InspirationVideoController::class, 'update']
            )->middleware(
                'permission:homepage.manage'
            );

          Route::post(
              '/homepage-media',
              [
                  HomepageMediaController::class,
                  'store'
              ]
          )->middleware(
              'permission:homepage.manage'
          );

            Route::get(
                '/homepage-sections',
                [
                    HomepageSectionController::class,
                    'index'
                ]
            );

            Route::post(
                '/homepage-sections',
                [
                    HomepageSectionController::class,
                    'store'
                ]
            );

            Route::get(
                '/homepage-sections/{homepageSection}',
                [
                    HomepageSectionController::class,
                    'show'
                ]
            );

            Route::patch(
                '/homepage-sections/{homepageSection}',
                [
                    HomepageSectionController::class,
                    'update'
                ]
            );

            Route::delete(
                '/homepage-sections/{homepageSection}',
                [
                    HomepageSectionController::class,
                    'destroy'
                ]
            );

        });


        /*
        |--------------------------------------------------------------------------
        | Menu Management
        |--------------------------------------------------------------------------
        |
        | All menu and menu-item operations require:
        |
        | menus.manage
        |
        |--------------------------------------------------------------------------
        */

        Route::middleware(
            'permission:menus.manage'
        )->group(function () {

            /*
            |--------------------------------------------------------------------------
            | Menus
            |--------------------------------------------------------------------------
            */

            Route::get(
                '/menus',
                [
                    MenuController::class,
                    'index'
                ]
            );

            Route::post(
                '/menus',
                [
                    MenuController::class,
                    'store'
                ]
            );

            Route::get(
                '/menus/{menu}',
                [
                    MenuController::class,
                    'show'
                ]
            );

            Route::patch(
                '/menus/{menu}',
                [
                    MenuController::class,
                    'update'
                ]
            );

            Route::delete(
                '/menus/{menu}',
                [
                    MenuController::class,
                    'destroy'
                ]
            );


            /*
            |--------------------------------------------------------------------------
            | Menu Items
            |--------------------------------------------------------------------------
            */

            Route::post(
                '/menus/{menu}/items',
                [
                    MenuItemController::class,
                    'store'
                ]
            );

            Route::get(
                '/menus/{menu}/items/{item}',
                [
                    MenuItemController::class,
                    'show'
                ]
            );

            Route::patch(
                '/menus/{menu}/items/{item}',
                [
                    MenuItemController::class,
                    'update'
                ]
            );

            Route::delete(
                '/menus/{menu}/items/{item}',
                [
                    MenuItemController::class,
                    'destroy'
                ]
            );

        });


        /*
        |--------------------------------------------------------------------------
        | Shipping Methods Management
        |--------------------------------------------------------------------------
        |
        | shipping_methods.view
        | shipping_methods.create
        | shipping_methods.update
        | shipping_methods.delete
        |
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/shipping-methods',
            [
                ShippingMethodController::class,
                'index'
            ]
        )->middleware(
            'permission:shipping_methods.view'
        );

        Route::get(
            '/shipping-methods/{shippingMethod}',
            [
                ShippingMethodController::class,
                'show'
            ]
        )->middleware(
            'permission:shipping_methods.view'
        );

        Route::post(
            '/shipping-methods',
            [
                ShippingMethodController::class,
                'store'
            ]
        )->middleware(
            'permission:shipping_methods.create'
        );

        Route::put(
            '/shipping-methods/{shippingMethod}',
            [
                ShippingMethodController::class,
                'update'
            ]
        )->middleware(
            'permission:shipping_methods.update'
        );

        Route::delete(
            '/shipping-methods/{shippingMethod}',
            [
                ShippingMethodController::class,
                'destroy'
            ]
        )->middleware(
            'permission:shipping_methods.delete'
        );


        /*
        |--------------------------------------------------------------------------
        | Stores Management
        |--------------------------------------------------------------------------
        |
        | stores.view
        | stores.create
        | stores.update
        | stores.delete
        |
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/stores',
            [
                StoreController::class,
                'index'
            ]
        )->middleware(
            'permission:stores.view'
        );

        Route::get(
            '/stores/{store}',
            [
                StoreController::class,
                'show'
            ]
        )->middleware(
            'permission:stores.view'
        );

        Route::post(
            '/stores',
            [
                StoreController::class,
                'store'
            ]
        )->middleware(
            'permission:stores.create'
        );

        Route::put(
            '/stores/{store}',
            [
                StoreController::class,
                'update'
            ]
        )->middleware(
            'permission:stores.update'
        );

        Route::delete(
            '/stores/{store}',
            [
                StoreController::class,
                'destroy'
            ]
        )->middleware(
            'permission:stores.delete'
        );


        /*
        |--------------------------------------------------------------------------
        | Contact Messages
        |--------------------------------------------------------------------------
        |
        | Messages submitted through the storefront Contact Us form.
        |
        |--------------------------------------------------------------------------
        */

        Route::middleware(
            'permission:contact_messages.view'
        )->group(function () {

            Route::get(
                '/contact-messages',
                [
                    ContactMessageController::class,
                    'index'
                ]
            );

            Route::get(
                '/contact-messages/{contactMessage}',
                [
                    ContactMessageController::class,
                    'show'
                ]
            );

        });


        Route::patch(
            '/contact-messages/{contactMessage}/status',
            [
                ContactMessageController::class,
                'updateStatus'
            ]
        )->middleware(
            'permission:contact_messages.view'
        );

        Route::middleware(
            'permission:contact_messages.view'
        )->group(function () {
            Route::get(
                '/support-tickets',
                [SupportTicketController::class, 'index']
            );

            Route::get(
                '/support-tickets/{supportTicket}',
                [SupportTicketController::class, 'show']
            );

            Route::patch(
                '/support-tickets/{supportTicket}/close',
                [SupportTicketController::class, 'close']
            )->middleware('role:super_admin,admin');
        });

        Route::middleware('permission:contact_messages.view')
            ->group(function () {
                Route::post(
                    '/support-tickets/{supportTicket}/messages',
                    [SupportTicketController::class, 'reply']
                )->middleware('permission:support_tickets.reply');
            });

        /*
        |--------------------------------------------------------------------------
        | Activity Logs
        |--------------------------------------------------------------------------
        |
        | Read-only audit log access.
        |
        | activity_logs.view
        |
        |--------------------------------------------------------------------------
        */

        Route::middleware(
            'permission:activity_logs.view'
        )->group(function () {

            Route::get(
                '/activity-logs',
                [
                    ActivityLogController::class,
                    'index'
                ]
            );

            Route::get(
                '/activity-logs/actions',
                [
                    ActivityLogController::class,
                    'actions'
                ]
            );

            Route::get(
                '/activity-logs/{activityLog}',
                [
                    ActivityLogController::class,
                    'show'
                ]
            );

        });

    });


/*
|--------------------------------------------------------------------------
| Admin User / Staff Management API
|--------------------------------------------------------------------------
|
| Only Super Admin can manage staff users.
|
| Managed roles:
|
| admin
| accountant
| support
|
| super_admin is intentionally excluded from normal CRUD operations.
|
|--------------------------------------------------------------------------
*/

Route::prefix('admin')
    ->middleware([
        'auth:sanctum',
        'active',
        'role:super_admin',
    ])
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        | List Staff Users
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/users',
            [
                UserController::class,
                'index'
            ]
        );


        /*
        |--------------------------------------------------------------------------
        | Staff User Details
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/users/{user}',
            [
                UserController::class,
                'show'
            ]
        );


        /*
        |--------------------------------------------------------------------------
        | Create Staff User
        |--------------------------------------------------------------------------
        */

        Route::post(
            '/users',
            [
                UserController::class,
                'store'
            ]
        );


        /*
        |--------------------------------------------------------------------------
        | Update Staff User
        |--------------------------------------------------------------------------
        */

        Route::patch(
            '/users/{user}',
            [
                UserController::class,
                'update'
            ]
        );


        /*
        |--------------------------------------------------------------------------
        | Delete Staff User
        |--------------------------------------------------------------------------
        */

        Route::delete(
            '/users/{user}',
            [
                UserController::class,
                'destroy'
            ]
        );

    });


/*
|--------------------------------------------------------------------------
| Role Management API
|--------------------------------------------------------------------------
|
| Only Super Admin can manage roles.
|
|--------------------------------------------------------------------------
*/

Route::prefix('admin')
    ->middleware([
        'auth:sanctum',
        'active',
        'role:super_admin',
    ])
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        | List Roles
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/roles',
            [
                RoleController::class,
                'index'
            ]
        );


        /*
        |--------------------------------------------------------------------------
        | Role Details
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/roles/{role}',
            [
                RoleController::class,
                'show'
            ]
        );


        /*
        |--------------------------------------------------------------------------
        | Create Role
        |--------------------------------------------------------------------------
        */

        Route::post(
            '/roles',
            [
                RoleController::class,
                'store'
            ]
        );


        /*
        |--------------------------------------------------------------------------
        | Update Role
        |--------------------------------------------------------------------------
        */

        Route::patch(
            '/roles/{role}',
            [
                RoleController::class,
                'update'
            ]
        );


        /*
        |--------------------------------------------------------------------------
        | Delete Role
        |--------------------------------------------------------------------------
        */

        Route::delete(
            '/roles/{role}',
            [
                RoleController::class,
                'destroy'
            ]
        );

    });


/*
|--------------------------------------------------------------------------
| Permission Management API
|--------------------------------------------------------------------------
|
| Only Super Admin can manage permissions.
|
|--------------------------------------------------------------------------
*/

Route::prefix('admin')
    ->middleware([
        'auth:sanctum',
        'active',
        'role:super_admin',
    ])
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        | List Permissions
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/permissions',
            [
                PermissionController::class,
                'index'
            ]
        );


        /*
        |--------------------------------------------------------------------------
        | Permission Details
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/permissions/{permission}',
            [
                PermissionController::class,
                'show'
            ]
        );


        /*
        |--------------------------------------------------------------------------
        | Create Permission
        |--------------------------------------------------------------------------
        */

        Route::post(
            '/permissions',
            [
                PermissionController::class,
                'store'
            ]
        );


        /*
        |--------------------------------------------------------------------------
        | Update Permission
        |--------------------------------------------------------------------------
        */

        Route::patch(
            '/permissions/{permission}',
            [
                PermissionController::class,
                'update'
            ]
        );


        /*
        |--------------------------------------------------------------------------
        | Delete Permission
        |--------------------------------------------------------------------------
        */

        Route::delete(
            '/permissions/{permission}',
            [
                PermissionController::class,
                'destroy'
            ]
        );

    });

/*
|--------------------------------------------------------------------------
| Permission-aware Admin Dashboard
|--------------------------------------------------------------------------
*/

Route::get(
    '/admin/dashboard',
    [
        DashboardController::class,
        'index',
    ]
)->middleware([
    'auth:sanctum',
    'active',
    'role:super_admin,admin,accountant,support',
]);
