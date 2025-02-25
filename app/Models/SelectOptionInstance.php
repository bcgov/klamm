<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SelectOptionInstance extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'form_field_id',
        'select_option_id',
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

    public function selectOption(): BelongsTo
    {
        return $this->belongsTo(SelectOptions::class, 'select_option_id');
    }

    public function formField(): BelongsTo
    {
        return $this->belongsTo(FormField::class, 'form_field_id');
    }
}
