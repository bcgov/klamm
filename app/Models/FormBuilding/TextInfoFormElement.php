<?php

namespace App\Models\FormBuilding;

use Filament\Forms\Components\MarkdownEditor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class TextInfoFormElement extends Model
{
    use HasFactory;

    protected $fillable = [
        'content',
    ];

    /**
     * Get the Filament form schema for this element type.
     */
    public static function getFilamentSchema(bool $disabled = false): array
    {
        return [
            MarkdownEditor::make('elementable_data.content')
                ->label('Content')
                ->disabled($disabled),
        ];
    }

    /**
     * Get the form element that owns this text info element.
     */
    public function formElement(): MorphOne
    {
        return $this->morphOne(FormElement::class, 'elementable');
    }
}
