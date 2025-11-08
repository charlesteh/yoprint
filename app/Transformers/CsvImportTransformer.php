<?php

namespace App\Transformers;

use App\Models\CsvImport;
use League\Fractal\TransformerAbstract;

class CsvImportTransformer extends TransformerAbstract
{
    /**
     * Transform the CsvImport model.
     *
     * @return array<string, mixed>
     */
    public function transform(CsvImport $csvImport): array
    {
        return [
            'id' => $csvImport->id,
            'file_name' => $csvImport->file_name,
            'status' => $csvImport->status,
            'total_rows' => $csvImport->total_rows,
            'processed_rows' => $csvImport->processed_rows,
            'progress_percentage' => $csvImport->total_rows > 0
                ? round(($csvImport->processed_rows / $csvImport->total_rows) * 100, 2)
                : 0,
            'error_message' => $csvImport->error_message,
            'started_at' => $csvImport->started_at?->toIso8601String(),
            'completed_at' => $csvImport->completed_at?->toIso8601String(),
            'created_at' => $csvImport->created_at->toIso8601String(),
            'updated_at' => $csvImport->updated_at->toIso8601String(),
        ];
    }
}
