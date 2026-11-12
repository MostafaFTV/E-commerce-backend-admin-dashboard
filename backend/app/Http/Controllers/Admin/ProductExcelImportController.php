<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ProductExcelImportApplyService;
use App\Services\ProductExcelImportPreviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Reader\Exception as SpreadsheetReaderException;
use RuntimeException;

class ProductExcelImportController extends Controller
{
    public function preview(
        Request $request,
        ProductExcelImportPreviewService $service
    ): JsonResponse {
        abort_unless(
            $request->user()?->hasPermission('products.update') &&
            $request->user()?->hasPermission('inventory.manage'),
            403
        );

        $validated = $request->validate([
            'file' => [
                'required',
                'file',
                'mimes:xlsx',
                'max:5120',
            ],
        ]);

        $file = $validated['file'];
        $path = $file->getRealPath();

        if ($path === false || !is_file($path)) {
            throw ValidationException::withMessages([
                'file' => 'فایل بارگذاری‌شده قابل خواندن نیست.',
            ]);
        }

        try {
            $result = $service->preview($path);
        } catch (
            SpreadsheetReaderException |
            RuntimeException $exception
        ) {
            throw ValidationException::withMessages([
                'file' => 'ساختار فایل Excel نامعتبر است یا قابل پردازش نیست.',
            ]);
        }

        return response()->json(
            $result,
            200,
            [
                'Cache-Control' => 'private, no-store',
            ]
        );
    }

    public function apply(
        Request $request,
        ProductExcelImportApplyService $service
    ): JsonResponse {
        abort_unless(
            $request->user()?->hasPermission('products.update') &&
            $request->user()?->hasPermission('inventory.manage'),
            403
        );

        $validated = $request->validate([
            'file' => [
                'required',
                'file',
                'mimes:xlsx',
                'max:5120',
            ],
        ]);

        $path = $validated['file']->getRealPath();

        if ($path === false || !is_file($path)) {
            throw ValidationException::withMessages([
                'file' => 'فایل بارگذاری‌شده قابل خواندن نیست.',
            ]);
        }

        try {
            $result = $service->apply(
                $path,
                $request->user()
            );
        } catch (SpreadsheetReaderException $exception) {
            throw ValidationException::withMessages([
                'file' => 'فایل Excel نامعتبر یا غیرقابل خواندن است.',
            ]);
        } catch (RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => 'فایل Excel نامعتبر یا قدیمی است. هیچ تغییری اعمال نشد.',
            ], 409, [
                'Cache-Control' => 'private, no-store',
            ]);
        }

        return response()->json($result, 200, [
            'Cache-Control' => 'private, no-store',
        ]);
    }

}