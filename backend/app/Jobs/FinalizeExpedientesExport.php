<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class FinalizeExpedientesExport implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $token,
        public readonly string $path,
        public readonly string $filename,
        public readonly int $userId,
    ) {
    }

    public function handle(): void
    {
        if (! Storage::disk('local')->exists($this->path)) {
            return;
        }

        $data = Cache::get($this->token, []);

        Cache::put($this->token, array_merge($data, [
            'status' => 'ready',
            'path' => $this->path,
            'filename' => $this->filename,
            'user_id' => $this->userId,
        ]), now()->addMinutes(30));
    }
}
