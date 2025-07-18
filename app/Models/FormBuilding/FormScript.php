<?php

namespace App\Models\FormBuilding;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\FormBuilding\FormVersion;
use Illuminate\Support\Facades\Log;

class FormScript extends Model
{
    use HasFactory;

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
            // Create record
            $filename = FormScript::createJsFilename($formVersion, $type);

            if ($type === 'web' && $formVersion->webFormScript) {
                $filename = $formVersion->webFormScript->filename;
            } else if ($type === 'pdf' && $formVersion->pdfFormScript) {
                $filename = $formVersion->pdfFormScript->filename;
            }

            $formScript = FormScript::updateOrCreate(
                ['form_version_id' => $formVersion->id, 'type' => $type],
                [
                    'form_version_id' => $formVersion->id,
                    'filename' => $filename,
                    'type' => $type,
                ]
            );

            // Create JS file
            $saveResult = $formScript->saveJsContent($js_content);
            if (!$saveResult) {
                throw new \Exception('Failed to save JS content to file');
            }

            return $formScript;
        } catch (\Exception $e) {
            Log::error('Error in createFormScript', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
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
}
