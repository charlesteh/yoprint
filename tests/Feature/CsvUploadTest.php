<?php

use App\Events\CsvImportProgressUpdated;
use App\Jobs\ProcessCsvImportJob;
use App\Models\CsvImport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

beforeEach(function () {
    Storage::fake('local');
});

test('uploads page can be rendered', function () {
    $response = $this->get('/');

    $response->assertSuccessful();
});

test('csv file can be uploaded', function () {
    Queue::fake();

    $file = UploadedFile::fake()->create('test.csv', 100, 'text/csv');

    $response = $this->postJson('/api/uploads', [
        'file' => $file,
    ]);

    $response->assertCreated();

    assertDatabaseHas('csv_imports', [
        'file_name' => 'test.csv',
        'status' => 'pending',
    ]);

    Queue::assertPushed(ProcessCsvImportJob::class);
});

test('csv upload requires file', function () {
    $response = $this->postJson('/api/uploads', []);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['file']);
});

test('csv upload validates file type', function () {
    $file = UploadedFile::fake()->create('test.pdf', 100, 'application/pdf');

    $response = $this->postJson('/api/uploads', [
        'file' => $file,
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['file']);
});

test('csv import can be processed', function () {
    Event::fake([CsvImportProgressUpdated::class]);

    $csvContent = "UNIQUE_KEY,PRODUCT_TITLE,PRODUCT_DESCRIPTION,STYLE#,AVAILABLE_SIZES,BRAND_LOGO_IMAGE,THUMBNAIL_IMAGE,COLOR_SWATCH_IMAGE,PRODUCT_IMAGE,SPEC_SHEET,PRICE_TEXT,SUGGESTED_PRICE,CATEGORY_NAME,SUBCATEGORY_NAME,COLOR_NAME,COLOR_SQUARE_IMAGE,COLOR_PRODUCT_IMAGE,COLOR_PRODUCT_IMAGE_THUMBNAIL,SIZE,QTY,PIECE_WEIGHT,PIECE_PRICE,DOZENS_PRICE,CASE_PRICE,PRICE_GROUP,CASE_SIZE,INVENTORY_KEY,SIZE_INDEX,SANMAR_MAINFRAME_COLOR,MILL,PRODUCT_STATUS,COMPANION_STYLES,MSRP,MAP_PRICING,FRONT_MODEL_IMAGE_URL,BACK_MODEL_IMAGE,FRONT_FLAT_IMAGE,BACK_FLAT_IMAGE,PRODUCT_MEASUREMENTS,PMS_COLOR,GTIN\n";
    $csvContent .= "12345,Test Product,Test Description,TEST-001,S-XL,logo.jpg,thumb.jpg,swatch.jpg,product.jpg,spec.pdf,Price applies,10.99,Category,Subcategory,Red,square.jpg,product.jpg,thumb.jpg,M,100,0.5,8.99,7.99,6.99,A,36,INV-001,2,Red,Mill Name,Active,COMP-001,12.99,MAP,front.jpg,back.jpg,front_flat.jpg,back_flat.jpg,measurements.pdf,PMS-123,1234567890123\n";

    Storage::disk('local')->put('test.csv', $csvContent);

    $csvImport = CsvImport::query()->create([
        'file_name' => 'test.csv',
        'file_path' => 'test.csv',
        'status' => 'pending',
    ]);

    $job = new ProcessCsvImportJob($csvImport);
    $job->handle();

    assertDatabaseHas('csv_imports', [
        'id' => $csvImport->id,
        'status' => 'completed',
        'total_rows' => 1,
        'processed_rows' => 1,
    ]);

    assertDatabaseHas('products', [
        'unique_key' => '12345',
        'product_title' => 'Test Product',
        'product_description' => 'Test Description',
        'style_number' => 'TEST-001',
    ]);

    Event::assertDispatched(CsvImportProgressUpdated::class);
});

test('csv import handles upsert correctly', function () {
    $csvContent = "UNIQUE_KEY,PRODUCT_TITLE,PRODUCT_DESCRIPTION,STYLE#,AVAILABLE_SIZES,BRAND_LOGO_IMAGE,THUMBNAIL_IMAGE,COLOR_SWATCH_IMAGE,PRODUCT_IMAGE,SPEC_SHEET,PRICE_TEXT,SUGGESTED_PRICE,CATEGORY_NAME,SUBCATEGORY_NAME,COLOR_NAME,COLOR_SQUARE_IMAGE,COLOR_PRODUCT_IMAGE,COLOR_PRODUCT_IMAGE_THUMBNAIL,SIZE,QTY,PIECE_WEIGHT,PIECE_PRICE,DOZENS_PRICE,CASE_PRICE,PRICE_GROUP,CASE_SIZE,INVENTORY_KEY,SIZE_INDEX,SANMAR_MAINFRAME_COLOR,MILL,PRODUCT_STATUS,COMPANION_STYLES,MSRP,MAP_PRICING,FRONT_MODEL_IMAGE_URL,BACK_MODEL_IMAGE,FRONT_FLAT_IMAGE,BACK_FLAT_IMAGE,PRODUCT_MEASUREMENTS,PMS_COLOR,GTIN\n";
    $csvContent .= "12345,Original Product,Original Description,TEST-001,S-XL,logo.jpg,thumb.jpg,swatch.jpg,product.jpg,spec.pdf,Price applies,10.99,Category,Subcategory,Red,square.jpg,product.jpg,thumb.jpg,M,100,0.5,8.99,7.99,6.99,A,36,INV-001,2,Red,Mill Name,Active,COMP-001,12.99,MAP,front.jpg,back.jpg,front_flat.jpg,back_flat.jpg,measurements.pdf,PMS-123,1234567890123\n";

    Storage::disk('local')->put('test1.csv', $csvContent);

    $csvImport1 = CsvImport::query()->create([
        'file_name' => 'test1.csv',
        'file_path' => 'test1.csv',
        'status' => 'pending',
    ]);

    $job1 = new ProcessCsvImportJob($csvImport1);
    $job1->handle();

    assertDatabaseCount('products', 1);

    $updatedCsvContent = "UNIQUE_KEY,PRODUCT_TITLE,PRODUCT_DESCRIPTION,STYLE#,AVAILABLE_SIZES,BRAND_LOGO_IMAGE,THUMBNAIL_IMAGE,COLOR_SWATCH_IMAGE,PRODUCT_IMAGE,SPEC_SHEET,PRICE_TEXT,SUGGESTED_PRICE,CATEGORY_NAME,SUBCATEGORY_NAME,COLOR_NAME,COLOR_SQUARE_IMAGE,COLOR_PRODUCT_IMAGE,COLOR_PRODUCT_IMAGE_THUMBNAIL,SIZE,QTY,PIECE_WEIGHT,PIECE_PRICE,DOZENS_PRICE,CASE_PRICE,PRICE_GROUP,CASE_SIZE,INVENTORY_KEY,SIZE_INDEX,SANMAR_MAINFRAME_COLOR,MILL,PRODUCT_STATUS,COMPANION_STYLES,MSRP,MAP_PRICING,FRONT_MODEL_IMAGE_URL,BACK_MODEL_IMAGE,FRONT_FLAT_IMAGE,BACK_FLAT_IMAGE,PRODUCT_MEASUREMENTS,PMS_COLOR,GTIN\n";
    $updatedCsvContent .= "12345,Updated Product,Updated Description,TEST-001,S-XL,logo.jpg,thumb.jpg,swatch.jpg,product.jpg,spec.pdf,Price applies,15.99,Category,Subcategory,Red,square.jpg,product.jpg,thumb.jpg,M,150,0.5,12.99,11.99,10.99,A,36,INV-001,2,Red,Mill Name,Active,COMP-001,18.99,MAP,front.jpg,back.jpg,front_flat.jpg,back_flat.jpg,measurements.pdf,PMS-123,1234567890123\n";

    Storage::disk('local')->put('test2.csv', $updatedCsvContent);

    $csvImport2 = CsvImport::query()->create([
        'file_name' => 'test2.csv',
        'file_path' => 'test2.csv',
        'status' => 'pending',
    ]);

    $job2 = new ProcessCsvImportJob($csvImport2);
    $job2->handle();

    assertDatabaseCount('products', 1);

    assertDatabaseHas('products', [
        'unique_key' => '12345',
        'product_title' => 'Updated Product',
        'product_description' => 'Updated Description',
        'suggested_price' => '15.99',
        'qty' => 150,
    ]);
});

test('csv import is idempotent', function () {
    $csvContent = "UNIQUE_KEY,PRODUCT_TITLE,PRODUCT_DESCRIPTION,STYLE#,AVAILABLE_SIZES,BRAND_LOGO_IMAGE,THUMBNAIL_IMAGE,COLOR_SWATCH_IMAGE,PRODUCT_IMAGE,SPEC_SHEET,PRICE_TEXT,SUGGESTED_PRICE,CATEGORY_NAME,SUBCATEGORY_NAME,COLOR_NAME,COLOR_SQUARE_IMAGE,COLOR_PRODUCT_IMAGE,COLOR_PRODUCT_IMAGE_THUMBNAIL,SIZE,QTY,PIECE_WEIGHT,PIECE_PRICE,DOZENS_PRICE,CASE_PRICE,PRICE_GROUP,CASE_SIZE,INVENTORY_KEY,SIZE_INDEX,SANMAR_MAINFRAME_COLOR,MILL,PRODUCT_STATUS,COMPANION_STYLES,MSRP,MAP_PRICING,FRONT_MODEL_IMAGE_URL,BACK_MODEL_IMAGE,FRONT_FLAT_IMAGE,BACK_FLAT_IMAGE,PRODUCT_MEASUREMENTS,PMS_COLOR,GTIN\n";
    $csvContent .= "12345,Test Product,Test Description,TEST-001,S-XL,logo.jpg,thumb.jpg,swatch.jpg,product.jpg,spec.pdf,Price applies,10.99,Category,Subcategory,Red,square.jpg,product.jpg,thumb.jpg,M,100,0.5,8.99,7.99,6.99,A,36,INV-001,2,Red,Mill Name,Active,COMP-001,12.99,MAP,front.jpg,back.jpg,front_flat.jpg,back_flat.jpg,measurements.pdf,PMS-123,1234567890123\n";

    Storage::disk('local')->put('test.csv', $csvContent);

    for ($i = 0; $i < 3; $i++) {
        $csvImport = CsvImport::query()->create([
            'file_name' => "test{$i}.csv",
            'file_path' => 'test.csv',
            'status' => 'pending',
        ]);

        $job = new ProcessCsvImportJob($csvImport);
        $job->handle();
    }

    assertDatabaseCount('products', 1);

    assertDatabaseHas('products', [
        'unique_key' => '12345',
        'product_title' => 'Test Product',
    ]);
});

test('api returns import history', function () {
    CsvImport::query()->create([
        'file_name' => 'test1.csv',
        'file_path' => 'uploads/test1.csv',
        'status' => 'completed',
        'total_rows' => 100,
        'processed_rows' => 100,
    ]);

    CsvImport::query()->create([
        'file_name' => 'test2.csv',
        'file_path' => 'uploads/test2.csv',
        'status' => 'processing',
        'total_rows' => 200,
        'processed_rows' => 50,
    ]);

    CsvImport::query()->create([
        'file_name' => 'test3.csv',
        'file_path' => 'uploads/test3.csv',
        'status' => 'pending',
    ]);

    $response = $this->getJson('/api/uploads');

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'data' => [
            '*' => [
                'id',
                'file_name',
                'status',
                'total_rows',
                'processed_rows',
                'progress_percentage',
                'created_at',
            ],
        ],
    ]);
});
