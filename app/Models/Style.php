<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Style extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'property',
        'value'
    ];

    public function styleInstances(): HasMany
    {
        return $this->hasMany(StyleInstance::class);
    }

    public function webFormFields(): BelongsToMany
    {
        return $this->belongsToMany(FormField::class, 'form_field_style_web', 'style_id', 'form_field_id')
            ->withTimestamps();
    }

    public function pdfFormFields(): BelongsToMany
    {
        return $this->belongsToMany(FormField::class, 'form_field_style_pdf', 'style_id', 'form_field_id')
            ->withTimestamps();
    }

    public function webFieldGroups(): BelongsToMany
    {
        return $this->belongsToMany(FieldGroup::class, 'field_group_style_web', 'style_id', 'field_group_id')
            ->withTimestamps();
    }

    public function pdfFieldGroups(): BelongsToMany
    {
        return $this->belongsToMany(FieldGroup::class, 'field_group_style_pdf', 'style_id', 'field_group_id')
            ->withTimestamps();
    }
}
