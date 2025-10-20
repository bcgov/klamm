<?php

namespace App\Models\FormBuilding;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\FormBuilding\FormVersion;
use Illuminate\Support\Facades\Log;

class FormScript extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'form_version_id',
        'filename',
        'description',
        'type',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
    ];

    public static function boot()
    {
        parent::boot();

        // If FormScript is deleted, delete the associated JS file.
        static::deleting(function ($formScript) {
            $formScript->deleteJsFile();
        });
    }

    public static function booted()
    {
        static::saving(function ($formScript) {
            if ($formScript->formVersion()->where('status', ['approved', 'published'])->exists()) {
                throw new \Exception("Cannot edit a FormScript used in an approved or published Form Version.");
            }
        });

        static::deleting(function ($formScript) {
            if ($formScript->formVersion()->where('status', ['approved', 'published'])->exists()) {
                throw new \Exception("Cannot delete a FormScript used in an approved or published Form Version.");
            }
        });
    }

    public static function createFormScript($formVersion, string $js_content, string $type)
    {
        // Delete record if no JS content
        if (!$js_content) {
            $formScript = FormScript::where('form_version_id', $formVersion->id)->where('type', $type)->first();
            $formScript?->deleteJsFile();
            $formScript?->delete();
            return;
        }

        try {
            // Prefer an existing filename (active or trashed) to keep the path stable
            $existing = FormScript::withTrashed()
                ->where('form_version_id', $formVersion->id)
                ->where('type', $type)
                ->first();

            $filename = $existing?->filename ?? FormScript::createJsFilename($formVersion, $type);

            // Atomic UPSERT on (form_version_id, type)
            // If a trashed row exists, this also "restores" it by setting deleted_at = null
            FormScript::upsert(
                [
                    [
                        'form_version_id' => $formVersion->id,
                        'type' => $type,
                        'filename' => $filename,
                        'deleted_at' => null,   
                        'created_at' => now(),  
                        'updated_at' => now(),
                    ]
                ],
                ['form_version_id', 'type'],      
                ['filename', 'deleted_at', 'updated_at']
            );

            // Fetch the (now guaranteed) active row
            $formScript = FormScript::where('form_version_id', $formVersion->id)
                ->where('type', $type)
                ->firstOrFail();

            // Write JS file
            if (!$formScript->saveJsContent($js_content)) {
                throw new \Exception('Failed to save JS content to file');
            }

            return $formScript;
        } catch (\Throwable $e) {
            Log::error('Error in createFormScript', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    public static function createJsFilename($formVersion, string $type): string
    {
        $formId = Str::slug($formVersion->form->form_id);
        $versionNumber = $formVersion->version_number;
        $uuid = Str::uuid()->toString();
        $filename = "{$formId}-{$versionNumber}-{$type}-{$uuid}";
        return $filename;
    }

    /**
     * Get the JS file content for this form
     */
    public function getJsContent(): ?string
    {
        $filename = $this->filename . '.js';

        if (Storage::disk('scripts')->exists($filename)) {
            $content = Storage::disk('scripts')->get($filename);
            return $content;
        }

        return null;
    }

    /**
     * Save JS content to file
     */
    public function saveJsContent(string $content): bool
    {
        try {
            $filename = $this->filename . '.js';
            $result = Storage::disk('scripts')->put($filename, trim($content));
            return $result;
        } catch (\Exception $e) {
            Log::error('Error saving JS content', [
                'filename' => $this->filename . '.js',
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Delete JS file for this form
     */
    public function deleteJsFile(): bool
    {
        $filename = $this->filename . '.js';
        if (Storage::disk('scripts')->exists($filename)) {
            return Storage::disk('scripts')->delete($filename);
        }

        return true;
    }

    /**
     * Check if JS file exists for this form
     */
    public function hasJsFile(): bool
    {
        $filename = $this->filename . '.js';
        return Storage::disk('scripts')->exists($filename);
    }

    public static function getTypes(): array
    {
        return [
            'web' => 'Web',
            'pdf' => 'PDF',
            'template' => 'Template',
        ];
    }

    public static function formatType(?string $type): string
    {
        return self::getTypes()[$type] ?? ucfirst($type);
    }

    public function formVersion(): BelongsTo
    {
        return $this->belongsTo(FormVersion::class);
    }

    /**
     * Many-to-many relationship with FormVersion
     */
    public function formVersions(): BelongsToMany
    {
        return $this->belongsToMany(FormVersion::class, 'form_script_form_version')
            ->withTimestamps();
    }
}
