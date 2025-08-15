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

class StyleSheet extends Model
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


        // If StyleSheet is deleted, delete the associated CSS file.
        static::deleting(function ($styleSheet) {
            $styleSheet->deleteCssFile();
        });
    }

    public static function booted()
    {
        static::saving(function ($styleSheet) {
            if ($styleSheet->formVersion()->where('status', ['approved', 'published'])->exists()) {
                throw new \Exception("Cannot edit a StyleSheet used in an approved or published Form Version.");
            }
        });

        static::deleting(function ($styleSheet) {
            if ($styleSheet->formVersion()->where('status', ['approved', 'published'])->exists()) {
                throw new \Exception("Cannot delete a StyleSheet used in an approved or published Form Version.");
            }
        });
    }

    public static function createStyleSheet($formVersion, string $css_content, string $type)
    {
        // Delete record if no CSS content
        if (!$css_content) {
            $styleSheet = StyleSheet::where('form_version_id', $formVersion->id)->where('type', $type)->first();
            $styleSheet?->deleteCssFile();
            $styleSheet?->delete();
            return;
        }

        // Create record
        $filename = StyleSheet::createCssFilename($formVersion, $type);
        if ($type === 'web' && $formVersion->webStyleSheet) {
            $filename = $formVersion->webStyleSheet->filename;
        } else if ($type === 'pdf' && $formVersion->pdfStyleSheet) {
            $filename = $formVersion->pdfStyleSheet->filename;
        }
        $styleSheet = StyleSheet::updateOrCreate(
            ['form_version_id' => $formVersion->id, 'type' => $type],
            [
                'form_version_id' => $formVersion->id,
                'filename' => $filename,
                'type' => $type,
            ]
        );

        // Create CSS file
        $styleSheet->saveCssContent($css_content);
    }

    public static function createCssFilename($formVersion, string $type): string
    {
        $formId = Str::slug($formVersion->form->form_id);
        $versionNumber = $formVersion->version_number;
        $uuid = Str::uuid()->toString();
        $filename = "{$formId}-{$versionNumber}-{$type}-{$uuid}";
        return $filename;
    }

    /**
     * Get the CSS file content for this form
     */
    public function getCssContent(): ?string
    {
        $filename = $this->filename . '.css';

        if (Storage::disk('stylesheets')->exists($filename)) {
            $content = Storage::disk('stylesheets')->get($filename);
            return $content;
        }

        return null;
    }

    /**
     * Save CSS content to file
     */
    public function saveCssContent(string $content): bool
    {
        return Storage::disk('stylesheets')->put($this->filename . '.css', trim($content));
    }

    /**
     * Delete CSS file for this form
     */
    public function deleteCssFile(): bool
    {
        $filename = $this->filename . '.css';
        if (Storage::disk('stylesheets')->exists($filename)) {
            return Storage::disk('stylesheets')->delete($filename);
        }

        return true;
    }

    /**
     * Check if CSS file exists for this form
     */
    public function hasCssFile(): bool
    {
        $filename = $this->filename . '.css';
        return Storage::disk('stylesheets')->exists($filename);
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
