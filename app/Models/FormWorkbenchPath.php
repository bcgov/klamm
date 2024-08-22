<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormWorkbenchPath extends Model
{
    use HasFactory;
    protected $fillable = [
        'form_id',
        'workbench_path',
    ];

    public function form()
    {
        return $this->belongsTo(Form::class);
    }
}
