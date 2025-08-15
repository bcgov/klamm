<?php

namespace App\Models\FormBuilding;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Filament\Forms\Components\Textarea;
use WeStacks\FilamentMonacoEditor\MonacoEditor;

class TextInfoFormElement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'content',
    ];

    protected $attributes = [
        'content' => '',
    ];

    /**
     * Get the Filament form schema for this element type.
     * 
     * @param bool $disabled Whether the schema should be disabled
     * @param string $mode The mode ('create' or 'edit')
     */
    public static function getFilamentSchema(bool $disabled = false, string $mode = 'create'): array
    {
        if ($mode === 'create') {
            return [
                Textarea::make('elementable_data.content')
                    ->label('Content')
                    ->rows(10)
                    ->disabled($disabled),
            ];
        }

        return [
            MonacoEditor::make('elementable_data.content')
                ->label('Content')
                ->language('html')
                ->theme('vs-dark')
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
