<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;


class BRERule extends Model
{
    use HasFactory;
    protected $table = 'bre_rules';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'label',
        'description',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
    ];

    public function breInputs()
    {
        return $this->belongsToMany(BreField::class, 'bre_field_bre_rule_input', 'bre_rule_id', 'bre_field_id')->withTimestamps();
    }
    public function breOutputs()
    {
        return $this->belongsToMany(BreField::class, 'bre_field_bre_rule_output', 'bre_rule_id', 'bre_field_id')->withTimestamps();
    }

    public function parentRules()
    {
        return $this->belongsToMany(BreRule::class, 'bre_rule_bre_rule', 'parent_rule_id', 'child_rule_id');
    }

    // Define the relationship for child rules
    public function childRules()
    {
        return $this->belongsToMany(BreRule::class, 'bre_rule_bre_rule', 'child_rule_id', 'parent_rule_id');
    }
}
