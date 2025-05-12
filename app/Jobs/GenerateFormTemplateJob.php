<?php

namespace App\Jobs;

use App\Helpers\FormTemplateHelper;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\FormVersion;

class GenerateFormTemplateJob implements ShouldQueue, \Illuminate\Contracts\Queue\ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, Batchable;

    public int $formVersionId;
    public int $requestedAt;
    public int $tries = 2;
    public int $timeout = 300;

    private const CACHE_PREFIX = 'formtemplate:';

    public function __construct(int $formVersionId, int $requestedAt)
    {
        $this->formVersionId = $formVersionId;
        $this->requestedAt = $requestedAt;
    }

    public function uniqueId(): string
    {
        return 'generate-form-template-' . $this->formVersionId;
    }
    public function uniqueFor(): int
    {
        return 60;
    }

    // generate and store the form template in the cache
    public function handle()
    {
        FormTemplateHelper::clearFormTemplateCache($this->formVersionId);
        // If newer job is requested, abort early
        $latest = Cache::get($this->cacheRequestKey());
        if ($latest !== null && (int)$latest !== (int)$this->requestedAt) return;

        $formVersion = FormVersion::find($this->formVersionId);
        if (!$formVersion) return;

        Cache::tags(['status'])->put(
            $this->cacheStatusKey(),
            'generating',
            now()->addHours(1)
        );

        try {
            $json = FormTemplateHelper::generateJsonTemplate($this->formVersionId);
            Cache::tags(['form-template'])->put($this->cacheTemplateKey(), $json, now()->addDay());
            Cache::tags(['status'])->put($this->cacheStatusKey(), 'completed', now()->addHours(1));
        } catch (\Exception $e) {
            Cache::tags(['status'])->put($this->cacheStatusKey(), 'failed', now()->addHours(1));
            throw $e;
        }
    }

    public function cacheRequestKey(): string
    {
        return self::CACHE_PREFIX . "{$this->formVersionId}:requested_at";
    }

    public function cacheTemplateKey(): string
    {
        return self::CACHE_PREFIX . "{$this->formVersionId}:cached_json";
    }

    public function cacheStatusKey(): string
    {
        return self::CACHE_PREFIX . "{$this->formVersionId}:status";
    }

    public function failed(\Throwable $exception)
    {
        Cache::tags(['status'])->put($this->cacheStatusKey(), 'failed', now()->addHours(1));
        app(\Illuminate\Contracts\Cache\Repository::class)->forget('laravel_unique_job:' . $this->uniqueId());
    }
}
