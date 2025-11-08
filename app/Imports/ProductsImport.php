<?php

namespace App\Imports;

use App\Events\CsvImportProgressUpdated;
use App\Models\CsvImport;
use App\Models\Product;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithUpserts;

class ProductsImport implements ToModel, WithHeadingRow, WithBatchInserts, WithChunkReading, WithUpserts
{
    protected int $processedRows = 0;

    protected int $skippedRows = 0;

    public function __construct(protected CsvImport $csvImport) {}

    public function getProcessedRows(): int
    {
        return $this->processedRows;
    }

    public function getSkippedRows(): int
    {
        return $this->skippedRows;
    }

    public function model(array $row): ?Product
    {
        // Skip rows with invalid or missing unique_key
        $uniqueKey = trim($row['unique_key'] ?? '');
        if (empty($uniqueKey) || ! is_numeric($uniqueKey)) {
            $this->skippedRows++;
            Log::warning('Skipping row with invalid unique_key', ['unique_key' => $uniqueKey, 'row' => $row]);

            return null;
        }

        $this->processedRows++;

        // Update progress every 100 rows
        if ($this->processedRows % 100 === 0) {
            $this->csvImport->update(['processed_rows' => $this->processedRows]);
            CsvImportProgressUpdated::dispatch($this->csvImport->fresh());
        }

        return new Product([
            'unique_key' => $uniqueKey,
            'product_title' => $row['product_title'] ?? null,
            'product_description' => $row['product_description'] ?? null,
            'style_number' => $row['style'] ?? null,
            'available_sizes' => $row['available_sizes'] ?? null,
            'brand_logo_image' => $row['brand_logo_image'] ?? null,
            'thumbnail_image' => $row['thumbnail_image'] ?? null,
            'color_swatch_image' => $row['color_swatch_image'] ?? null,
            'product_image' => $row['product_image'] ?? null,
            'spec_sheet' => $row['spec_sheet'] ?? null,
            'price_text' => $row['price_text'] ?? null,
            'suggested_price' => ! empty($row['suggested_price']) ? (float) $row['suggested_price'] : null,
            'category_name' => $row['category_name'] ?? null,
            'subcategory_name' => $row['subcategory_name'] ?? null,
            'color_name' => $row['color_name'] ?? null,
            'color_square_image' => $row['color_square_image'] ?? null,
            'color_product_image' => $row['color_product_image'] ?? null,
            'color_product_image_thumbnail' => $row['color_product_image_thumbnail'] ?? null,
            'size' => $row['size'] ?? null,
            'qty' => ! empty($row['qty']) ? (int) $row['qty'] : null,
            'piece_weight' => ! empty($row['piece_weight']) ? (float) $row['piece_weight'] : null,
            'piece_price' => ! empty($row['piece_price']) ? (float) $row['piece_price'] : null,
            'dozens_price' => ! empty($row['dozens_price']) ? (float) $row['dozens_price'] : null,
            'case_price' => ! empty($row['case_price']) ? (float) $row['case_price'] : null,
            'price_group' => $row['price_group'] ?? null,
            'case_size' => ! empty($row['case_size']) ? (int) $row['case_size'] : null,
            'inventory_key' => $row['inventory_key'] ?? null,
            'size_index' => ! empty($row['size_index']) ? (int) $row['size_index'] : null,
            'sanmar_mainframe_color' => $row['sanmar_mainframe_color'] ?? null,
            'mill' => $row['mill'] ?? null,
            'product_status' => $row['product_status'] ?? null,
            'companion_styles' => $row['companion_styles'] ?? null,
            'msrp' => ! empty($row['msrp']) ? (float) $row['msrp'] : null,
            'map_pricing' => $row['map_pricing'] ?? null,
            'front_model_image_url' => $row['front_model_image_url'] ?? null,
            'back_model_image' => $row['back_model_image'] ?? null,
            'front_flat_image' => $row['front_flat_image'] ?? null,
            'back_flat_image' => $row['back_flat_image'] ?? null,
            'product_measurements' => $row['product_measurements'] ?? null,
            'pms_color' => $row['pms_color'] ?? null,
            'gtin' => $row['gtin'] ?? null,
        ]);
    }

    public function uniqueBy(): string
    {
        return 'unique_key';
    }

    public function batchSize(): int
    {
        return 100;
    }

    public function chunkSize(): int
    {
        return 100;
    }
}
