<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SelectableValueInstance extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'form_field_id',
        'form_instance_field_id',
        'selectable_value_id',
        'order',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
    ];

    public function selectableValue(): BelongsTo
    {
        return $this->belongsTo(SelectableValue::class, 'selectable_value_id');
    }

    public function formField(): BelongsTo
    {
        return $this->belongsTo(FormField::class, 'form_field_id');
    }

    public function formInstanceField(): BelongsTo
    {
        return $this->belongsTo(FormInstanceField::class, 'form_instance_field_id');
    }
}
