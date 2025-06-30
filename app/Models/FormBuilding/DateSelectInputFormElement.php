<?php

namespace App\Models\FormBuilding;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class DateSelectInputFormElement extends Model
{
    use HasFactory;

    protected $fillable = [
        'placeholder_text',
        'label',
        'visible_label',
        'repeater_item_label',
        'min_date',
        'max_date',
        'default_date',
        'date_format',
        'include_time',
    ];

    protected $casts = [
        'visible_label' => 'boolean',
        'min_date' => 'date',
        'max_date' => 'date',
        'default_date' => 'date',
        'include_time' => 'boolean',
    ];

    /**
     * Get the form element that owns this date select input element.
     */
    public function formElement(): MorphOne
    {
        return $this->morphOne(FormElement::class, 'elementable');
    }

    /**
     * Get available date formats.
     */
    public static function getDateFormats(): array
    {
        return [
            'Y-m-d' => 'YYYY-MM-DD',
            'd/m/Y' => 'DD/MM/YYYY',
            'm/d/Y' => 'MM/DD/YYYY',
            'd-m-Y' => 'DD-MM-YYYY',
            'm-d-Y' => 'MM-DD-YYYY',
            'Y-m-d H:i' => 'YYYY-MM-DD HH:MM',
            'd/m/Y H:i' => 'DD/MM/YYYY HH:MM',
        ];
    }
}
