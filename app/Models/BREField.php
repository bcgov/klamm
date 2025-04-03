<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\BREFieldGroup;
use App\Models\BREDataValidation;

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
        'data_validation_id',
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
        'data_validation_id' => 'integer',
        'icmcdw_fields' => 'array',
        'rule_inputs' => 'array',
        'rule_outputs' => 'array',
        'field_groups' => 'array',
        'child_fields' => 'array',
        'siebel_business_objects' => 'array',
        'siebel_business_components' => 'array',
        'siebel_applets' => 'array',
        'siebel_tables' => 'array',
        'siebel_fields' => 'array',
    ];

    public function getRouteKeyName()
    {
        return 'name';
    }

    public function breDataType(): BelongsTo
    {
        return $this->belongsTo(BREDataType::class, 'data_type_id');
    }

    public function getBreDataTypeWithValueTypeAttribute()
    {
        $breDataType = $this->breDataType;

        if ($breDataType) {
            $breDataType->load('breValueType');
        }

        return $breDataType;
    }

    public function breDataValidation(): BelongsTo
    {
        return $this->belongsTo(BREDataValidation::class, 'data_validation_id');
    }

    public function getBreDataValidationWithValidationTypeAttribute()
    {
        $breDataValidation = $this->breDataValidation;

        if ($breDataValidation) {
            $breDataValidation->load('breValidationType');
        }

        return $breDataValidation;
    }

    public function breFieldGroups()
    {
        return $this->belongsToMany(BREFieldGroup::class, 'bre_field_bre_field_group', 'bre_field_id', 'bre_field_group_id')->withTimestamps();
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

    public function icmcdwFields()
    {
        return $this->belongsToMany(ICMCDWField::class, 'bre_field_icm_cdw_field', 'bre_field_id', 'icm_cdw_field_id')->withTimestamps();
    }

    public function siebelBusinessObjects()
    {
        return $this->belongsToMany(SiebelBusinessObject::class, 'bre_field_siebel_business_object', 'bre_field_id', 'siebel_business_object_id')->withTimestamps();
    }

    public function siebelBusinessComponents()
    {
        return $this->belongsToMany(SiebelBusinessComponent::class, 'bre_field_siebel_business_component', 'bre_field_id', 'siebel_business_component_id')->withTimestamps();
    }

    public function siebelApplets()
    {
        return $this->belongsToMany(SiebelApplet::class, 'bre_field_siebel_applet', 'bre_field_id', 'siebel_applet_id')->withTimestamps();
    }

    public function siebelTables()
    {
        return $this->belongsToMany(SiebelTable::class, 'bre_field_siebel_table', 'bre_field_id', 'siebel_table_id')->withTimestamps();
    }

    public function siebelFields()
    {
        return $this->belongsToMany(SiebelField::class, 'bre_field_siebel_field', 'bre_field_id', 'siebel_field_id')->withTimestamps();
    }

    public function getInputOutputType()
    {
        $hasInputs = $this->breInputs()->exists();
        $hasOutputs = $this->breOutputs()->exists();

        if ($hasInputs && $hasOutputs) {
            return 'input/output';
        } elseif ($hasInputs) {
            return 'input';
        } elseif ($hasOutputs) {
            return 'output';
        } else {
            return '';
        }
    }

    public function breRules()
    {
        return $this->belongsToMany(BRERule::class)->withTimestamps();
    }


    public function childFields()
    {
        return $this->belongsToMany(BREField::class, 'bre_field_bre_field', 'parent_field_id', 'child_field_id')
            ->with('breDataType', 'breDataValidation', 'breDataValidation.breValidationType');
    }

    public function syncFieldGroups(array $fieldGroups)
    {
        $fieldGroupIds = collect($fieldGroups)->pluck('id')->all();
        $this->breFieldGroups()->sync($fieldGroupIds);
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

    public function syncIcmCDWFields(array $icmCDWFields)
    {
        $icmCDWFieldIds = collect($icmCDWFields)->pluck('id')->all();
        $this->icmCDWFields()->sync($icmCDWFieldIds);
    }

    public function syncSiebelBusinessObjects(array $siebelBusinessObjects)
    {
        $siebelObjectFieldIds = collect($siebelBusinessObjects)->pluck('id')->all();
        $this->siebelBusinessObjects()->sync($siebelObjectFieldIds);
    }

    public function syncSiebelBusinessComponents(array $siebelBusinessComponents)
    {
        $siebelComponentFieldIds = collect($siebelBusinessComponents)->pluck('id')->all();
        $this->siebelBusinessComponents()->sync($siebelComponentFieldIds);
    }

    public function syncSiebelApplets(array $siebelApplets)
    {
        $siebelAppletFieldIds = collect($siebelApplets)->pluck('id')->all();
        $this->siebelApplets()->sync($siebelAppletFieldIds);
    }

    public function syncSiebelTables(array $siebelTables)
    {
        $siebelTableFieldIds = collect($siebelTables)->pluck('id')->all();
        $this->siebelTables()->sync($siebelTableFieldIds);
    }

    public function syncSiebelFields(array $siebelFields)
    {
        $siebelFieldIds = collect($siebelFields)->pluck('id')->all();
        $this->siebelFields()->sync($siebelFieldIds);
    }

    public function syncChildFields(array $childFields)
    {
        $childFieldIds = collect($childFields)->pluck('id')->all();
        $this->childFields()->sync($childFieldIds);
    }
}
