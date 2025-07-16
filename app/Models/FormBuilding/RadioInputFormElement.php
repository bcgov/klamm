<?php

namespace App\Models\FormBuilding;

use App\Helpers\SchemaHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class RadioInputFormElement extends Model
{
    use HasFactory;

    protected $fillable = [
        'labelText',
        'hideLabel',
        'defaultSelected',
        'labelPosition',
        'helperText',
        'orientation',
    ];

    protected $casts = [
        'hideLabel' => 'boolean',
    ];

    protected $attributes = [
        'hideLabel' => false,
        'labelText' => '',
        'labelPosition' => 'right',
        'orientation' => 'vertical',
    ];

    /**
     * Get the Filament form schema for this element type.
     */
    public static function getFilamentSchema(bool $disabled = false): array
    {
        return [
            SchemaHelper::getLabelTextField($disabled)
                ->required(),
            SchemaHelper::getHideLabelToggle($disabled),
            \Filament\Forms\Components\Select::make('elementable_data.labelPosition')
                ->label('Label Position')
                ->options([
                    'left' => 'Left',
                    'right' => 'Right',
                ])
                ->default('right')
                ->visible(fn(callable $get): bool => !$get('elementable_data.hideLabel'))
                ->disabled($disabled),
            SchemaHelper::getHelperTextField($disabled),
            \Filament\Forms\Components\Select::make('elementable_data.orientation')
                ->label('Orientation')
                ->options([
                    'horizontal' => 'Horizontal',
                    'vertical' => 'Vertical',
                ])
                ->default('vertical')
                ->disabled($disabled),
            \Filament\Forms\Components\Select::make('elementable_data.defaultSelected')
                ->label('Default Selected Value')
                ->options(function (callable $get) {
                    $options = $get('elementable_data.options') ?? [];
                    $selectOptions = [];
                    foreach ($options as $option) {
                        if (!empty($option['value'])) {
                            $selectOptions[$option['value']] = $option['label'] ?? $option['value'];
                        }
                    }
                    return $selectOptions;
                })
                ->disabled($disabled),
            \Filament\Forms\Components\Repeater::make('elementable_data.options')
                ->label('Options')
                ->schema([
                    \Filament\Forms\Components\TextInput::make('label')
                        ->label('Option Label')
                        ->required()
                        ->columnSpan(2)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (callable $set, callable $get, $state) {
                            $value = $get('value');
                            if (empty($value) && !empty($state)) {
                                $slug = \Illuminate\Support\Str::slug($state, '-');
                                $set('value', $slug);
                            }
                        }),
                    \Filament\Forms\Components\TextInput::make('value')
                        ->label('Option Value')
                        ->required()
                        ->columnSpan(2)
                        ->suffixAction(
                            \Filament\Forms\Components\Actions\Action::make('regenerate_value')
                                ->icon('heroicon-o-arrow-path')
                                ->tooltip('Regenerate from Option Label')
                                ->action(function (callable $set, callable $get) {
                                    $label = $get('label');
                                    if (!empty($label)) {
                                        $slug = \Illuminate\Support\Str::slug($label, '-');
                                        $set('value', $slug);
                                    }
                                })
                        ),
                ])
                ->columns(2)
                ->defaultItems(1)
                ->addActionLabel('Add Option')
                ->reorderableWithButtons()
                ->collapsible()
                ->itemLabel(fn(array $state): ?string => $state['label'] ?? 'Option')
                ->disabled($disabled)
                ->minItems(1),
        ];
    }

    /**
     * Get the form element that owns this radio input element.
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
            'labelText' => $this->labelText,
            'hideLabel' => $this->hideLabel,
            'defaultSelected' => $this->defaultSelected,
            'labelPosition' => $this->labelPosition,
            'helperText' => $this->helperText,
            'orientation' => $this->orientation,
        ];
    }

    /**
     * Get the options for this radio input.
     */
    public function options(): MorphMany
    {
        return $this->morphMany(SelectOptionFormElement::class, 'optionable')->orderBy('order');
    }
}
