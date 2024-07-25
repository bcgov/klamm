<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;


class BRERule extends Model
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
        return $this->belongsToMany(BreField::class, 'b_r_e_field_b_r_e_rule_input', 'b_r_e_rule_id', 'b_r_e_field_id')->withTimestamps();
    }
    public function breOutputs()
    {
        return $this->belongsToMany(BreField::class, 'b_r_e_field_b_r_e_rule_output', 'b_r_e_rule_id', 'b_r_e_field_id')->withTimestamps();
    }

    public function parentRules()
    {
        return $this->belongsToMany(BreRule::class, 'b_r_e_rule_b_r_e_rule', 'parent_rule_id', 'child_rule_id');
    }

    // Define the relationship for child rules
    public function childRules()
    {
        return $this->belongsToMany(BreRule::class, 'b_r_e_rule_b_r_e_rule', 'child_rule_id', 'parent_rule_id');
    }
}
