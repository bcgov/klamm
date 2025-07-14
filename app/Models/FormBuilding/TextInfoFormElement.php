<?php

namespace App\Models\FormBuilding;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use WeStacks\FilamentMonacoEditor\MonacoEditor;

class TextInfoFormElement extends Model
{
    use HasFactory;

    protected $fillable = [
        'content',
    ];

    protected $attributes = [
        'content' => '',
    ];

    /**
     * Get the Filament form schema for this element type.
     */
    public static function getFilamentSchema(bool $disabled = false): array
    {
        return [
            MonacoEditor::make('elementable_data.content')
                ->label('Content')
                ->language('html')
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

    /**
     * Return this element's data as an array
     */
    public function getData(): array
    {
        return [
            'content' => $this->content,
        ];
    }
}
