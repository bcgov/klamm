<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StyleSheet extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'filename',
        'description',
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

        // Create UUID and add to record
        static::creating(function ($styleSheet) {
            $styleSheet->filename = $styleSheet->id . '_' . Str::uuid()->toString();
        });

        // If StyleSheet is deleted, delete the associated CSS file. 
        static::deleting(function ($styleSheet) {
            $styleSheet->deleteCssFile();
        });
    }


    public static function booted()
    {
        static::saving(function ($styleSheet) {
            if ($styleSheet->formVersions()->where('status', ['approved', 'published'])->exists()) {
                throw new \Exception("Cannot edit a StyleSheet used in an approved or published Form Version.");
            }
        });

        static::deleting(function ($styleSheet) {
            if ($styleSheet->formVersions()->where('status', ['approved', 'published'])->exists()) {
                throw new \Exception("Cannot delete a StyleSheet used in an approved or published Form Version.");
            }
        });
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
        return Storage::disk('stylesheets')->put($this->filename . '.css', $content);
    }

    public function handleCssFileSave(?string $cssContent): void
    {
        if (!is_null($cssContent)) {
            if (!empty(trim($cssContent))) {
                $this->saveCssContent($cssContent);
            } else {
                $this->deleteCssFile();
            }
        }
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
        ];
    }

    public static function formatType(?string $type): string
    {
        return self::getTypes()[$type] ?? ucfirst($type);
    }

    public function formVersions(): BelongsToMany
    {
        return $this->belongsToMany(FormVersion::class)
            ->withPivot('order as pivot_order', 'type')
            ->orderBy('form_version_style_sheet.order');
    }
}
