<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Roles
        |--------------------------------------------------------------------------
        */

        Schema::create('roles', function (Blueprint $table) {
            $table->id();

            $table->string('name', 100);
            $table->string('slug', 100)->unique('roles_slug_unique');
            $table->text('description')->nullable();

            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | Permissions
        |--------------------------------------------------------------------------
        */

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();

            $table->string('name', 150);
            $table->string('slug', 150)->unique('permissions_slug_unique');
            $table->text('description')->nullable();

            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | Role User
        |--------------------------------------------------------------------------
        */

        Schema::create('role_user', function (Blueprint $table) {
            $table->foreignId('role_id')
                ->constrained('roles', 'id', 'role_user_role_id_foreign')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users', 'id', 'role_user_user_id_foreign')
                ->cascadeOnDelete();

            $table->primary(
                ['role_id', 'user_id'],
                'role_user_primary'
            );
        });

        /*
        |--------------------------------------------------------------------------
        | Permission Role
        |--------------------------------------------------------------------------
        */

        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('permission_id')
                ->constrained('permissions', 'id', 'permission_role_permission_id_foreign')
                ->cascadeOnDelete();

            $table->foreignId('role_id')
                ->constrained('roles', 'id', 'permission_role_role_id_foreign')
                ->cascadeOnDelete();

            $table->primary(
                ['permission_id', 'role_id'],
                'permission_role_primary'
            );
        });

        /*
        |--------------------------------------------------------------------------
        | Categories
        |--------------------------------------------------------------------------
        */

Schema::create('categories', function (Blueprint $table) {

    $table->id();


    /*
    |--------------------------------------------------------------------------
    | Hierarchy
    |--------------------------------------------------------------------------
    */

    $table->foreignId('parent_id')
        ->nullable()
        ->constrained(
            'categories',
            'id',
            'categories_parent_id_foreign'
        )
        ->nullOnDelete();



    /*
    |--------------------------------------------------------------------------
    | Basic Information
    |--------------------------------------------------------------------------
    */

    $table->string('name', 150);

    $table->string('slug', 180)
        ->unique('categories_slug_unique');

    $table->text('description')
        ->nullable();



    /*
    |--------------------------------------------------------------------------
    | Media
    |--------------------------------------------------------------------------
    */

    $table->string('image', 500)
        ->nullable();



    /*
    |--------------------------------------------------------------------------
    | Management
    |--------------------------------------------------------------------------
    */

    $table->boolean('is_active')
        ->default(true);

    $table->unsignedInteger('sort_order')
        ->default(0);



    /*
    |--------------------------------------------------------------------------
    | Audit
    |--------------------------------------------------------------------------
    */

    $table->foreignId('created_by')
        ->nullable()
        ->constrained(
            'users',
            'id',
            'categories_created_by_foreign'
        )
        ->nullOnDelete();


    $table->foreignId('updated_by')
        ->nullable()
        ->constrained(
            'users',
            'id',
            'categories_updated_by_foreign'
        )
        ->nullOnDelete();



    $table->timestamps();

  });
/*
|--------------------------------------------------------------------------
| Products
|--------------------------------------------------------------------------
*/

Schema::create('products', function (Blueprint $table) {
    $table->id();

    $table->foreignId('category_id')
        ->constrained(
            'categories',
            'id',
            'products_category_id_foreign'
        )
        ->restrictOnDelete();

    $table->string('name', 255);

    $table->string('slug', 280)
        ->unique('products_slug_unique');

    $table->text('short_description')->nullable();
    $table->text('description')->nullable();

    $table->string('brand', 150)->nullable();

    // Base product price.
    // Variant price may override this value.
    $table->decimal('price', 12, 2)->default(0);

    $table->enum('status', [
        'draft',
        'active',
        'inactive',
    ])->default('draft');

    $table->boolean('is_featured')->default(false);

    // Laravel Soft Deletes.
    $table->softDeletes();

    $table->timestamps();
});


/*
|--------------------------------------------------------------------------
| Product Variants
|--------------------------------------------------------------------------
*/

