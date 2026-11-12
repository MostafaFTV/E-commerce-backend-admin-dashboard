<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\ProductExcelExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Tests\TestCase;

class ProductExcelImportApplyApiTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/admin/products/excel/apply';

    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        parent::tearDown();
    }

    private function staff(
        string $roleSlug = 'admin',
        array $permissions = [
            'products.update',
            'inventory.manage',
        ]
    ): User {
        $role = Role::firstOrCreate(
            ['slug' => $roleSlug],
            ['name' => Str::headline($roleSlug)]
        );

        foreach ($permissions as $slug) {
            $permission = Permission::firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => Str::headline(
                        str_replace('.', ' ', $slug)
                    ),
                ]
            );

            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

        $user = User::factory()->create([
            'status' => UserStatus::ACTIVE,
        ]);

        $user->roles()->attach($role->id);

        return $user;
    }

    private function excel(?float $percent = null): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'tkb-apply-api-');

        if ($path === false) {
            throw new \RuntimeException('Temporary file error.');
        }

        $this->files[] = $path;

        app(ProductExcelExportService::class)->export($path);

        if ($percent !== null) {
            $book = (new Xlsx())->load($path);

            try {
                $book->getSheetByName('تنظیمات قیمت')
                    ->setCellValue('B4', $percent);

                (new XlsxWriter($book))->save($path);
            } finally {
                $book->disconnectWorksheets();
            }
        }

        return new UploadedFile(
            $path,
            'TKB-Products.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }

    private function upload(User $user, UploadedFile $file)
    {
        return $this->actingAs($user, 'sanctum')
            ->post(self::URL, [
                'file' => $file,
            ], [
                'Accept' => 'application/json',
            ]);
    }

    public function test_guest_cannot_apply(): void
    {
        $this->postJson(self::URL)->assertUnauthorized();
    }

    public function test_accountant_cannot_apply(): void
    {
        $this->actingAs(
            $this->staff('accountant'),
            'sanctum'
        )->postJson(self::URL)->assertForbidden();
    }

    public function test_missing_product_permission_is_forbidden(): void
    {
        $this->actingAs(
            $this->staff('admin', ['inventory.manage']),
            'sanctum'
        )->postJson(self::URL)->assertForbidden();
    }

    public function test_missing_inventory_permission_is_forbidden(): void
    {
        $this->actingAs(
            $this->staff('admin', ['products.update']),
            'sanctum'
        )->postJson(self::URL)->assertForbidden();
    }

    public function test_missing_file_is_rejected(): void
    {
        $this->actingAs($this->staff(), 'sanctum')
            ->postJson(self::URL)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    }

    public function test_invalid_file_is_rejected(): void
    {
        $file = UploadedFile::fake()->createWithContent(
            'invalid.xlsx',
            'not an Excel workbook'
        );

        $this->upload($this->staff(), $file)
            ->assertUnprocessable();
    }

    public function test_authorized_apply_updates_product_price(): void
    {
        $product = Product::factory()->create([
            'price' => 100000,
        ]);

        $file = $this->excel(10);

        $response = $this->upload($this->staff(), $file);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'summary.product_price_updates',
                1
            );

        $this->assertEquals(
            110000,
            $product->fresh()->price
        );
    }

    public function test_stale_excel_is_rejected_without_changes(): void
    {
        $product = Product::factory()->create([
            'price' => 100000,
        ]);

        $file = $this->excel(10);

        $this->upload($this->staff(), $file)
            ->assertOk();

        $response = $this->upload(
            $this->staff(),
            $file
        );

        $response->assertStatus(409)
            ->assertJsonPath('success', false);

        $this->assertEquals(
            110000,
            $product->fresh()->price
        );
    }
}