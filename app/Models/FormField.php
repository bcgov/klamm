<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FormField extends Model
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
        'help_text',
        'data_type_id',
        'description',
        'data_binding_path',
        'data_binding',
        'mask'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'data_type_id' => 'integer',
    ];

    public function dataType(): BelongsTo
    {
        return $this->belongsTo(DataType::class);
    }

    public function fieldGroups(): BelongsToMany
    {
        return $this->belongsToMany(FieldGroup::class)->withTimestamps();
    }

    public function formInstanceFields(): HasMany
    {
        return $this->hasMany(FormInstanceField::class);
    }

    public function webStyles(): BelongsToMany
    {
        return $this->belongsToMany(Style::class, 'form_field_style_web', 'form_field_id', 'style_id');
    }

    public function pdfStyles(): BelongsToMany
    {
        return $this->belongsToMany(Style::class, 'form_field_style_pdf', 'form_field_id', 'style_id');
    }

    public function validations(): HasMany
    {
        return $this->hasMany(FormFieldValidation::class);
    }

    public function formFieldValue(): HasOne
    {
        return $this->hasOne(FormFieldValue::class);
    }

    public function formFieldDateFormat(): HasOne
    {
        return $this->hasOne(FormFieldDateFormat::class);
    }

    public function isValueInputNeededForField()
    {
        return $this->dataType && $this->dataType->name === 'text-info';
    }

    public function selectOptionInstances(): HasMany
    {
        return $this->hasMany(SelectOptionInstance::class, 'form_field_id');
    }

    public function formVersions()
    {
        return $this->belongsToMany(
            FormVersion::class,
            'form_instance_fields',
            'form_field_id',
            'form_version_id'
        )->distinct();
    }
}