Schema::create('product_variants', function (Blueprint $table) {
    $table->id();

    $table->foreignId('product_id')
        ->constrained(
            'products',
            'id',
            'product_variants_product_id_foreign'
        )
        ->restrictOnDelete();

    $table->string('sku', 100)
        ->unique('product_variants_sku_unique');

    // NULL = use products.price
    // Value = override products.price
    $table->decimal('price', 12, 2)->nullable();

    $table->enum('status', [
    'active',
    'made_to_order',
    'sold',
    'inactive',
   ])->default('active');

    $table->unsignedInteger('sort_order')->default(0);

    // Laravel Soft Deletes.
    $table->softDeletes();

    $table->timestamps();
});

        /*
        |--------------------------------------------------------------------------
        | Attributes
        |--------------------------------------------------------------------------
        */

        Schema::create('attributes', function (Blueprint $table) {
            $table->id();

            $table->string('name', 100);
            $table->string('slug', 120)
                ->unique('attributes_slug_unique');

            $table->enum('type', [
                'text',
                'color',
                'number',
                'select',
            ])->default('select');

            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | Attribute Values
        |--------------------------------------------------------------------------
        */

        Schema::create('attribute_values', function (Blueprint $table) {
            $table->id();

            $table->foreignId('attribute_id')
                ->constrained(
                    'attributes',
                    'id',
                    'attribute_values_attribute_id_foreign'
                )
                ->cascadeOnDelete();

            $table->string('value', 150);

            $table->timestamps();

            $table->unique(
                ['attribute_id', 'value'],
                'attribute_values_attr_value_unique'
            );
        });

        /*
        |--------------------------------------------------------------------------
        | Variant Attribute Values
        |--------------------------------------------------------------------------
        */

        Schema::create('variant_attribute_values', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_variant_id')
                ->constrained(
                    'product_variants',
                    'id',
                    'variant_attr_values_variant_id_foreign'
                )
                ->cascadeOnDelete();

            $table->foreignId('attribute_value_id')
                ->constrained(
                    'attribute_values',
                    'id',
                    'variant_attr_values_value_id_foreign'
                )
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique(
                ['product_variant_id', 'attribute_value_id'],
                'variant_attr_value_unique'
            );
        });

        /*
        |--------------------------------------------------------------------------
        | Product Images
        |--------------------------------------------------------------------------
        */

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained(
                    'products',
                    'id',
                    'product_images_product_id_foreign'
                )
                ->cascadeOnDelete();

            $table->foreignId('product_variant_id')
                ->nullable()
                ->constrained(
                    'product_variants',
                    'id',
                    'product_images_variant_id_foreign'
                )
                ->nullOnDelete();

            $table->string('image_path', 500);
            $table->string('alt_text', 255)->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);

            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | Collections
        |--------------------------------------------------------------------------
        */

        Schema::create('collections', function (Blueprint $table) {
            $table->id();

            $table->string('name', 150);
            $table->string('slug', 180)
                ->unique('collections_slug_unique');

            $table->text('description')->nullable();
            $table->string('image', 500)->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | Collection Product
        |--------------------------------------------------------------------------
        */

        Schema::create('collection_product', function (Blueprint $table) {
            $table->foreignId('collection_id')
                ->constrained(
                    'collections',
                    'id',
                    'collection_product_collection_foreign'
                )
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->constrained(
                    'products',
                    'id',
                    'collection_product_product_foreign'
                )
                ->cascadeOnDelete();

            $table->primary(
                ['collection_id', 'product_id'],
                'collection_product_primary'
            );
        });

/*
|--------------------------------------------------------------------------
| Inventory
|--------------------------------------------------------------------------
*/

Schema::create('inventories', function (Blueprint $table) {
    $table->id();

    $table->foreignId('product_variant_id')
        ->unique('inventories_variant_id_unique')
        ->constrained(
            'product_variants',
            'id',
            'inventories_variant_id_foreign'
        )
        ->cascadeOnDelete();

    // Physical stock.
    $table->unsignedInteger('quantity')->default(0);

    // Stock reserved by orders.
    $table->unsignedInteger('reserved_quantity')->default(0);

    // Admin low-stock alert threshold.
    $table->unsignedInteger('low_stock_threshold')->default(5);

    $table->timestamps();
});
        /*
        |--------------------------------------------------------------------------
        | Shipping Methods
        |--------------------------------------------------------------------------
        */

        Schema::create('shipping_methods', function (Blueprint $table) {
            $table->id();

            $table->string('name', 150);
            $table->text('description')->nullable();

            $table->decimal('price', 12, 2)->default(0);

            $table->string('estimated_days', 100)->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | Carts
        |--------------------------------------------------------------------------
        */

        Schema::create('carts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained(
                    'users',
                    'id',
                    'carts_user_id_foreign'
                )
                ->nullOnDelete();

            $table->string('session_id', 255)
                ->nullable()
                ->index('carts_session_id_index');

            $table->enum('status', [
                'active',
                'converted',
                'abandoned',
            ])->default('active');

            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | Cart Items
        |--------------------------------------------------------------------------
        */

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cart_id')
                ->constrained(
                    'carts',
                    'id',
                    'cart_items_cart_id_foreign'
                )
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->constrained(
                    'products',
                    'id',
                    'cart_items_product_id_foreign'
                )
                ->restrictOnDelete();

            // NULL for products without variants.
            $table->foreignId('product_variant_id')
                ->nullable()
                ->constrained(
                    'product_variants',
                    'id',
                    'cart_items_variant_id_foreign'
                )
                ->restrictOnDelete();

            $table->unsignedInteger('quantity')->default(1);

            // Snapshot of current cart price.
            $table->decimal('unit_price', 12, 2);

            $table->timestamps();

            $table->index(
                ['cart_id', 'product_id'],
                'cart_items_cart_product_index'
            );

            $table->index(
                ['cart_id', 'product_variant_id'],
                'cart_items_cart_variant_index'
            );
        });

        /*
        |--------------------------------------------------------------------------
        | Orders
        |--------------------------------------------------------------------------
        */

        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained(
                    'users',
                    'id',
                    'orders_user_id_foreign'
                )
                ->restrictOnDelete();

            $table->string('order_number', 50)
                ->unique('orders_order_number_unique');

            $table->enum('status', [
                'pending_payment',
                'paid',
                'processing',
                'shipped',
                'delivered',
                'cancelled',
                'returned',
            ])->default('pending_payment');

            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('shipping_cost', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            $table->string('currency', 10);

            $table->foreignId('shipping_method_id')
                ->nullable()
                ->constrained(
                    'shipping_methods',
                    'id',
                    'orders_shipping_method_id_foreign'
                )
                ->nullOnDelete();

            // Address snapshots.
            $table->json('shipping_address');
            $table->json('billing_address')->nullable();

            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | Order Items
        |--------------------------------------------------------------------------
        */

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained(
                    'orders',
                    'id',
                    'order_items_order_id_foreign'
                )
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->constrained(
                    'products',
                    'id',
                    'order_items_product_id_foreign'
                )
                ->restrictOnDelete();

            // NULL for products without variants.
            $table->foreignId('product_variant_id')
                ->nullable()
                ->constrained(
                    'product_variants',
                    'id',
                    'order_items_variant_id_foreign'
                )
                ->restrictOnDelete();

            // Historical snapshots.
            $table->string('product_name', 255);
            $table->string('sku', 100)->nullable();

            $table->unsignedInteger('quantity');

            $table->decimal('unit_price', 12, 2);
            $table->decimal('total', 12, 2);

            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | Payments
        |--------------------------------------------------------------------------
        */

        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained(
                    'orders',
                    'id',
                    'payments_order_id_foreign'
                )
                ->cascadeOnDelete();

            $table->string('transaction_id', 255)->nullable();

            $table->decimal('amount', 12, 2);

            $table->string('currency', 10);

            $table->string('method', 50);

            $table->enum('status', [
                'pending',
                'paid',
                'failed',
                'refunded',
            ])->default('pending');

            $table->timestamp('paid_at')->nullable();

            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | Order Status History
        |--------------------------------------------------------------------------
        */

        Schema::create('order_status_history', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained(
                    'orders',
                    'id',
                    'order_history_order_id_foreign'
                )
                ->cascadeOnDelete();

            $table->enum('from_status', [
                'pending_payment',
                'paid',
                'processing',
                'shipped',
                'delivered',
                'cancelled',
                'returned',
            ])->nullable();

            $table->enum('to_status', [
                'pending_payment',
                'paid',
                'processing',
                'shipped',
                'delivered',
                'cancelled',
                'returned',
            ]);

            $table->foreignId('changed_by')
                ->nullable()
                ->constrained(
                    'users',
                    'id',
                    'order_history_changed_by_foreign'
                )
                ->nullOnDelete();

            $table->text('note')->nullable();

            $table->timestamp('created_at')->useCurrent();
        });

        /*
        |--------------------------------------------------------------------------
        | Inventory Movements
        |--------------------------------------------------------------------------
        */

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('inventory_id')
                ->constrained(
                    'inventories',
                    'id',
                    'inventory_movements_inventory_id_foreign'
                )
                ->cascadeOnDelete();

            $table->enum('type', [
                'purchase',
                'sale',
                'reservation',
                'release',
                'return',
                'adjustment',
            ]);

            // Change in actual physical stock.
            $table->integer('quantity_delta')->default(0);

            // Change in reserved stock.
            $table->integer('reserved_delta')->default(0);

            $table->foreignId('order_id')
                ->nullable()
                ->constrained(
                    'orders',
                    'id',
                    'inventory_movements_order_id_foreign'
                )
                ->nullOnDelete();

            $table->foreignId('order_item_id')
                ->nullable()
                ->constrained(
                    'order_items',
                    'id',
                    'inventory_movements_order_item_id_foreign'
                )
                ->nullOnDelete();

            $table->text('note')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained(
                    'users',
                    'id',
                    'inventory_movements_created_by_foreign'
                )
                ->nullOnDelete();

            $table->timestamp('created_at')->useCurrent();

            $table->index(
                ['inventory_id', 'created_at'],
                'inventory_movements_inventory_created_index'
            );
        });

        /*
        |--------------------------------------------------------------------------
        | Addresses
        |--------------------------------------------------------------------------
        */

        Schema::create('addresses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained(
                    'users',
                    'id',
                    'addresses_user_id_foreign'
                )
                ->cascadeOnDelete();

            $table->string('title', 100)->nullable();

            $table->string('recipient_name', 200);
            $table->string('phone', 30);

            $table->text('address');

            $table->string('city', 100);
            $table->string('state', 100)->nullable();
            $table->string('postal_code', 30);
            $table->string('country', 100);

            $table->boolean('is_default')->default(false);

            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | Wishlists
        |--------------------------------------------------------------------------
        */

        Schema::create('wishlists', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->unique('wishlists_user_id_unique')
                ->constrained(
                    'users',
                    'id',
                    'wishlists_user_id_foreign'
                )
                ->cascadeOnDelete();

            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | Wishlist Items
        |--------------------------------------------------------------------------
        */

        Schema::create('wishlist_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('wishlist_id')
                ->constrained(
                    'wishlists',
                    'id',
                    'wishlist_items_wishlist_id_foreign'
                )
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->constrained(
                    'products',
                    'id',
                    'wishlist_items_product_id_foreign'
                )
                ->cascadeOnDelete();

            $table->timestamp('created_at')->useCurrent();

            $table->unique(
                ['wishlist_id', 'product_id'],
                'wishlist_items_unique'
            );
        });

        /*
        |--------------------------------------------------------------------------
        | CMS Pages
        |--------------------------------------------------------------------------
        */

        Schema::create('pages', function (Blueprint $table) {
            $table->id();

            $table->string('title', 255);
            $table->string('slug', 280)
                ->unique('pages_slug_unique');

            $table->text('content');

            $table->enum('status', [
                'draft',
                'published',
            ])->default('draft');

            $table->foreignId('created_by')
                ->nullable()
                ->constrained(
                    'users',
                    'id',
                    'pages_created_by_foreign'
                )
                ->nullOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained(
                    'users',
                    'id',
                    'pages_updated_by_foreign'
                )
                ->nullOnDelete();

            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | Banners
        |--------------------------------------------------------------------------
        */

        Schema::create('banners', function (Blueprint $table) {
            $table->id();

            $table->string('title', 255)->nullable();
            $table->string('subtitle', 500)->nullable();

            $table->string('image', 500);
            $table->string('mobile_image', 500)->nullable();

            $table->string('link', 500)->nullable();
            $table->string('button_text', 100)->nullable();

            $table->unsignedInteger('sort_order')->default(0);

            $table->boolean('is_active')->default(true);

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | Menus
        |--------------------------------------------------------------------------
        */

        Schema::create('menus', function (Blueprint $table) {
            $table->id();

            $table->string('name', 150);
            $table->string('slug', 180)
                ->unique('menus_slug_unique');

            $table->string('location', 100);

            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | Menu Items
        |--------------------------------------------------------------------------
        */

        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('menu_id')
                ->constrained(
                    'menus',
                    'id',
                    'menu_items_menu_id_foreign'
                )
                ->cascadeOnDelete();

            $table->foreignId('parent_id')
                ->nullable()
                ->constrained(
                    'menu_items',
                    'id',
                    'menu_items_parent_id_foreign'
                )
                ->cascadeOnDelete();

            $table->string('title', 150);

            $table->enum('type', [
                'url',
                'category',
                'product',
                'collection',
                'page',
            ])->default('url');

            $table->string('url', 500)->nullable();

            $table->foreignId('category_id')
                ->nullable()
                ->constrained(
                    'categories',
                    'id',
                    'menu_items_category_id_foreign'
                )
                ->nullOnDelete();

            $table->foreignId('product_id')
                ->nullable()
                ->constrained(
                    'products',
                    'id',
                    'menu_items_product_id_foreign'
                )
                ->nullOnDelete();

            $table->foreignId('collection_id')
                ->nullable()
                ->constrained(
                    'collections',
                    'id',
                    'menu_items_collection_id_foreign'
                )
                ->nullOnDelete();

            $table->foreignId('page_id')
                ->nullable()
                ->constrained(
                    'pages',
                    'id',
                    'menu_items_page_id_foreign'
                )
                ->nullOnDelete();

            $table->unsignedInteger('sort_order')->default(0);

            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | Homepage Sections
        |--------------------------------------------------------------------------
        */

        Schema::create('homepage_sections', function (Blueprint $table) {
            $table->id();

            // Fixed homepage section identifier.
            $table->string('section_key', 100)
                ->unique('homepage_sections_key_unique');

            $table->string('title', 255)->nullable();
            $table->string('subtitle', 500)->nullable();

            $table->text('content')->nullable();

            $table->string('image', 500)->nullable();
            $table->string('mobile_image', 500)->nullable();

            $table->string('link', 500)->nullable();
            $table->string('button_text', 100)->nullable();

            $table->foreignId('category_id')
                ->nullable()
                ->constrained(
                    'categories',
                    'id',
                    'homepage_sections_category_id_foreign'
                )
                ->nullOnDelete();

            $table->foreignId('product_id')
                ->nullable()
                ->constrained(
                    'products',
                    'id',
                    'homepage_sections_product_id_foreign'
                )
                ->nullOnDelete();

            $table->foreignId('collection_id')
                ->nullable()
                ->constrained(
                    'collections',
                    'id',
                    'homepage_sections_collection_id_foreign'
                )
                ->nullOnDelete();

            $table->boolean('is_active')->default(true);

            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | Stores
        |--------------------------------------------------------------------------
        */

        Schema::create('stores', function (Blueprint $table) {
            $table->id();

            $table->string('name', 150);
            $table->string('slug', 180)
                ->unique('stores_slug_unique');

            $table->text('description')->nullable();

            $table->text('address');
            $table->string('city', 100);

            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->json('opening_hours')->nullable();

            $table->string('image', 500)->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | Settings
        |--------------------------------------------------------------------------
        */

        Schema::create('settings', function (Blueprint $table) {
            $table->id();

            $table->string('setting_key', 150)
                ->unique('settings_key_unique');

            $table->text('setting_value')->nullable();

            $table->string('type', 50)->default('string');
            $table->string('group_name', 100)->nullable();

            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | Activity Logs
        |--------------------------------------------------------------------------
        */

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained(
                    'users',
                    'id',
                    'activity_logs_user_id_foreign'
                )
                ->nullOnDelete();

            $table->string('action', 100);

            $table->text('description')->nullable();

            $table->json('properties')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('stores');
        Schema::dropIfExists('homepage_sections');
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menus');
        Schema::dropIfExists('banners');
        Schema::dropIfExists('pages');
        Schema::dropIfExists('wishlist_items');
        Schema::dropIfExists('wishlists');
        Schema::dropIfExists('addresses');
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('order_status_history');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
        Schema::dropIfExists('shipping_methods');
        Schema::dropIfExists('inventories');
        Schema::dropIfExists('collection_product');
        Schema::dropIfExists('collections');
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('variant_attribute_values');
        Schema::dropIfExists('attribute_values');
        Schema::dropIfExists('attributes');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};