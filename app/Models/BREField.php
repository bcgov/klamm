<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\BREFieldGroup;
use App\Models\FieldGroup;

class BREField extends Model
{
    use HasFactory;
    protected $table = 'bre_fields';

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
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'data_type_id' => 'integer',
    ];

    public function breDataType(): BelongsTo
    {
        return $this->belongsTo(BREDataType::class, 'data_type_id');
    }

    public function breFieldGroups()
    {
        return $this->belongsToMany(BreFieldGroup::class, 'bre_field_bre_field_group', 'bre_field_id', 'bre_field_group_id')->withTimestamps();
    }

    // Accessor for field group names
    public function getFieldGroupNamesAttribute()
    {
        return $this->breFieldGroups->pluck('name')->join(', ');
    }

    public function breInputs()
    {
        return $this->belongsToMany(BRERule::class, 'bre_field_bre_rule_input', 'bre_field_id', 'bre_rule_id')->withTimestamps();
    }
    public function breOutputs()
    {
        return $this->belongsToMany(BRERule::class, 'bre_field_bre_rule_output', 'bre_field_id', 'bre_rule_id')->withTimestamps();
    }


    public function breRules()
    {
        return $this->belongsToMany(BRERule::class)->withTimestamps();
    }
}
