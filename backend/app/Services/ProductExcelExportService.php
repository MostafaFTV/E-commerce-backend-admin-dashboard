<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class ProductExcelExportService
{
    private const DARK = '243425';
    private const GREEN = 'E2F0DC';
    private const BLUE = 'E5EFF8';
    private const GRAY = 'F0F1EF';
    private const WHITE = 'FFFFFF';
    private const BORDER = 'DDE1DB';

    public function export(string $destination): void
    {
        $spreadsheet = new Spreadsheet();

        try {
            $spreadsheet->getDefaultStyle()->getFont()->setName('Tahoma')->setSize(11);
            $spreadsheet->getDefaultStyle()->getAlignment()
                ->setVertical(Alignment::VERTICAL_CENTER)
                ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            $settings = $spreadsheet->getActiveSheet();
            $settings->setTitle('تنظیمات قیمت');

            $products = $spreadsheet->createSheet();
            $products->setTitle('محصولات و موجودی');

            $guide = $spreadsheet->createSheet();
            $guide->setTitle('راهنمای استفاده');

            $this->buildSettings($settings);
            $this->buildProducts($products);
            $this->buildGuide($guide);

            $spreadsheet->setActiveSheetIndex(0);
            $writer = new Xlsx($spreadsheet);
            $writer->setPreCalculateFormulas(false);
            $writer->save($destination);
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    private function configure(Worksheet $sheet): void
    {
        $sheet->setRightToLeft(true);
        $sheet->getSheetView()->setZoomScale(85);
        $sheet->getDefaultRowDimension()->setRowHeight(27);
        $sheet->getPageSetup()->setFitToWidth(1);
    }

    private function title(Worksheet $sheet, string $range, string $text): void
    {
        $sheet->mergeCells($range);
        $sheet->setCellValue(explode(':', $range)[0], $text);
        $style = $sheet->getStyle($range);
        $style->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB(self::DARK);
        $style->getFont()->setBold(true)->setSize(15)
            ->getColor()->setRGB(self::WHITE);
        $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(32);
        $sheet->getRowDimension(2)->setRowHeight(28);
    }

    private function editable(Worksheet $sheet, string $coordinate): void
    {
        $sheet->getStyle($coordinate)->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB(self::GREEN);
    }

    private function readOnly(Worksheet $sheet, string $coordinate): void
    {
        $sheet->getStyle($coordinate)->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB(self::GRAY);
    }

    private function wholeNumberValidation(Worksheet $sheet, string $coordinate): void
    {
        $validation = $sheet->getCell($coordinate)->getDataValidation();
        $validation->setType(DataValidation::TYPE_WHOLE);
        $validation->setOperator(DataValidation::OPERATOR_GREATERTHANOREQUAL);
        $validation->setFormula1('0');
        $validation->setAllowBlank(true);
        $validation->setShowErrorMessage(true);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setErrorTitle('موجودی نامعتبر');
        $validation->setError('موجودی جدید باید عدد صحیح و نامنفی باشد.');
    }

    private function priceValidation(Worksheet $sheet, string $coordinate): void
    {
        $validation = $sheet->getCell($coordinate)->getDataValidation();
        $validation->setType(DataValidation::TYPE_DECIMAL);
        $validation->setOperator(DataValidation::OPERATOR_GREATERTHANOREQUAL);
        $validation->setFormula1('0');
        $validation->setAllowBlank(true);
        $validation->setShowErrorMessage(true);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setErrorTitle('قیمت نامعتبر');
        $validation->setError('قیمت دستی باید عدد نامنفی باشد.');
    }

    private function buildSettings(Worksheet $sheet): void
    {
        $this->configure($sheet);
        foreach (['A' => 33, 'B' => 23, 'C' => 56] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $this->title($sheet, 'A1:C2', 'تنظیمات عمومی قیمت محصولات');
        $sheet->setCellValue('A4', 'درصد تغییر قیمت');
        $sheet->setCellValue('B4', 0);
        $sheet->setCellValue('C4', 'افزایش: ۱۵ | کاهش: ۱۰- | بدون تغییر: صفر');
        $sheet->setCellValue('A6', 'گرد کردن قیمت به');
        $sheet->setCellValue('B6', 1);
        $sheet->setCellValue('C6', 'مثلاً عدد ۱۰۰۰ برای گرد کردن به نزدیک‌ترین هزار');
        $sheet->setCellValue('A8', 'زمان دریافت اطلاعات');
        $sheet->setCellValue('B8', now()->format('Y-m-d H:i:s'));

        foreach (['B4', 'B6'] as $cell) {
            $this->editable($sheet, $cell);
        }
        $sheet->getStyle('A4:C8')->getAlignment()->setWrapText(true);
        $sheet->getRowDimension(4)->setRowHeight(42);
        $sheet->getRowDimension(6)->setRowHeight(42);

        $validation = $sheet->getCell('B6')->getDataValidation();
        $validation->setType(DataValidation::TYPE_WHOLE);
        $validation->setOperator(DataValidation::OPERATOR_GREATERTHAN);
        $validation->setFormula1('0');
        $validation->setShowErrorMessage(true);
        $validation->setError('مقدار گرد کردن باید عدد صحیح بزرگ‌تر از صفر باشد.');
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
    }

    private function buildProducts(Worksheet $sheet): void
    {
        $this->configure($sheet);
        foreach ([
            'A' => 35, 'B' => 28, 'C' => 19, 'D' => 20, 'E' => 22,
            'F' => 19, 'G' => 17, 'H' => 17, 'I' => 19, 'J' => 16,
        ] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
        $this->title($sheet, 'A1:J2', 'مدیریت قیمت و موجودی محصولات');
        $sheet->mergeCells('A3:J3');
        $sheet->setCellValue('A3', 'فقط خانه‌های سبز را ویرایش کنید. خانه خالی یعنی بدون تغییر؛ موجودی رزرو قابل ویرایش نیست.');
        $sheet->getStyle('A3:J3')->getAlignment()->setWrapText(true);
        $sheet->getRowDimension(3)->setRowHeight(34);

        $headers = [
            'نام محصول', 'مدل / کد کالا', 'وضعیت', 'قیمت فعلی',
            'قیمت پیشنهادی', 'قیمت دستی', 'موجودی فعلی',
            'موجودی رزرو', 'موجودی جدید', 'نوع ردیف',
        ];
        foreach ($headers as $index => $label) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($index + 1) . '5', $label);
        }
        $headerStyle = $sheet->getStyle('A5:J5');
        $headerStyle->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB(self::DARK);
        $headerStyle->getFont()->setBold(true)->getColor()->setRGB(self::WHITE);
        $headerStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);
        $sheet->getRowDimension(5)->setRowHeight(42);

        foreach (['K', 'L', 'M', 'N'] as $column) {
            $sheet->getColumnDimension($column)->setVisible(false);
        }
        foreach (['K' => 'product_id', 'L' => 'variant_id', 'M' => 'original_product_price', 'N' => 'original_variant_price'] as $column => $label) {
            $sheet->setCellValue("{$column}5", $label);
        }

        $row = 6;
        Product::query()->with([
            'variants.inventory',
        ])->chunkById(200, function ($products) use ($sheet, &$row): void {
            foreach ($products as $product) {
                $this->writeProductRow($sheet, $row, $product);
                ++$row;
                foreach ($product->variants as $variant) {
                    $this->writeVariantRow($sheet, $row, $product, $variant);
                    ++$row;
                }
            }
        });

        if ($row > 6) {
            $last = $row - 1;
            $sheet->getStyle("D6:F{$last}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("E6:E{$last}")->getFill()->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB(self::BLUE);
            $sheet->getStyle("A6:J{$last}")->getBorders()->getBottom()
                ->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB(self::BORDER);
            $sheet->setAutoFilter("A5:J{$last}");
        }
        $sheet->freezePane('C6');
    }

    private function writeProductRow(Worksheet $sheet, int $row, Product $product): void
    {
        $sheet->setCellValue("A{$row}", $this->productName($product));
        $sheet->setCellValueExplicit("B{$row}", (string) $product->slug, DataType::TYPE_STRING);
        $sheet->setCellValue("C{$row}", $product->status?->value ?? '');
        $sheet->setCellValue("D{$row}", (float) $product->price);
        $sheet->setCellValue("E{$row}", $this->priceFormula($row));
        $sheet->setCellValue("J{$row}", 'محصول اصلی');
        $sheet->setCellValue("K{$row}", $product->id);
        $sheet->setCellValue("M{$row}", (float) $product->price);

        $this->readOnly($sheet, "A{$row}:J{$row}");
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $this->editable($sheet, "F{$row}");
        $this->priceValidation($sheet, "F{$row}");
        // Physical inventory belongs to the variant, never to the product row.
        $this->readOnly($sheet, "I{$row}");
    }

    private function writeVariantRow(Worksheet $sheet, int $row, Product $product, ProductVariant $variant): void
    {
        $sheet->setCellValue("A{$row}", '↳ ' . $this->productName($product));
        $sheet->setCellValueExplicit("B{$row}", (string) $variant->sku, DataType::TYPE_STRING);
        $sheet->setCellValue("C{$row}", $variant->status?->value ?? '');
        $sheet->setCellValue("D{$row}", (float) ($variant->price ?? $product->price));
        $sheet->setCellValue("E{$row}", $this->priceFormula($row));
        $sheet->setCellValue("G{$row}", $variant->inventory?->quantity);
        $sheet->setCellValue("H{$row}", $variant->inventory?->reserved_quantity);
        $sheet->setCellValue("J{$row}", '↳ مدل');
        $sheet->setCellValue("K{$row}", $product->id);
        $sheet->setCellValue("L{$row}", $variant->id);
        $sheet->setCellValue("M{$row}", (float) $product->price);
        if ($variant->price !== null) {
            $sheet->setCellValue("N{$row}", (float) $variant->price);
        }

        $this->editable($sheet, "F{$row}");
        $this->priceValidation($sheet, "F{$row}");
        if ($variant->requiresInventory() && $variant->inventory !== null) {
            $this->editable($sheet, "I{$row}");
            $this->wholeNumberValidation($sheet, "I{$row}");
        } else {
            $this->readOnly($sheet, "I{$row}");
        }
    }

    private function priceFormula(int $row): string
    {
        // A per-row manual price takes priority over the general percentage.
        return '=IF(F' . $row . '<>"",F' . $row
            . ',ROUND(D' . $row . '*(1+\'تنظیمات قیمت\'!$B$4/100)'
            . '/\'تنظیمات قیمت\'!$B$6,0)*\'تنظیمات قیمت\'!$B$6)';
    }

    private function productName(Product $product): string
    {
        $raw = $product->getRawOriginal('name');
        $names = is_array($raw) ? $raw : json_decode((string) $raw, true);
        if (!is_array($names)) {
            return (string) $raw;
        }
        return (string) ($names['fa'] ?? $names['en'] ?? '');
    }

    private function buildGuide(Worksheet $sheet): void
    {
        $this->configure($sheet);
        $sheet->getColumnDimension('A')->setWidth(29);
        $sheet->getColumnDimension('B')->setWidth(100);
        $this->title($sheet, 'A1:B2', 'راهنمای مدیریت قیمت و موجودی');

        $instructions = [
            ['مرحله ۱', 'فایل را از پنل مدیریت دانلود کنید.'],
            ['مرحله ۲', 'برای افزایش یا کاهش همه قیمت‌ها، درصد را در شیت «تنظیمات قیمت» وارد کنید.'],
            ['مرحله ۳', 'قیمت پیشنهادی به‌صورت خودکار در شیت «محصولات و موجودی» نمایش داده می‌شود.'],
            ['مرحله ۴', 'اگر قیمت محصول یا مدلی باید متفاوت باشد، فقط خانه سبز «قیمت دستی» را پر کنید.'],
            ['مرحله ۵', 'برای تغییر موجودی یک مدل، فقط خانه سبز «موجودی جدید» را پر کنید.'],
            ['مرحله ۶', 'فایل را ذخیره کنید و پس از فعال‌شدن قابلیت Import در پنل، آن را بارگذاری کنید.'],
            ['مرحله ۷', 'تغییرات را در پیش‌نمایش پنل بررسی و سپس تأیید کنید.'],
            ['قیمت محصول', 'قیمت محصول اصلی، پایه قیمت مدل‌هایی است که قیمت اختصاصی ندارند.'],
            ['قیمت مدل', 'مدل‌ها ممکن است قیمت اختصاصی داشته باشند یا از قیمت محصول اصلی استفاده کنند.'],
            ['قیمت دستی', 'قیمت دستی برای همان ردیف از تغییر درصدی اولویت بیشتری دارد.'],
            ['خانه خالی', 'خالی گذاشتن خانه سبز یعنی تغییر دستی برای آن ردیف انجام نشود.'],
            ['موجودی', 'موجودی جدید، موجودی فیزیکی کل است؛ نه تعداد قابل‌فروش پس از رزرو.'],
            ['موجودی رزرو', 'این مقدار فقط نمایشی است و نباید با فایل Excel تغییر داده شود.'],
            ['مدل سفارشی', 'مدل‌هایی که مدیریت انبار ندارند، خانه موجودی جدید خاکستری دارند.'],
            ['شناسه‌های فنی', 'شناسه‌های مخفی برای شناسایی دقیق ردیف‌ها هستند؛ آن‌ها را تغییر ندهید.'],
            ['نکته ایمنی', 'تا قبل از تأیید نهایی در پنل، هیچ قیمتی یا موجودی در سایت تغییر نمی‌کند.'],
        ];

        $row = 4;
        foreach ($instructions as [$heading, $description]) {
            $sheet->setCellValue("A{$row}", $heading);
            $sheet->setCellValue("B{$row}", $description);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $sheet->getStyle("A{$row}:B{$row}")->getAlignment()->setWrapText(true);
            $sheet->getRowDimension($row)->setRowHeight(43);
            ++$row;
        }
    }
}
