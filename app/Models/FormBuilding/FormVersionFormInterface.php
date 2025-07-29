<?php

namespace App\Models\FormBuilding;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\FormMetadata\FormInterface;

class FormVersionFormInterface extends Pivot
{
    protected $table = 'form_version_form_interfaces';

    public $incrementing = true;

    protected $fillable = [
        'form_version_id',
        'form_interface_id',
        'order',
    ];

    public function formVersion(): BelongsTo
    {
        return $this->belongsTo(FormVersion::class);
    }

    public function formInterface(): BelongsTo
    {
        return $this->belongsTo(FormInterface::class);
    }
}
