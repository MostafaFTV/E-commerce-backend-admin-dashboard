<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class ProductExcelExportApiTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/admin/products/excel/export';

    private function staff(
        string $roleSlug,
        array $permissionSlugs = []
    ): User {
        $role = Role::create([
            'name' => Str::headline($roleSlug),
            'slug' => $roleSlug,
        ]);

        foreach ($permissionSlugs as $slug) {
            $permission = Permission::firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => Str::headline(
                        str_replace('.', ' ', $slug)
                    ),
                ]
            );

            $role->permissions()->attach($permission->id);
        }

        $user = User::factory()->create([
            'status' => UserStatus::ACTIVE,
        ]);

        $user->roles()->attach($role->id);

        return $user;
    }

    private function allPermissions(): array
    {
        return [
            'products.view',
            'inventory.view',
        ];
    }

    public function test_guest_cannot_download_excel(): void
    {
        $this->getJson(self::URL)
            ->assertUnauthorized();
    }

    public function test_non_admin_role_cannot_download_excel(): void
    {
        $user = $this->staff(
            'accountant',
            $this->allPermissions()
        );

        $this->actingAs($user, 'sanctum')
            ->getJson(self::URL)
            ->assertForbidden();
    }

    public function test_admin_without_products_permission_is_forbidden(): void
    {
        $user = $this->staff(
            'admin',
            ['inventory.view']
        );

        $this->actingAs($user, 'sanctum')
            ->getJson(self::URL)
            ->assertForbidden();
    }

    public function test_admin_without_inventory_permission_is_forbidden(): void
    {
        $user = $this->staff(
            'admin',
            ['products.view']
        );

        $this->actingAs($user, 'sanctum')
            ->getJson(self::URL)
            ->assertForbidden();
    }

    public function test_authorized_admin_downloads_valid_rtl_excel_without_database_changes(): void
    {
        $user = $this->staff(
            'admin',
            $this->allPermissions()
        );

        $before = [
            'products' => Product::count(),
            'variants' => ProductVariant::count(),
            'inventories' => Inventory::count(),
            'movements' => InventoryMovement::count(),
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->get(self::URL);

        $response->assertOk();
        $response->assertDownload();

        $binary = $response->baseResponse;

        $this->assertInstanceOf(
            BinaryFileResponse::class,
            $binary
        );

        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            (string) $response->headers->get('Content-Type')
        );

        $file = $binary->getFile()->getPathname();

        $this->assertFileExists($file);

        $workbook = null;

        try {
            $workbook = IOFactory::load($file);

            $this->assertSame(
                [
                    'تنظیمات قیمت',
                    'محصولات و موجودی',
                    'راهنمای استفاده',
                ],
                $workbook->getSheetNames()
            );

            foreach ($workbook->getAllSheets() as $sheet) {
                $this->assertTrue(
                    $sheet->getRightToLeft(),
                    'RTL missing: ' . $sheet->getTitle()
                );
            }

            $productsSheet = $workbook->getSheetByName(
                'محصولات و موجودی'
            );

            $this->assertNotNull($productsSheet);

            $this->assertSame(
                'نام محصول',
                $productsSheet->getCell('A5')->getValue()
            );

            $this->assertSame(
                'قیمت پیشنهادی',
                $productsSheet->getCell('E5')->getValue()
            );

            $this->assertSame(
                'موجودی جدید',
                $productsSheet->getCell('I5')->getValue()
            );

            foreach (['K', 'L', 'M', 'N'] as $column) {
                $this->assertFalse(
                    $productsSheet
                        ->getColumnDimension($column)
                        ->getVisible(),
                    "Technical column {$column} is visible."
                );
            }
        } finally {
            if ($workbook !== null) {
                $workbook->disconnectWorksheets();
            }

            if (is_file($file)) {
                unlink($file);
            }
        }

        $after = [
            'products' => Product::count(),
            'variants' => ProductVariant::count(),
            'inventories' => Inventory::count(),
            'movements' => InventoryMovement::count(),
        ];

        $this->assertSame(
            $before,
            $after,
            'Excel export unexpectedly changed database records.'
        );
    }
}