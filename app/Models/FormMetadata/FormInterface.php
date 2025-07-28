<?php

namespace App\Models\FormMetadata;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\FormBuilding\FormVersion;

class FormInterface extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'description',
        'form_version_id',
        'label',
        'style',
        'condition',
    ];


    public function actions(): HasMany
    {
        return $this->hasMany(InterfaceAction::class, 'form_interface_id');
    }

    public function formVersions(): BelongsToMany
    {
        return $this->belongsToMany(FormVersion::class, 'form_version_form_interfaces')
            ->withPivot('order')
            ->withTimestamps();
    }
}
