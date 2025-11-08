<?php

use App\Models\CsvImport;
use App\Models\Product;
use Illuminate\Support\Facades\File;

use function Pest\Laravel\assertDatabaseCount;

test('reset database clears products and csv_imports tables', function () {
    Product::query()->create([
        'unique_key' => 'test-product-1',
        'product_title' => 'Test Product',
        'product_description' => 'Test Description',
    ]);

    Product::query()->create([
        'unique_key' => 'test-product-2',
        'product_title' => 'Test Product 2',
        'product_description' => 'Test Description 2',
    ]);

    CsvImport::query()->create([
        'file_name' => 'test.csv',
        'file_path' => 'csv-imports/test.csv',
        'status' => 'completed',
    ]);

    assertDatabaseCount('products', 2);
    assertDatabaseCount('csv_imports', 1);

    $response = $this->deleteJson('/api/reset-database');

    $response->assertSuccessful();
    $response->assertJson([
        'message' => 'Database reset successfully.',
        'products_deleted' => 2,
        'imports_deleted' => 1,
    ]);

    assertDatabaseCount('products', 0);
    assertDatabaseCount('csv_imports', 0);
});

test('reset database recreates storage directories', function () {
    $csvImportsPath = storage_path('app/private/csv-imports');
    $chunksPath = storage_path('app/private/chunks');

    if (! File::exists($csvImportsPath)) {
        File::makeDirectory($csvImportsPath, 0755, true);
    }
    if (! File::exists($chunksPath)) {
        File::makeDirectory($chunksPath, 0755, true);
    }

    File::put($csvImportsPath.'/test.csv', 'test content');
    File::put($chunksPath.'/test-chunk', 'chunk content');

    expect(File::exists($csvImportsPath.'/test.csv'))->toBeTrue();
    expect(File::exists($chunksPath.'/test-chunk'))->toBeTrue();

    $response = $this->deleteJson('/api/reset-database');

    $response->assertSuccessful();

    expect(File::exists($csvImportsPath))->toBeTrue();
    expect(File::exists($chunksPath))->toBeTrue();
    expect(File::exists($csvImportsPath.'/test.csv'))->toBeFalse();
    expect(File::exists($chunksPath.'/test-chunk'))->toBeFalse();
});
