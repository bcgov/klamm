<?php

namespace App\Models\FormBuilding;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SelectOptionFormElement extends Model
{
    use HasFactory;

    protected $fillable = [
        'optionable_type',
        'optionable_id',
        'label',
        'order',
        'description',
    ];

    protected $casts = [
        'order' => 'integer',
    ];

    /**
     * Get the owning optionable model (SelectInputFormElement or RadioInputFormElement).
     */
    public function optionable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope to order by the order field
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }

    /**
     * Create an option for a select input
     */
    public static function createForSelect(SelectInputFormElement $selectInput, array $optionData): self
    {
        $optionData['optionable_type'] = SelectInputFormElement::class;
        $optionData['optionable_id'] = $selectInput->id;

        return self::create($optionData);
    }

    /**
     * Create an option for a radio input
     */
    public static function createForRadio(RadioInputFormElement $radioInput, array $optionData): self
    {
        $optionData['optionable_type'] = RadioInputFormElement::class;
        $optionData['optionable_id'] = $radioInput->id;

        return self::create($optionData);
    }
}
