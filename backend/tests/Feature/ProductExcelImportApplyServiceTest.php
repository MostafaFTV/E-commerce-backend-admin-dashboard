<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\ProductExcelExportService;
use App\Services\ProductExcelImportApplyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use RuntimeException;
use Tests\TestCase;

class ProductExcelImportApplyServiceTest extends TestCase
{
    use RefreshDatabase;

    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        parent::tearDown();
    }

    private function makeFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'tkb-apply-');

        if ($path === false) {
            throw new RuntimeException(
                'Cannot create temporary Excel file.'
            );
        }

        $this->temporaryFiles[] = $path;

        app(ProductExcelExportService::class)->export($path);

        return $path;
    }

    private function editFile(string $path, callable $edit): void
    {
        $book = (new XlsxReader())->load($path);

        try {
            $edit($book);
            (new XlsxWriter($book))->save($path);
        } finally {
            $book->disconnectWorksheets();
        }
    }

    private function fixtures(): array
    {
        $product = Product::factory()->create([
            'price' => 100000,
        ]);

        $inherited = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => null,
        ]);

        $priced = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => 200000,
        ]);

        $inventory = Inventory::factory()->create([
            'product_variant_id' => $inherited->id,
            'quantity' => 25,
            'reserved_quantity' => 5,
            'low_stock_threshold' => 3,
        ]);

        return compact(
            'product',
            'inherited',
            'priced',
            'inventory'
        );
    }

    private function rowForVariant(
        $sheet,
        int $variantId
    ): int {
        for (
            $row = 6;
            $row <= $sheet->getHighestDataRow();
            $row++
        ) {
            if (
                (int) $sheet->getCell("L{$row}")->getValue()
                === $variantId
            ) {
                return $row;
            }
        }

        throw new RuntimeException(
            "Variant {$variantId} not found in Excel."
        );
    }

    public function test_apply_updates_prices_and_preserves_inheritance(): void
    {
        $data = $this->fixtures();
        $actor = User::factory()->create();
        $file = $this->makeFile();

        $this->editFile($file, function ($book): void {
            $book->getSheetByName('تنظیمات قیمت')
                ->setCellValue('B4', 10);
        });

        $result = app(ProductExcelImportApplyService::class)
            ->apply($file, $actor);

        $this->assertTrue($result['success']);

        $this->assertEquals(
            110000,
            $data['product']->fresh()->price
        );

        $this->assertNull(
            $data['inherited']->fresh()->price
        );

        $this->assertEquals(
            110000,
            $data['inherited']->fresh()->effectivePrice()
        );

        $this->assertEquals(
            220000,
            $data['priced']->fresh()->price
        );

        $this->assertSame(
            1,
            $result['summary']['product_price_updates']
        );

        $this->assertSame(
            1,
            $result['summary']['variant_price_updates']
        );

        $this->assertSame(
            0,
            InventoryMovement::count()
        );
    }

    public function test_apply_adjusts_inventory_and_records_movement(): void
    {
        $data = $this->fixtures();
        $actor = User::factory()->create();
        $file = $this->makeFile();

        $variantId = $data['inherited']->id;

        $this->editFile(
            $file,
            function ($book) use ($variantId): void {
                $sheet = $book->getSheetByName(
                    'محصولات و موجودی'
                );

                $row = $this->rowForVariant(
                    $sheet,
                    $variantId
                );

                $sheet->setCellValue("I{$row}", 30);
            }
        );

        $result = app(ProductExcelImportApplyService::class)
            ->apply($file, $actor);

        $this->assertTrue($result['success']);

        $this->assertSame(
            30,
            $data['inventory']->fresh()->quantity
        );

        $this->assertSame(
            5,
            $data['inventory']->fresh()->reserved_quantity
        );

        $this->assertSame(
            1,
            InventoryMovement::count()
        );

        $movement = InventoryMovement::firstOrFail();

        $this->assertSame(
            $data['inventory']->id,
            $movement->inventory_id
        );

        $this->assertSame(
            \App\Enums\InventoryMovementType::ADJUSTMENT,
            $movement->type
        );

        $this->assertSame(
            5,
            $movement->quantity_delta
        );

        $this->assertSame(
            0,
            $movement->reserved_delta
        );

        $this->assertSame(
            $actor->id,
            $movement->created_by
        );

        $this->assertSame(
            1,
            $result['summary']['stock_updates']
        );
    }

    public function test_reapplying_stale_file_rolls_back(): void
    {
        $data = $this->fixtures();
        $actor = User::factory()->create();
        $file = $this->makeFile();

        $this->editFile($file, function ($book): void {
            $book->getSheetByName('تنظیمات قیمت')
                ->setCellValue('B4', 10);
        });

        $service = app(ProductExcelImportApplyService::class);

        $service->apply($file, $actor);

        $afterFirst = [
            'product_price' => $data['product']->fresh()->price,
            'priced_variant' => $data['priced']->fresh()->price,
            'inherited_variant' => $data['inherited']->fresh()->price,
            'quantity' => $data['inventory']->fresh()->quantity,
            'movements' => InventoryMovement::count(),
        ];

        try {
            $service->apply($file, $actor);

            $this->fail(
                'Reapplying a stale Excel file should fail.'
            );
        } catch (RuntimeException $exception) {
            $this->assertNotEmpty(
                $exception->getMessage()
            );
        }

        $this->assertSame(
            $afterFirst,
            [
                'product_price' => $data['product']->fresh()->price,
                'priced_variant' => $data['priced']->fresh()->price,
                'inherited_variant' => $data['inherited']->fresh()->price,
                'quantity' => $data['inventory']->fresh()->quantity,
                'movements' => InventoryMovement::count(),
            ]
        );
    }

    public function test_invalid_stock_rejects_entire_file(): void
    {
        $data = $this->fixtures();
        $actor = User::factory()->create();
        $file = $this->makeFile();

        $variantId = $data['inherited']->id;

        $this->editFile(
            $file,
            function ($book) use ($variantId): void {
                $book->getSheetByName('تنظیمات قیمت')
                    ->setCellValue('B4', 10);

                $sheet = $book->getSheetByName(
                    'محصولات و موجودی'
                );

                $row = $this->rowForVariant(
                    $sheet,
                    $variantId
                );

                $sheet->setCellValue("I{$row}", 2);
            }
        );

        try {
            app(ProductExcelImportApplyService::class)
                ->apply($file, $actor);

            $this->fail(
                'Stock below reserved quantity must be rejected.'
            );
        } catch (RuntimeException $exception) {
            $this->assertNotEmpty(
                $exception->getMessage()
            );
        }

        $this->assertEquals(
            100000,
            $data['product']->fresh()->price
        );

        $this->assertEquals(
            200000,
            $data['priced']->fresh()->price
        );

        $this->assertNull(
            $data['inherited']->fresh()->price
        );

        $this->assertSame(
            25,
            $data['inventory']->fresh()->quantity
        );

        $this->assertSame(
            5,
            $data['inventory']->fresh()->reserved_quantity
        );

        $this->assertSame(
            0,
            InventoryMovement::count()
        );
    }

    public function test_transaction_rolls_back_prices_when_inventory_adjustment_fails(): void
    {
        $data = $this->fixtures();
        $actor = User::factory()->create();
        $file = $this->makeFile();

        $variantId = $data['inherited']->id;

        $this->editFile(
            $file,
            function ($book) use ($variantId): void {
                $book->getSheetByName('تنظیمات قیمت')
                    ->setCellValue('B4', 10);

                $sheet = $book->getSheetByName(
                    'محصولات و موجودی'
                );

                $row = $this->rowForVariant(
                    $sheet,
                    $variantId
                );

                $sheet->setCellValue("I{$row}", 30);
            }
        );

        $inventoryService = \Mockery::mock(
            \App\Services\InventoryService::class
        );

        $inventoryService
            ->shouldReceive('adjustStock')
            ->once()
            ->andThrow(
                new RuntimeException(
                    'Simulated inventory adjustment failure.'
                )
            );

        $this->app->instance(
            \App\Services\InventoryService::class,
            $inventoryService
        );

        try {
            app(ProductExcelImportApplyService::class)
                ->apply($file, $actor);

            $this->fail(
                'Apply should fail when inventory adjustment fails.'
            );
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Simulated inventory adjustment failure.',
                $exception->getMessage()
            );
        }

        $this->assertEquals(
            100000,
            $data['product']->fresh()->price
        );

        $this->assertEquals(
            200000,
            $data['priced']->fresh()->price
        );

        $this->assertNull(
            $data['inherited']->fresh()->price
        );

        $this->assertSame(
            25,
            $data['inventory']->fresh()->quantity
        );

        $this->assertSame(
            5,
            $data['inventory']->fresh()->reserved_quantity
        );

        $this->assertSame(
            0,
            InventoryMovement::count()
        );
    }

    public function test_apply_rejects_stale_inventory_without_partial_updates(): void
    {
        $data = $this->fixtures();
        $actor = User::factory()->create();
        $file = $this->makeFile();

        $variantId = $data['inherited']->id;

        $this->editFile(
            $file,
            function ($book) use ($variantId): void {
                $book->getSheetByName('تنظیمات قیمت')
                    ->setCellValue('B4', 10);

                $sheet = $book->getSheetByName(
                    'محصولات و موجودی'
                );

                $row = $this->rowForVariant(
                    $sheet,
                    $variantId
                );

                $sheet->setCellValue("I{$row}", 30);
            }
        );

        // Simulate an inventory update after Excel export.
        $data['inventory']->update([
            'quantity' => 26,
        ]);

        try {
            app(ProductExcelImportApplyService::class)
                ->apply($file, $actor);

            $this->fail(
                'Stale inventory should reject the complete import.'
            );
        } catch (RuntimeException $exception) {
            $this->assertNotEmpty($exception->getMessage());
        }

        $this->assertEquals(
            100000,
            $data['product']->fresh()->price
        );

        $this->assertEquals(
            200000,
            $data['priced']->fresh()->price
        );

        $this->assertNull(
            $data['inherited']->fresh()->price
        );

        $this->assertSame(
            26,
            $data['inventory']->fresh()->quantity
        );

        $this->assertSame(
            5,
            $data['inventory']->fresh()->reserved_quantity
        );

        $this->assertSame(
            0,
            InventoryMovement::count()
        );
    }

}