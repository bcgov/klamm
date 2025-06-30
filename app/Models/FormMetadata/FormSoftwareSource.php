<?php

namespace App\Models\FormMetadata;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Form;

class FormSoftwareSource extends Model
{
    use HasFactory;

    protected $fillable = [
        "name",
        "description"
    ];

    public function forms(): BelongsToMany
    {
        return $this->belongsToMany(Form::class, 'form_software_source_form');
    }
}
