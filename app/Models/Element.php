<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Element extends Model
{
    use HasFactory;

    protected $fillable = [
        'form_version_id',
        'parent_element_id',
        'uuid',
        'order',
        'custom_label',
        'hide_label',
        'custom_data_binding_path',
        'custom_data_binding',
        'custom_help_text',
        'visible_web',
        'visible_pdf'
    ];

    protected $casts = [
        'hide_label' => 'boolean',
        'visible_web' => 'boolean',
        'visible_pdf' => 'boolean',
    ];

    public static function boot()
    {
        parent::boot();

        static::creating(function ($element) {
            // Generate UUID if not provided
            if (empty($element->uuid)) {
                $element->uuid = (string) Str::uuid();
            }
        });
    }

    public function formVersion(): BelongsTo
    {
        return $this->belongsTo(FormVersion::class);
    }

    public function parentElement(): BelongsTo
    {
        return $this->belongsTo(Element::class, 'parent_element_id');
    }

    public function childElements(): HasMany
    {
        return $this->hasMany(Element::class, 'parent_element_id');
    }

    public function elementValidations(): HasMany
    {
        return $this->hasMany(ElementValidation::class);
    }

    public function elementConditionals(): HasMany
    {
        return $this->hasMany(ElementConditional::class);
    }
}
