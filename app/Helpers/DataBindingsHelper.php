<?php

namespace App\Helpers;

use App\Models\FormBuilding\FormVersion;
use App\Models\FormMetadata\FormDataSource;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Placeholder;

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
                TextInput::make('path')
                    ->label('Data Path')
                    ->when($shouldShowTooltipsCallback && $shouldShowTooltipsCallback(), function ($component) {
                        return $component->hintIcon('heroicon-m-question-mark-circle', tooltip: 'The full string referencing the ICM data');
                    })
                    ->required(!$disabled)
                    ->regex('/^(\$\.)?(\[\'[A-Za-z0-9_-]+\'\])(\.(\[\'[A-Za-z0-9_-]+\'\]))*$/')
                    ->disabled($disabled)
                    ->autocomplete(false)
                    ->placeholder("$.['Contact'].['Birth Date']")
                    ->helperText('The path to the data field in the selected data source'),
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
}
