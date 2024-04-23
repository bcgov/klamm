<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormField extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'label',
        'help_text',
        'data_type_id',
        'description',
        'field_group_id',
        'validation',
        'required',
        'repeater',
        'max_count',
        'conditional_logic',
        'prepopulated',
        'datasource_id',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'data_type_id' => 'integer',
        'field_group_id' => 'integer',
        'required' => 'boolean',
        'repeater' => 'boolean',
        'prepopulated' => 'boolean',
        'datasource_id' => 'integer',
    ];

    public function dataType(): BelongsTo
    {
        return $this->belongsTo(DataType::class);
    }

    public function fieldGroup(): BelongsTo
    {
        return $this->belongsTo(FieldGroup::class);
    }

    public function datasource(): BelongsTo
    {
        return $this->belongsTo(Datasource::class);
    }
}
