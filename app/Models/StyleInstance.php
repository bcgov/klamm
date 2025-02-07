<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StyleInstance extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'style_id',
        'form_instance_field_id',
        'field_group_instance_id',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'style_id' => 'integer',
        'form_instance_field_id' => 'integer',
        'field_group_instance_id' => 'integer',
    ];

    public function style(): BelongsTo
    {
        return $this->belongsTo(Style::class);
    }

    public function formInstanceField(): BelongsTo
    {
        return $this->belongsTo(FormInstanceField::class, 'form_instance_field_id');
    }

    public function fieldGroupInstance(): BelongsTo
    {
        return $this->belongsTo(FieldGroupInstance::class);
    }
}
