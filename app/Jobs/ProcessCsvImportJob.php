<?php

namespace App\Jobs;

use App\Events\CsvImportProgressUpdated;
use App\Imports\ProductsImport;
use App\Models\CsvImport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ProcessCsvImportJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    /**
     * Create a new job instance.
     */
    public function __construct(public CsvImport $csvImport) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $this->csvImport->update([
                'status' => 'processing',
                'started_at' => now(),
            ]);

            CsvImportProgressUpdated::dispatch($this->csvImport->fresh());

            $filePath = Storage::disk('local')->path($this->csvImport->file_path);

            if (! file_exists($filePath)) {
                throw new \Exception('CSV file not found');
            }

            // Count total rows for progress tracking
            $totalRows = $this->countTotalRows($filePath);
            $this->csvImport->update(['total_rows' => $totalRows]);

            // Import using Laravel Excel
            $import = new ProductsImport($this->csvImport);
            Excel::import($import, $this->csvImport->file_path, 'local');

            // Update final processed rows count
            $this->csvImport->update([
                'status' => 'completed',
                'processed_rows' => $import->getProcessedRows(),
                'completed_at' => now(),
            ]);

            Log::info('CSV Import Completed', [
                'import_id' => $this->csvImport->id,
                'total_rows' => $totalRows,
                'processed_rows' => $import->getProcessedRows(),
                'skipped_rows' => $import->getSkippedRows(),
            ]);

            CsvImportProgressUpdated::dispatch($this->csvImport->fresh());
        } catch (\Exception $e) {
            Log::error('CSV Import Failed', [
                'import_id' => $this->csvImport->id,
                'error' => $e->getMessage(),
            ]);

            $this->csvImport->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            CsvImportProgressUpdated::dispatch($this->csvImport->fresh());

            throw $e;
        }
    }

    /**
     * Count total rows in CSV file for progress tracking.
     */
    private function countTotalRows(string $filePath): int
    {
        $handle = fopen($filePath, 'r');

        if ($handle === false) {
            throw new \Exception('Unable to open CSV file for row counting');
        }

        // Skip header row
        fgetcsv($handle);

        $totalRows = 0;
        while (fgets($handle) !== false) {
            $totalRows++;
        }

        fclose($handle);

        return $totalRows;
    }
}
