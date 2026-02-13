<?php

namespace App\Helpers;

use App\Models\FormBuilding\FormVersion;
use App\Models\FormMetadata\FormDataSource;
use App\Models\DataBindingMapping;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Get;
use Filament\Forms\Set; 

class DataBindingsHelper
{
    /**
     * Get the Data Bindings tab schema for form elements
     *
     * @param FormVersion|null $formVersion The form version instance
     * @param int|null $formVersionId The form version ID if no instance is available
     * @param callable|null $shouldShowTooltipsCallback Callback to determine if tooltips should be shown
     * @param bool $disabled Whether the schema should be disabled (for view mode)
     * @param bool $useRelationship Whether to use the relationship() method on the repeater
     * @return array The schema array
     */
    public static function getDataBindingsSchema(
        ?FormVersion $formVersion = null,
        ?int $formVersionId = null,
        ?callable $shouldShowTooltipsCallback = null,
        bool $disabled = false,
        bool $useRelationship = false
    ): array {
        // Get form version instance if not provided
        if (!$formVersion && $formVersionId) {
            $formVersion = FormVersion::find($formVersionId);
        }

        // Handle case where no form version is available
        if (!$formVersion) {
            return [
                Placeholder::make('no_form_version')
                    ->label('')
                    ->content('Form version not available.')
            ];
        }

        // Handle case where no data sources are assigned
        if ($formVersion->formDataSources->isEmpty()) {
            $content = $disabled
                ? 'No Data Sources are assigned to this Form Version.'
                : 'Please add Data Sources in the Form Version before adding Data Bindings.';

            $placeholder = Placeholder::make('no_data_sources')
                ->label('')
                ->content($content);

            if (!$disabled) {
                $placeholder->extraAttributes(['class' => 'text-warning']);
            }

            return [$placeholder];
        }

        // Small helpers
        $resolveSource = function (Get $get): string {
            $id = (int) $get('form_data_source_id');
            return $id ? (string) (FormDataSource::find($id)?->name ?? '') : '';
        };
        $compose = static function (string $src, string $label): string {
            return ($src !== '' && $label !== '')
                ? "$.['{$src}'].['{$label}']"
                : '';
        };

        $findRepeating = static function (string $src, string $label): ?string {
            if ($src === '' || $label === '') {
                return null;
            }
            /** @var ?string $value */
            $value = DataBindingMapping::query()
                ->where('data_source', $src)
                ->where('path_label', $label)
                ->value('repeating_path');

            return $value ?: null;
        };

        // Build the repeater schema
        $repeater = Repeater::make('dataBindings')
            ->label('Data Bindings')
            ->schema([
                Select::make('form_data_source_id')
                    ->label('Data Source')
                    ->when($shouldShowTooltipsCallback && $shouldShowTooltipsCallback(), function ($component) {
                        return $component->hintIcon('heroicon-m-question-mark-circle', tooltip: 'The ICM Entity this data binding uses');
                    })
                    ->options(function () use ($formVersion) {
                        return $formVersion->formDataSources->pluck('name', 'id')->toArray();
                    })
                    ->searchable()
                    ->preload()
                    ->required(!$disabled)
                    ->disabled($disabled)
                    ->live(onBlur: true),
                // Path label
                self::pathLabelField(
                    sourceField: 'form_data_source_id',   
                    sourceIsId: true,
                    targetPathField: 'path',
                    targetRepeatingField: 'repeating_path',
                ),
                TextInput::make('path')
                    ->label('Data path')
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText("Composed as: $.['{Data source}'].['{Path label}']")
                    // When the form loads (view/edit), compose once so users see the preview
                    ->afterStateHydrated(function (Set $set, Get $get) use ($resolveSource, $compose) {
                        $src   = $resolveSource($get);
                        $label = (string) ($get('path_label') ?? '');
                        $set('path', $compose($src, $label));
                    })
                    ->reactive(),                    
                // Repeating path current set up as a preview only, shown only when it exists
                // possible todo: add a checking on form builder to check if the field is a repeatable container element
                TextInput::make('repeating_path')
                    ->label('Repeating path')
                    ->disabled()
                    ->dehydrated(false)
                    ->reactive()
                    ->visible(fn (Get $get) => filled($get('repeating_path')))
                    ->helperText("Repeating path for container element type e.g. $.['{Data source}'].[*]"),
                \Filament\Forms\Components\Textarea::make('condition')
                    ->label('Condition')
                    ->when($shouldShowTooltipsCallback && $shouldShowTooltipsCallback(), function ($component) {
                        return $component->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Custom script condition for this data binding');
                    })
                    ->disabled($disabled)
                    ->placeholder('Enter custom script condition...')
                    ->helperText('Optional custom script condition for this data binding')
                    ->rows(3),
            ])
            ->orderColumn('order')
            ->reorderableWithButtons()
            ->itemLabel(
                fn(array $state): ?string =>
                isset($state['form_data_source_id']) && isset($state['path'])
                    ? (FormDataSource::find($state['form_data_source_id'])?->name ?? 'Data Source') . ': ' . $state['path']
                    : 'New Data Binding'
            )
            ->addActionLabel('Add Data Binding')
            ->collapsible()
            ->collapsed()
            ->columnSpanFull();

        // Apply relationship if needed (for edit/view forms)
        if ($useRelationship) {
            $repeater->relationship();
        } else {
            // For create forms, set default items to 0
            $repeater->defaultItems(0);
        }

        // Disable the entire repeater if in disabled mode
        if ($disabled) {
            $repeater->disabled();
        }

        return [$repeater];
    }

