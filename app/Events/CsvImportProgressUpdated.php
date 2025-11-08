<?php

namespace App\Events;

use App\Models\CsvImport;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CsvImportProgressUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $broadcastQueue = 'broadcasts';

    /**
     * Create a new event instance.
     */
    public function __construct(public CsvImport $csvImport) {}

    /**
     * Handle a broadcasting exception.
     */
    public function failed(\Throwable $exception): void
    {
        \Log::warning('Failed to broadcast CSV import progress update', [
            'import_id' => $this->csvImport->id,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('csv-imports'),
        ];
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->csvImport->id,
            'status' => $this->csvImport->status,
            'file_name' => $this->csvImport->file_name,
            'total_rows' => $this->csvImport->total_rows,
            'processed_rows' => $this->csvImport->processed_rows,
            'progress_percentage' => $this->csvImport->total_rows > 0
                ? round(($this->csvImport->processed_rows / $this->csvImport->total_rows) * 100, 2)
                : 0,
        ];
    }
}
