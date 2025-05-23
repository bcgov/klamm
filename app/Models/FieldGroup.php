<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class FieldGroup extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'label',
        'repeater_item_label',
        'description',
        'internal_description',
        'data_binding_path',
        'data_binding',
        'repeater',
        'clear_button',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'repeater' => 'boolean',
        'clear_button' => 'boolean',
    ];

    protected static function booted()
    {
        static::saving(function (FieldGroup $fieldGroup) {
            if (!$fieldGroup->repeater) {
                $fieldGroup->repeater_item_label = null;
            }
        });
    }

    public function formFields(): BelongsToMany
    {
        return $this->belongsToMany(FormField::class)->withTimestamps();
    }

    public function fieldGroupInstances(): HasMany
    {
        return $this->hasMany(FieldGroupInstance::class);
    }

    public function webStyles(): BelongsToMany
    {
        return $this->belongsToMany(Style::class, 'field_group_style_web', 'field_group_id', 'style_id');
    }

    public function pdfStyles(): BelongsToMany
    {
        return $this->belongsToMany(Style::class, 'field_group_style_pdf', 'field_group_id', 'style_id');
    }

    public function formVersions(): HasManyThrough
    {
        return $this->hasManyThrough(
            FormVersion::class,
            FieldGroupInstance::class,
            'field_group_id',
            'id',
            'id',
            'form_version_id'
        )->distinct();
    }
}
