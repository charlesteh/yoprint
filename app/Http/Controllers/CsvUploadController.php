<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadCsvRequest;
use App\Jobs\ProcessCsvImportJob;
use App\Models\CsvImport;
use App\Models\Product;
use App\Transformers\CsvImportTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response;
use League\Fractal\Manager;
use League\Fractal\Resource\Collection;

class CsvUploadController extends Controller
{
    public function __construct(private readonly Manager $fractal) {}

    public function index(): Response
    {
        return Inertia::render('Uploads/Index');
    }

    public function store(UploadCsvRequest $request): JsonResponse
    {
        $file = $request->file('file');
        $fileName = $file->getClientOriginalName();
        $filePath = $file->store('csv-imports', 'local');

        $csvImport = CsvImport::query()->create([
            'user_id' => $request->user()?->id,
            'file_name' => $fileName,
            'file_path' => $filePath,
            'status' => 'pending',
        ]);

        ProcessCsvImportJob::dispatch($csvImport);

        return response()->json([
            'message' => 'CSV file uploaded successfully and is being processed.',
            'import_id' => $csvImport->id,
        ], 201);
    }

    public function storeChunk(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file',
            'chunkIndex' => 'required|integer|min:0',
            'totalChunks' => 'required|integer|min:1',
            'fileName' => 'required|string',
            'uploadId' => 'required|string',
        ]);

        $uploadId = $request->input('uploadId');
        $chunkIndex = (int) $request->input('chunkIndex');
        $totalChunks = (int) $request->input('totalChunks');
        $fileName = $request->input('fileName');

        \Log::info("Receiving chunk {$chunkIndex}/{$totalChunks} for upload {$uploadId}");

        $chunkPath = "chunks/{$uploadId}/chunk_{$chunkIndex}";
        $request->file('file')->storeAs(dirname($chunkPath), basename($chunkPath), 'local');

        $isLastChunk = ($chunkIndex + 1) === $totalChunks;

        \Log::info("Chunk {$chunkIndex} stored. Is last chunk: ".($isLastChunk ? 'yes' : 'no'));

        if ($isLastChunk) {
            \Log::info("Starting merge for upload {$uploadId}");
            $finalPath = $this->mergeChunks($uploadId, $totalChunks, $fileName);
            \Log::info("Merge completed. Final path: {$finalPath}");

            $csvImport = CsvImport::query()->create([
                'user_id' => $request->user()?->id,
                'file_name' => $fileName,
                'file_path' => $finalPath,
                'status' => 'pending',
            ]);

            ProcessCsvImportJob::dispatch($csvImport);

            \Log::info("CSV import {$csvImport->id} created and job dispatched");

            return response()->json([
                'message' => 'CSV file uploaded successfully and is being processed.',
                'import_id' => $csvImport->id,
                'completed' => true,
            ], 201);
        }

        return response()->json([
            'message' => 'Chunk uploaded successfully.',
            'chunkIndex' => $chunkIndex,
            'completed' => false,
        ], 200);
    }

    private function mergeChunks(string $uploadId, int $totalChunks, string $fileName): string
    {
        $finalPath = "csv-imports/{$uploadId}_{$fileName}";
        $finalFullPath = storage_path("app/private/{$finalPath}");

        \Log::info("Merging chunks to: {$finalFullPath}");

        $directory = dirname($finalFullPath);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
            \Log::info("Created directory: {$directory}");
        }

        $output = fopen($finalFullPath, 'wb');

        for ($i = 0; $i < $totalChunks; $i++) {
            $chunkPath = storage_path("app/private/chunks/{$uploadId}/chunk_{$i}");

            if (! file_exists($chunkPath)) {
                \Log::error("Chunk {$i} not found at: {$chunkPath}");
                throw new \Exception("Chunk {$i} not found");
            }

            $chunk = fopen($chunkPath, 'rb');
            $bytesCopied = stream_copy_to_stream($chunk, $output);
            fclose($chunk);

            \Log::info("Merged chunk {$i} ({$bytesCopied} bytes)");

            unlink($chunkPath);
        }

        fclose($output);

        $finalSize = filesize($finalFullPath);
        \Log::info("Final merged file size: {$finalSize} bytes");

        $chunksDir = storage_path("app/private/chunks/{$uploadId}");
        if (is_dir($chunksDir)) {
            rmdir($chunksDir);
            \Log::info('Cleaned up chunks directory');
        }

        return $finalPath;
    }

    public function index_api(Request $request): JsonResponse
    {
        $imports = CsvImport::query()
            ->when($request->user(), fn ($query) => $query->where('user_id', $request->user()->id))
            ->latest()
            ->limit(50)
            ->get();

        $resource = new Collection($imports, new CsvImportTransformer);
        $data = $this->fractal->createData($resource)->toArray();

        return response()->json($data);
    }

    public function resetDatabase(): JsonResponse
    {
        $productsCount = Product::query()->count();
        $importsCount = CsvImport::query()->count();

        Product::query()->truncate();
        CsvImport::query()->truncate();

        $csvImportsPath = storage_path('app/private/csv-imports');
        if (File::exists($csvImportsPath)) {
            File::deleteDirectory($csvImportsPath);
            File::makeDirectory($csvImportsPath, 0755, true);
        }

        $chunksPath = storage_path('app/private/chunks');
        if (File::exists($chunksPath)) {
            File::deleteDirectory($chunksPath);
            File::makeDirectory($chunksPath, 0755, true);
        }

        return response()->json([
            'message' => 'Database reset successfully.',
            'products_deleted' => $productsCount,
            'imports_deleted' => $importsCount,
        ], 200);
    }
}
