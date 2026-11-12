<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

/**
 * Read-only analysis of the TKB three-sheet Excel export.
 * No models are saved, and no inventory movements are created.
 */
final class ProductExcelImportPreviewService
{
    private const MAX_BYTES = 10 * 1024 * 1024;
    private const MAX_ROWS = 20000;
    private const MAX_PERCENT = 1000;
    private const MAX_PRICE = 999999999999.99;

    /** @return array<string, mixed> */
    public function preview(string $path): array
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('فایل Excel قابل خواندن نیست.');
        }

        $size = filesize($path);
        if ($size === false || $size === 0 || $size > self::MAX_BYTES) {
            throw new RuntimeException('حجم فایل Excel نامعتبر است (حداکثر ۱۰ مگابایت).');
        }



        $book = (new \PhpOffice\PhpSpreadsheet\Reader\Xlsx())->load($path);
        try {
            if ($book->getSheetNames() !== [
                'تنظیمات قیمت',
                'محصولات و موجودی',
                'راهنمای استفاده',
            ]) {
                throw new RuntimeException('نام یا ترتیب شیت‌های فایل معتبر نیست.');
            }

            $settings = $book->getSheetByName('تنظیمات قیمت');
            $sheet = $book->getSheetByName('محصولات و موجودی');
            if (!$settings instanceof Worksheet || !$sheet instanceof Worksheet) {
                throw new RuntimeException('شیت‌های مورد انتظار پیدا نشدند.');
            }

            foreach ([
                'A5' => 'نام محصول', 'B5' => 'مدل / کد کالا',
                'C5' => 'وضعیت', 'D5' => 'قیمت فعلی',
                'E5' => 'قیمت پیشنهادی', 'F5' => 'قیمت دستی',
                'G5' => 'موجودی فعلی', 'H5' => 'موجودی رزرو',
                'I5' => 'موجودی جدید', 'J5' => 'نوع ردیف',
                'K5' => 'product_id', 'L5' => 'variant_id',
                'M5' => 'original_product_price',
                'N5' => 'original_variant_price',
            ] as $cell => $expected) {
                if ($sheet->getCell($cell)->getValue() !== $expected) {
                    throw new RuntimeException("ساختار ستون {$cell} تغییر کرده است.");
                }
            }

            $percent = $this->number($settings->getCell('B4')->getValue(), 'درصد تغییر', true);
            $rounding = $this->number($settings->getCell('B6')->getValue(), 'مقدار گرد کردن', false);
            if ($percent < -100 || $percent > self::MAX_PERCENT) {
                throw new RuntimeException('درصد تغییر باید بین منفی ۱۰۰ و مثبت ۱۰۰۰ باشد.');
            }
            if ($rounding < 1 || floor($rounding) !== $rounding || $rounding > 1000000000) {
                throw new RuntimeException('گرد کردن باید عدد صحیح مثبت و حداکثر یک میلیارد باشد.');
            }

            $last = $sheet->getHighestDataRow();
            if ($last < 5 || $last > self::MAX_ROWS + 5) {
                throw new RuntimeException('تعداد ردیف‌های Excel خارج از محدوده مجاز است.');
            }

            // Active Eloquent scopes exclude soft-deleted products and variants.
            $products = Product::query()->with('variants.inventory')->get()->keyBy('id');
            $variants = ProductVariant::query()->with(['inventory', 'product'])->get()->keyBy('id');
            $seenProducts = [];
            $seenVariants = [];
            $changes = [];
            $errors = [];

            for ($row = 6; $row <= $last; $row++) {
                try {
                    $productId = $this->id($sheet->getCell("K{$row}")->getValue(), "شناسه محصول، ردیف {$row}");
                    $rawVariantId = $sheet->getCell("L{$row}")->getValue();
                    $isVariant = !$this->blank($rawVariantId);
                    $variantId = $isVariant ? $this->id($rawVariantId, "شناسه مدل، ردیف {$row}") : null;
                    $product = $products->get($productId);
                    if (!$product) {
                        throw new RuntimeException('محصول دیگر موجود نیست یا حذف شده است.');
                    }

                    $label = $sheet->getCell("J{$row}")->getValue();
                    if ($label !== ($isVariant ? '↳ مدل' : 'محصول اصلی')) {
                        throw new RuntimeException('نوع ردیف با شناسه‌ها همخوانی ندارد.');
                    }

                    if ($isVariant) {
                        if (isset($seenVariants[$variantId])) {
                            throw new RuntimeException('شناسه مدل تکراری است.');
                        }
                        $seenVariants[$variantId] = true;
                        $variant = $variants->get($variantId);
                        if (!$variant || (int) $variant->product_id !== $productId) {
                            throw new RuntimeException('مدل به این محصول تعلق ندارد یا حذف شده است.');
                        }
                        $effectivePrice = (float) ($variant->price ?? $product->price);
                        $storedVariantPrice = $variant->price === null ? null : (float) $variant->price;
                        $originalVariantPrice = $sheet->getCell("N{$row}")->getValue();
                        if (($storedVariantPrice === null) !== $this->blank($originalVariantPrice)) {
                            throw new RuntimeException('وضعیت قیمت اختصاصی مدل از زمان خروجی تغییر کرده است.');
                        }
                        if ($storedVariantPrice !== null && !$this->equalMoney(
                            $storedVariantPrice,
                            $this->number($originalVariantPrice, 'قیمت اختصاصی اولیه', false)
                        )) {
                            throw new RuntimeException('قیمت اختصاصی مدل از زمان خروجی تغییر کرده است.');
                        }
                        if ((string) $sheet->getCell("B{$row}")->getValue() !== (string) $variant->sku) {
                            throw new RuntimeException('کد مدل با اطلاعات فعلی همخوانی ندارد.');
                        }
                    } else {
                        if (isset($seenProducts[$productId])) {
                            throw new RuntimeException('شناسه محصول اصلی تکراری است.');
                        }
                        $seenProducts[$productId] = true;
                        $variant = null;
                        $effectivePrice = (float) $product->price;
                        if (!$this->blank($sheet->getCell("N{$row}")->getValue())) {
                            throw new RuntimeException('قیمت اختصاصی در ردیف محصول اصلی مجاز نیست.');
                        }
                        if ((string) $sheet->getCell("B{$row}")->getValue() !== (string) $product->slug) {
                            throw new RuntimeException('اسلاگ محصول با اطلاعات فعلی همخوانی ندارد.');
                        }
                    }

                    $base = (float) $product->price;
                    $originalBase = $this->number($sheet->getCell("M{$row}")->getValue(), 'قیمت پایه اولیه', false);
                    $originalEffective = $this->number($sheet->getCell("D{$row}")->getValue(), 'قیمت فعلی در فایل', false);
                    if (!$this->equalMoney($originalBase, $base) || !$this->equalMoney($originalEffective, $effectivePrice)) {
                        throw new RuntimeException('قیمت محصول از زمان دانلود Excel تغییر کرده؛ فایل جدید دریافت کنید.');
                    }

                    $manualRaw = $sheet->getCell("F{$row}")->getValue();
                    $stockRaw = $sheet->getCell("I{$row}")->getValue();
                    $manual = $this->blank($manualRaw) ? null : $this->number($manualRaw, 'قیمت دستی', false);
                    $stock = $this->blank($stockRaw) ? null : $this->number($stockRaw, 'موجودی جدید', false);

                    if ($manual !== null && $manual > self::MAX_PRICE) {
                        throw new RuntimeException('قیمت دستی بیش از سقف مجاز است.');
                    }
                    if ($stock !== null && (floor($stock) !== $stock || $stock > 2147483647)) {
                        throw new RuntimeException('موجودی باید عدد صحیح در محدوده مجاز باشد.');
                    }

                    $oldQuantity = null;
                    $reserved = null;
                    if ($isVariant && $variant->inventory !== null) {
                        $oldQuantity = (int) $variant->inventory->quantity;
                        $reserved = (int) $variant->inventory->reserved_quantity;
                        if ((int) $this->number($sheet->getCell("G{$row}")->getValue(), 'موجودی اولیه', false) !== $oldQuantity ||
                            (int) $this->number($sheet->getCell("H{$row}")->getValue(), 'موجودی رزرو اولیه', false) !== $reserved) {
                            throw new RuntimeException('موجودی یا رزرو از زمان خروجی تغییر کرده است.');
                        }
                    } elseif (!$this->blank($sheet->getCell("G{$row}")->getValue()) || !$this->blank($sheet->getCell("H{$row}")->getValue())) {
                        throw new RuntimeException('اطلاعات موجودی این ردیف با دیتابیس سازگار نیست.');
                    }

                    if ($stock !== null) {
                        if (!$isVariant || !$variant->requiresInventory() || $variant->inventory === null) {
                            throw new RuntimeException('موجودی این ردیف قابل ویرایش نیست.');
                        }
                        if ($stock < $reserved) {
                            throw new RuntimeException('موجودی جدید نمی‌تواند کمتر از موجودی رزروشده باشد.');
                        }
                    }

                    // Inherited variant pricing is driven by the product row only.
                    // A manual override on an inherited variant creates its own price.
                    $oldStoredPrice = $isVariant ? ($variant->price === null ? null : (float) $variant->price) : $base;
                    $suggested = $this->roundedPrice($effectivePrice, $percent, $rounding);
                    if ($isVariant && $oldStoredPrice === null && $manual === null) {
                        $newStoredPrice = null;
                        $newEffectivePrice = null; // Resolved after product prices are computed.
                    } else {
                        $newStoredPrice = $manual ?? $suggested;
                        $newEffectivePrice = $newStoredPrice;
                    }

                    $priceChanged = $newStoredPrice !== null &&
                        ($oldStoredPrice === null || !$this->equalMoney($oldStoredPrice, $newStoredPrice));
                    $stockChanged = $stock !== null && $stock !== $oldQuantity;
                    $changes[] = [
                        'row' => $row,
                        'product_id' => $productId,
                        'variant_id' => $variantId,
                        'sku' => (string) $sheet->getCell("B{$row}")->getValue(),
                        'price_source' => $manual !== null ? 'manual' : ($isVariant && $oldStoredPrice === null ? 'inherited' : 'percentage'),
                        'old_stored_price' => $oldStoredPrice,
                        'new_stored_price' => $newStoredPrice,
                        'old_effective_price' => $effectivePrice,
                        'new_effective_price' => $newEffectivePrice,
                        'old_quantity' => $oldQuantity,
                        'new_quantity' => $stock,
                        'reserved_quantity' => $reserved,
                        'price_changed' => $priceChanged,
                        'stock_changed' => $stockChanged,
                    ];
                } catch (\Throwable $exception) {
                    $errors[] = ['row' => $row, 'message' => $exception->getMessage()];
                    if (count($errors) >= 100) {
                        break;
                    }
                }
            }

            if (count($seenProducts) !== $products->count() || count($seenVariants) !== $variants->count()) {
                $errors[] = ['row' => null, 'message' => 'فایل کامل نیست یا بعضی ردیف‌های محصولات/مدل‌ها حذف شده‌اند. خروجی جدید دریافت کنید.'];
            }

            // Resolve derived prices of inherited variants from the proposed product price.
            $productPrices = [];
            foreach ($changes as $change) {
                if ($change['variant_id'] === null) {
                    $productPrices[$change['product_id']] = $change['new_stored_price'];
                }
            }
            foreach ($changes as &$change) {
                if ($change['variant_id'] !== null && $change['new_stored_price'] === null) {
                    $change['new_effective_price'] = $productPrices[$change['product_id']] ?? null;
                    if ($change['new_effective_price'] === null) {
                        $errors[] = ['row' => $change['row'], 'message' => 'قیمت جدید محصول پایه قابل تعیین نیست.'];
                    }
                    $change['price_changed'] = $change['new_effective_price'] !== null &&
                        !$this->equalMoney($change['old_effective_price'], $change['new_effective_price']);
                }
            }
            unset($change);

            return [
                'valid' => $errors === [],
                'can_apply' => false, // Preview only; Apply must be a separate, revalidated API.
                'settings' => ['percent' => $percent, 'rounding' => (int) $rounding],
                'summary' => [
                    'rows' => $last - 5,
                    'price_changes' => count(array_filter($changes, static fn ($c) => $c['price_changed'])),
                    'stock_changes' => count(array_filter($changes, static fn ($c) => $c['stock_changed'])),
                    'unchanged_rows' => count(array_filter($changes, static fn ($c) => !$c['price_changed'] && !$c['stock_changed'])),
                    'errors' => count($errors),
                ],
                'changes' => $changes,
                'errors' => $errors,
            ];
        } finally {
            $book->disconnectWorksheets();
        }
    }

    private function blank(mixed $value): bool
    {
        return $value === null || $value === '';
    }

    private function id(mixed $value, string $label): int
    {
        if (!is_numeric($value) || (float) $value < 1 || floor((float) $value) !== (float) $value || (float) $value > PHP_INT_MAX) {
            throw new RuntimeException("{$label} نامعتبر است.");
        }
        return (int) $value;
    }

    private function number(mixed $value, string $label, bool $signed): float
    {
        if (!is_int($value) && !is_float($value) && !(is_string($value) && is_numeric($value))) {
            throw new RuntimeException("{$label} باید عدد باشد.");
        }
        $number = (float) $value;
        if (!is_finite($number) || (!$signed && $number < 0)) {
            throw new RuntimeException("{$label} نامعتبر است.");
        }
        return $number;
    }

    private function equalMoney(float $a, float $b): bool
    {
        return abs($a - $b) < 0.005;
    }

    private function roundedPrice(float $price, float $percent, float $rounding): float
    {
        $computed = round(($price * (1 + $percent / 100)) / $rounding, 0) * $rounding;
        if (!is_finite($computed) || $computed < 0 || $computed > self::MAX_PRICE) {
            throw new RuntimeException('قیمت محاسبه‌شده خارج از محدوده مجاز است.');
        }
        return round($computed, 2);
    }
}
