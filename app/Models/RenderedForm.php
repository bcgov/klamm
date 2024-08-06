<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class RenderedForm extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['created_by', 'name', 'description', 'structure', 'ministry_id'];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->id = (string) Str::uuid();
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getStructureAttribute($value)
    {
        return json_encode(json_decode($value), JSON_PRETTY_PRINT);
    }

    public function ministry()
    {
        return $this->belongsTo(Ministry::class);
    }
}
