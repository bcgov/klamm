<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ICMCDWField extends Model
{
    use HasFactory;
    protected $table = 'icm_cdw_fields';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'field',
        'panel_type',
        'entity',
        'path',
        'subject_area',
        'applet',
        'datatype',
        'field_input_max_length',
        'ministry',
        'cdw_ui_caption',
        'cdw_table_name',
        'cdw_column_name'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
    ];

    public function breFields()
    {
        return $this->belongsToMany(BREField::class, 'bre_field_icm_cdw_field', 'icm_cdw_field_id', 'bre_field_id')->withTimestamps();
    }

    public function syncBreFields(array $breFields)
    {
        $breFieldIds = collect($breFields)->pluck('id')->all();
        $this->breFields()->sync($breFieldIds);
    }
}
