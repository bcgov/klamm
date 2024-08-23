<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;


class BREFieldGroup extends Model
{
    use HasFactory;
    protected $table = 'bre_field_groups';
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
        'bre_fields' => 'array',
    ];

    public function breFields()
    {
        return $this->belongsToMany(BREField::class, 'bre_field_bre_field_group', 'bre_field_group_id', 'bre_field_id')->withTimestamps();
    }

    public function syncbreFields(array $breFields)
    {
        $breFieldIds = collect($breFields)->pluck('id')->all();
        $this->breFields()->sync($breFieldIds);
    }
}
