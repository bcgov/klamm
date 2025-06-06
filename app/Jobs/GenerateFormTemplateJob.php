<?php

namespace App\Jobs;

use App\Helpers\FormTemplateHelper;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use App\Models\FormVersion;
use App\Events\FormVersionUpdateEvent;

class GenerateFormTemplateJob implements ShouldQueue, \Illuminate\Contracts\Queue\ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, Batchable;

    protected $formVersionId;
    protected $timestamp;
    protected $updatedComponents;
    protected $isDraft;
    public int $tries = 2;
    public int $timeout = 300;

    private const CACHE_PREFIX = 'formtemplate:';

    public function __construct(int $formVersionId, int $timestamp, ?array $updatedComponents = null, ?bool $isDraft = false)
    {
        $this->formVersionId = $formVersionId;
        $this->timestamp = $timestamp;
        $this->updatedComponents = $updatedComponents;
        $this->isDraft = $isDraft;
    }
    public function uniqueId(): string
    {
        $draftSuffix = $this->isDraft ? '-draft' : '';
        return 'generate-form-template-' . $this->formVersionId . $draftSuffix;
    }
    public function uniqueFor(): int
    {
        return 60;
    }

    // generate and store the form template in the cache
    public function handle()
    {

        // If newer job is requested, abort early
        $latest = Cache::get($this->cacheRequestKey());
        if ($latest !== null && (int)$latest !== (int)$this->timestamp) return;

        $formVersion = FormVersion::find($this->formVersionId);
        if (!$formVersion) return;

        Cache::tags(['status'])->put(
            $this->cacheStatusKey(),
            'generating',
            now()->addHours(1)
        );

        try {
            $json = FormTemplateHelper::generateJsonTemplate($this->formVersionId, $this->updatedComponents, $this->isDraft);
            if ($this->isDraft) {
                Cache::tags(['draft'])->put($this->cacheTemplateKey(), $json, now()->addDay());
            } else {
                Cache::tags(['form-template'])->put($this->cacheTemplateKey(), $json, now()->addDay());
            }
            FormVersionUpdateEvent::dispatch(
                $this->formVersionId,
                $formVersion->form_id,
                $formVersion->version_number,
                $this->updatedComponents,
                'template',
                $this->isDraft
            );
            Cache::tags(['status'])->put($this->cacheStatusKey(), 'completed', now()->addHours(1));
        } catch (\Exception $e) {
            Cache::tags(['status'])->put($this->cacheStatusKey(), 'failed', now()->addHours(1));
            throw $e;
        }
    }

    public function cacheRequestKey(): string
    {
        $draftPrefix = $this->isDraft ? 'draft_' : '';
        return self::CACHE_PREFIX . "{$this->formVersionId}:{$draftPrefix}requested_at";
    }

    public function cacheTemplateKey(): string
    {
        $draftPrefix = $this->isDraft ? 'draft_' : '';
        return self::CACHE_PREFIX . "{$this->formVersionId}:{$draftPrefix}cached_json";
    }

    public function cacheStatusKey(): string
    {
        $draftPrefix = $this->isDraft ? 'draft_' : '';
        return self::CACHE_PREFIX . "{$this->formVersionId}:{$draftPrefix}status";
    }

    public function failed(\Throwable $exception)
    {
        Cache::tags(['status'])->put($this->cacheStatusKey(), 'failed', now()->addHours(1));
        app(\Illuminate\Contracts\Cache\Repository::class)->forget('laravel_unique_job:' . $this->uniqueId());
    }
}
