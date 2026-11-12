<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ProductExcelImportApplyService
{
    public function __construct(
        private readonly ProductExcelImportPreviewService $previewService,
        private readonly InventoryService $inventoryService,
    ) {}

    /**
     * Revalidate and apply an Excel file atomically.
     *
     * Never trust a preview response returned by the browser.
     *
     * @return array<string, mixed>
     */
    public function apply(string $path, User $actor): array
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException(
                'فایل Excel قابل خواندن نیست.'
            );
        }

        return DB::transaction(function () use ($path, $actor): array {
            /*
             * Establish a consistent lock order:
             * products -> variants -> inventories.
             *
             * Preview runs only after locks have been acquired.
             */
            Product::query()
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);

            ProductVariant::query()
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);

            Inventory::query()
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);

            /*
             * Re-read the Excel and validate its original prices,
             * variant ownership, stock and reserved quantities.
             */
            $preview = $this->previewService->preview($path);

            if (
                ($preview['valid'] ?? false) !== true ||
                !empty($preview['errors'])
            ) {
                throw new RuntimeException(
                    'فایل Excel دارای خطا یا اطلاعات قدیمی است. '
                    . 'هیچ تغییری اعمال نشد. '
                    . 'فایل جدید دریافت کنید و دوباره بررسی کنید.'
                );
            }

            $changes = $preview['changes'] ?? [];

            if (!is_array($changes)) {
                throw new RuntimeException(
                    'ساختار تغییرات Excel نامعتبر است.'
                );
            }

            $productPriceChanges = 0;
            $variantPriceChanges = 0;
            $stockChanges = 0;

            /*
             * Product prices are saved independently of variants.
             * Inherited variant prices must remain NULL.
             */
            foreach ($changes as $change) {
                if (
                    ($change['variant_id'] ?? null) !== null ||
                    ($change['price_changed'] ?? false) !== true
                ) {
                    continue;
                }

                $productId = (int) $change['product_id'];

                $product = Product::query()
                    ->whereKey($productId)
                    ->firstOrFail();

                $expected = $change['old_stored_price'];
                $current = (float) $product->price;

                if (
                    $expected === null ||
                    abs($current - (float) $expected) >= 0.005
                ) {
                    throw new RuntimeException(
                        "قیمت محصول {$productId} تغییر کرده است. "
                        . 'هیچ تغییری اعمال نشد.'
                    );
                }

                $newPrice = $change['new_stored_price'];

                if ($newPrice === null) {
                    throw new RuntimeException(
                        "قیمت جدید محصول {$productId} نامعتبر است."
                    );
                }

                $product->price = $newPrice;
                $product->save();

                $productPriceChanges++;
            }

            /*
             * Only variants with explicit new stored prices
             * are updated. Inherited variants are never saved
             * with an artificial price override.
             */
            foreach ($changes as $change) {
                $variantId = $change['variant_id'] ?? null;

                if (
                    $variantId === null ||
                    ($change['price_changed'] ?? false) !== true
                ) {
                    continue;
                }

                $newPrice = $change['new_stored_price'] ?? null;

                if ($newPrice === null) {
                    continue;
                }

                $variant = ProductVariant::query()
                    ->whereKey((int) $variantId)
                    ->firstOrFail();

                if (
                    (int) $variant->product_id !==
                    (int) $change['product_id']
                ) {
                    throw new RuntimeException(
                        "مالکیت مدل {$variantId} نامعتبر است."
                    );
                }

                $expected = $change['old_stored_price'] ?? null;
                $current = $variant->price;

                if ($expected === null) {
                    if ($current !== null) {
                        throw new RuntimeException(
                            "قیمت مدل {$variantId} تغییر کرده است."
                        );
                    }
                } elseif (
                    $current === null ||
                    abs((float) $current - (float) $expected) >= 0.005
                ) {
                    throw new RuntimeException(
                        "قیمت مدل {$variantId} تغییر کرده است."
                    );
                }

                $variant->price = $newPrice;
                $variant->save();

                $variantPriceChanges++;
            }

            /*
             * Every stock change passes through InventoryService.
             * This preserves inventory movements and activity logs.
             */
            foreach ($changes as $change) {
                if (($change['stock_changed'] ?? false) !== true) {
                    continue;
                }

                $variantId = $change['variant_id'] ?? null;

                if ($variantId === null) {
                    throw new RuntimeException(
                        'تغییر موجودی برای محصول اصلی مجاز نیست.'
                    );
                }

                $variant = ProductVariant::query()
                    ->whereKey((int) $variantId)
                    ->firstOrFail();

                if (
                    (int) $variant->product_id !==
                    (int) $change['product_id'] ||
                    !$variant->requiresInventory()
                ) {
                    throw new RuntimeException(
                        "موجودی مدل {$variantId} قابل ویرایش نیست."
                    );
                }

                $inventory = Inventory::query()
                    ->where('product_variant_id', $variant->id)
                    ->firstOrFail();

                $oldQuantity = $change['old_quantity'] ?? null;
                $reserved = $change['reserved_quantity'] ?? null;
                $newQuantity = $change['new_quantity'] ?? null;

                if (
                    $oldQuantity === null ||
                    $reserved === null ||
                    $newQuantity === null ||
                    (int) $inventory->quantity !== (int) $oldQuantity ||
                    (int) $inventory->reserved_quantity !== (int) $reserved
                ) {
                    throw new RuntimeException(
                        "موجودی مدل {$variantId} تغییر کرده است. "
                        . 'هیچ تغییری اعمال نشد.'
                    );
                }

                $delta = (int) $newQuantity - (int) $oldQuantity;

                if ($delta === 0) {
                    continue;
                }

                $this->inventoryService->adjustStock(
                    $inventory,
                    $delta,
                    $actor,
                    'Excel bulk import - row ' . $change['row']
                );

                $stockChanges++;
            }

            return [
                'success' => true,
                'message' => 'تغییرات Excel با موفقیت ثبت شدند.',
                'summary' => [
                    'rows' => count($changes),
                    'product_price_updates' => $productPriceChanges,
                    'variant_price_updates' => $variantPriceChanges,
                    'stock_updates' => $stockChanges,
                ],
            ];
        }, 3);
    }
}