<?php

namespace App\Models\FormBuilding;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\FormMetadata\FormDataSource;

class FormVersionFormDataSource extends Pivot
{
    protected $table = 'form_versions_form_data_sources';

    public $incrementing = true;

    protected $fillable = [
        'form_version_id',
        'form_data_source_id',
        'order',
    ];

    public function formVersion(): BelongsTo
    {
        return $this->belongsTo(FormVersion::class);
    }

    public function formDataSource(): BelongsTo
    {
        return $this->belongsTo(FormDataSource::class);
    }
}
