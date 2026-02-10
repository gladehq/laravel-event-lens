<?php

namespace GladeHQ\LaravelEventLens\Services;

use GladeHQ\LaravelEventLens\Models\EventLog;
use Illuminate\Support\Facades\DB;

class EventLensBuffer
{
    protected array $events = [];

    public function push(array $eventData): void
    {
        $this->events[] = $eventData;
    }

    public function flush(): void
    {
        if (empty($this->events)) {
            return;
        }

        try {
            // Batch insert for performance
            // Note: 'payload' and 'side_effects' are arrays, needing json_encode for DB
            // But strict typed INSERTs might need raw strings if using DB::table but EventLog::insert is safer?
            // Eloquent's insert() doesn't cast automatically.
            // We should use EventLog::insert(), but manually handle JSON casting.
            
            $batch = array_map(function ($event) {
                // Ensure arrays are JSON
                if (isset($event['payload']) && is_array($event['payload'])) {
                    $event['payload'] = json_encode($event['payload'], JSON_INVALID_UTF8_SUBSTITUTE);
                }
                if (isset($event['side_effects']) && is_array($event['side_effects'])) {
                    $event['side_effects'] = json_encode($event['side_effects']);
                }
                if (isset($event['model_changes']) && is_array($event['model_changes'])) {
                    $event['model_changes'] = json_encode($event['model_changes']);
                }
                
                // Ensure timestamps are formatted if they are objects
                if (isset($event['happened_at']) && $event['happened_at'] instanceof \DateTimeInterface) {
                    $event['happened_at'] = $event['happened_at']->format('Y-m-d H:i:s');
                }
                
                // Add timestamps for entry
                $now = now()->format('Y-m-d H:i:s');
                $event['created_at'] = $now;
                $event['updated_at'] = $now;
                
                return $event;
            }, $this->events);

            EventLog::insert($batch);
            
            // Clear buffer
            $this->events = [];
            
        } catch (\Throwable $e) {
            if (config('app.debug')) {
                report($e);
            }
            $this->events = [];
        }
    }
}
