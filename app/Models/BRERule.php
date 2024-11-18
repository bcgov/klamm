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
        'internal_description',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'related_icm_cdw_fields' => 'array',
        'rule_inputs' => 'array',
        'rule_outputs' => 'array',
        'parent_rules' => 'array',
        'child_rules' => 'array',
        'icmcdw_fields' => 'array',
    ];

    public function getRouteKeyName()
    {
        return 'name';
    }

    public function breInputs()
    {
        return $this->belongsToMany(BREField::class, 'bre_field_bre_rule_input', 'bre_rule_id', 'bre_field_id')->withTimestamps();
    }
    public function breOutputs()
    {
        return $this->belongsToMany(BREField::class, 'bre_field_bre_rule_output', 'bre_rule_id', 'bre_field_id')->withTimestamps();
    }

    public function parentRules()
    {
        return $this->belongsToMany(BRERule::class, 'bre_rule_bre_rule', 'parent_rule_id', 'child_rule_id');
    }

    public function childRules()
    {
        return $this->belongsToMany(BRERule::class, 'bre_rule_bre_rule', 'child_rule_id', 'parent_rule_id');
    }

    public function icmCDWFields()
    {
        return $this->belongsToMany(ICMCDWField::class, 'bre_field_icm_cdw_field', 'bre_field_id', 'icm_cdw_field_id')->withTimestamps();
    }

    public function getICMCDWFieldObjects()
    {
        $fieldIds = $this->breInputs()
            ->pluck('bre_field_id')
            ->merge($this->breOutputs()->pluck('bre_field_id'))
            ->unique();

        return ICMCDWField::select('icm_cdw_fields.id', 'icm_cdw_fields.name')
            ->join('bre_field_icm_cdw_field', 'icm_cdw_fields.id', '=', 'bre_field_icm_cdw_field.icm_cdw_field_id')
            ->join('bre_fields', 'bre_field_icm_cdw_field.bre_field_id', '=', 'bre_fields.id')
            ->whereIn('bre_fields.id', $fieldIds)
            ->get();
    }

    public function getRelatedIcmCDWFields(): array
    {
        return $this->getICMCDWFieldObjects()
            ->pluck('name', 'id')
            ->toArray();
    }

    public function syncRuleInputs(array $ruleInputs)
    {
        $inputIds = collect($ruleInputs)->pluck('id')->all();
        $this->breInputs()->sync($inputIds);
    }

    public function syncRuleOutputs(array $ruleOutputs)
    {
        $outputIds = collect($ruleOutputs)->pluck('id')->all();
        $this->breOutputs()->sync($outputIds);
    }

    public function syncParentRules(array $parentRules)
    {
        $parentRuleIds = collect($parentRules)->pluck('id')->all();
        $this->parentRules()->sync($parentRuleIds);
    }

    public function syncChildRules(array $childRules)
    {
        $childRuleIds = collect($childRules)->pluck('id')->all();
        $this->childRules()->sync($childRuleIds);
    }

    public function syncIcmCDWFields(array $icmCDWFields)
    {
        $icmCDWFieldIds = collect($icmCDWFields)->pluck('id')->all();
        $this->icmCDWFields()->sync($icmCDWFieldIds);
    }
}
