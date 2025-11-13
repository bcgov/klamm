<?php

namespace App\Models\Anonymizer;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AnonymousSiebelDataType extends Model
{
    use SoftDeletes, HasFactory;

    protected $table = 'anonymous_siebel_data_types';
    protected $fillable = [
        'data_type_name',
        'description',
    ];

    public function columns()
    {
        return $this->hasMany(AnonymousSiebelColumn::class, 'data_type_id');
    }
}