    /**
     * Get the Data Bindings tab schema for create forms (BuildFormVersion)
     *
     * @param FormVersion $formVersion The form version instance
     * @param callable|null $shouldShowTooltipsCallback Callback to determine if tooltips should be shown
     * @return array The schema array
     */
    public static function getCreateSchema(
        FormVersion $formVersion,
        ?callable $shouldShowTooltipsCallback = null
    ): array {
        return self::getDataBindingsSchema(
            formVersion: $formVersion,
            shouldShowTooltipsCallback: $shouldShowTooltipsCallback,
            disabled: false,
            useRelationship: false
        );
    }

    /**
     * Get the Data Bindings tab schema for edit forms (FormElementTreeBuilder edit)
     *
     * @param int|null $formVersionId The form version ID
     * @param callable|null $shouldShowTooltipsCallback Callback to determine if tooltips should be shown
     * @return array The schema array
     */
    public static function getEditSchema(
        ?int $formVersionId,
        ?callable $shouldShowTooltipsCallback = null
    ): array {
        return self::getDataBindingsSchema(
            formVersionId: $formVersionId,
            shouldShowTooltipsCallback: $shouldShowTooltipsCallback,
            disabled: false,
            useRelationship: true
        );
    }

    /**
     * Get the Data Bindings tab schema for view forms (FormElementTreeBuilder view)
     *
     * @param int|null $formVersionId The form version ID
     * @param callable|null $shouldShowTooltipsCallback Callback to determine if tooltips should be shown
     * @return array The schema array
     */
    public static function getViewSchema(
        ?int $formVersionId,
        ?callable $shouldShowTooltipsCallback = null
    ): array {
        return self::getDataBindingsSchema(
            formVersionId: $formVersionId,
            shouldShowTooltipsCallback: $shouldShowTooltipsCallback,
            disabled: true,
            useRelationship: true
        );
    }

    /**
     * Reusable Path Label field.
     *
     * @param string      $sourceField          State key holding the source (id or name)
     * @param bool        $sourceIsId           True if $sourceField stores form_data_sources.id
     * @param string      $targetPathField      State key to receive composed JSONPath (e.g. 'path')
     * @param string|null $targetRepeatingField Optional state key to receive repeating path (if any)
     */
    public static function pathLabelField(
        string $sourceField,
        bool $sourceIsId = false,
        string $targetPathField = 'path',
        ?string $targetRepeatingField = null,
    ): TextInput {
        $resolveSource = function (Get $get) use ($sourceField, $sourceIsId): string {
            $raw = $get($sourceField);

            if ($sourceIsId) {
                $id = (int) $raw;
                if (!$id) {
                    return '';
                }

                return (string) (FormDataSource::find($id)->name ?? '');
            }

            return (string) $raw;
        };

        $compose = static function (string $src, string $label): string {
            return $src !== '' && $label !== '' ? "$.['{$src}'].['{$label}']" : '';
        };

        $lookupRepeating = static function (string $src, string $label): ?string {
            if ($src === '' || $label === '') {
                return null;
            }
            $value = DataBindingMapping::query()
                ->where('data_source', $src)
                ->where('path_label', $label)
                ->value('repeating_path');

            return $value ?: null;
        };

        return TextInput::make('path_label')
            ->label('Path label')
            ->placeholder('e.g. First Name')
            ->autocomplete(false)
            ->required()
            ->datalist(function (Get $get) use ($resolveSource): array {
                $src = $resolveSource($get);

                if ($src === '') {
                    return DataBindingMapping::query()
                        ->whereNotNull('path_label')
                        ->select('path_label')
                        ->groupBy('path_label')
                        ->orderBy('path_label')
                        ->limit(30)
                        ->pluck('path_label')
                        ->all();
                }

                return DataBindingMapping::query()
                    ->where('data_source', $src)
                    ->whereNotNull('path_label')
                    ->select('path_label')
                    ->groupBy('path_label')
                    ->orderBy('path_label')
                    ->limit(50)
                    ->pluck('path_label')
                    ->all();
            })
            ->live(onBlur: true)
            ->afterStateUpdated(function (Set $set, $state, Get $get) use (
                $resolveSource,
                $compose,
                $targetPathField,
                $lookupRepeating,
                $targetRepeatingField
            ) {
                $src   = $resolveSource($get);
                $label = (string) $state;

                // Compose preview path
                if ($src !== '' && $label !== '') {
                    $set($targetPathField, $compose($src, $label));
                } else {
                    $set($targetPathField, '');
                }

                // Fill/clear repeating path if a target is provided
                if ($targetRepeatingField !== null) {
                    $set($targetRepeatingField, $lookupRepeating($src, $label));
                }
            })
            ->afterStateHydrated(function (Set $set, $state, Get $get) use (
                $resolveSource,
                $compose,
                $targetPathField,
                $lookupRepeating,
                $targetRepeatingField
            ) {
                $src   = $resolveSource($get);
                $label = (string) ($state ?? '');

                if ($src !== '' && $label !== '') {
                    $set($targetPathField, $compose($src, $label));
                }

                if ($targetRepeatingField !== null) {
                    $set($targetRepeatingField, $lookupRepeating($src, $label));
                }
            });
    }
}
