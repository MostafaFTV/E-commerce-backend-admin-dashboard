<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ProductExcelExportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProductExcelController extends Controller
{
    public function export(
        Request $request,
        ProductExcelExportService $exportService
    ): BinaryFileResponse {
        abort_unless(
            $request->user()?->hasPermission('products.view') &&
            $request->user()?->hasPermission('inventory.view'),
            403
        );

        $directory = storage_path('app/private/excel-exports');

        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            abort(500, 'Unable to prepare export directory.');
        }

        $filename = 'TKB-Products-'
            . now()->format('Ymd-His')
            . '-'
            . bin2hex(random_bytes(4))
            . '.xlsx';

        $path = $directory . DIRECTORY_SEPARATOR . $filename;

        try {
            $exportService->export($path);

            return response()
                ->download(
                    $path,
                    $filename,
                    [
                        'Content-Type' =>
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'Cache-Control' => 'private, no-store',
                    ]
                )
                ->deleteFileAfterSend(true);
        } catch (\Throwable $exception) {
            if (is_file($path)) {
                @unlink($path);
            }

            throw $exception;
        }
    }
}