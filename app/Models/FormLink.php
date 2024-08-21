<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormLink extends Model
{
    use HasFactory;
    protected $fillable = [
        'form_id',
        'link',
    ];

    public function form()
    {
        return $this->belongsTo(Form::class);
    }
}
