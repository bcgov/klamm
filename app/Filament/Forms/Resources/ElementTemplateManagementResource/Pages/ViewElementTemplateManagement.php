<?php

namespace App\Filament\Forms\Resources\ElementTemplateManagementResource\Pages;

use App\Filament\Forms\Resources\ElementTemplateManagementResource;
use App\Helpers\GeneralTabHelper;
use App\Models\FormBuilding\FormElement;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\ViewRecord;
use Filament\Forms\Components\Actions\Action as FieldAction;

class ViewElementTemplateManagement extends ViewRecord
{
    protected static string $resource = ElementTemplateManagementResource::class;

    protected static ?string $title = 'View Element Template';

    // Back & Edit buttons(Edit only if not parented)
    protected function getHeaderActions(): array
    {
        /** @var FormElement $record */
        $record = $this->getRecord();

        $isParented =
            ! is_null($record->form_version_id) ||
            (property_exists($record, 'parent_id') && $record->parent_id !== null && $record->parent_id !== -1);

        return [
            Action::make('back')
                ->label('Back')
                ->icon('heroicon-o-arrow-left')
                ->url(ElementTemplateManagementResource::getUrl('index'))
                ->color('secondary'),

            Actions\EditAction::make()
                ->label('Edit')
                ->icon('heroicon-o-pencil-square')
                ->url(ElementTemplateManagementResource::getUrl('edit', ['record' => $record]))
                ->visible(! $isParented),
        ];
    }

    public function form(Form $form): Form
    {
        $schema = GeneralTabHelper::getCreateSchema(
            shouldShowTooltipsCallback: fn () => (bool) (auth()->user()?->tooltips_enabled ?? false),
            includeTemplateSelector: false,
            disabledCallback: null
        );

        // Keep is_template ON, locked and hidden
        $schema = $this->tweakIsTemplateField($schema, hide: true);

        // UUID
        $schema[] = Grid::make(12)->schema([
            TextInput::make('_reference_uuid_display')
                ->label('Reference UUID')
                ->disabled()
                ->dehydrated(false)
                ->formatStateUsing(function ($state, ?FormElement $record) {
                    if (! $record) return '';
                    return $record->reference_uuid
                        ?? $record->uuid
                        ?? $record->public_id
                        ?? data_get($record, 'meta.reference_uuid')
                        ?? (string) $record->getAttribute('id');
                })
                ->extraInputAttributes(['x-ref' => 'uuidField'])
                ->suffixAction(
                    FieldAction::make('copy_uuid')
                        ->icon('heroicon-o-clipboard')
                        ->tooltip('Copy Reference UUID')
                        ->extraAttributes(['x-data' => '{}', 'x-on:click' => 'navigator.clipboard.writeText($refs.uuidField.value)'])
                )
                ->columnSpan(12),
            TextInput::make('_data_path_display')
                ->label('Data path')
                ->disabled()
                ->dehydrated(false)
                ->formatStateUsing(function ($state, ?FormElement $record) {
                    if (! $record) return '';
                    return optional($record->dataBindings)
                        ? $record->dataBindings->pluck('path')->filter()->unique()->join(', ')
                        : '';
                })
                ->extraInputAttributes(['x-ref' => 'dataPath'])
                ->suffixAction(
                    FieldAction::make('copy_path')
                        ->icon('heroicon-o-clipboard')
                        ->tooltip('Copy data path')
                        ->extraAttributes([
                            'x-data' => '{}',
                            'x-on:click' => 'navigator.clipboard.writeText($refs.dataPath.value)',
                        ])
                )
                ->columnSpan(12),           
        ]);

        // keep read-only
        return $form->schema($this->deepDisable($schema));
    }


    protected function authorizeAccess(): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole('admin'), 403);
    }

    // Disable all controls for read-only rendering
    private function deepDisable(array $components): array
    {
        $map = function (Component $c) use (&$map): Component {
            if (method_exists($c, 'getChildComponents')) {
                $children = [];
                foreach ($c->getChildComponents() as $child) {
                    $children[] = $map($child)->disabled();
                }
                if (method_exists($c, 'childComponents'))   $c = $c->childComponents($children)->disabled();
                elseif (method_exists($c, 'schema'))        $c = $c->schema($children)->disabled();
                else                                        $c = $c->disabled();
            } else {
                $c = $c->disabled();
            }
            return $c;
        };

        return array_map(fn ($comp) => $map($comp), $components);
    }
    
    // Lock and/or hide the is_template toggle in the reused schema
    private function tweakIsTemplateField(array $components, bool $hide): array
    {
        $map = function (Component $c) use (&$map, $hide): ?Component {
            if (method_exists($c, 'getChildComponents')) {
                $children = [];
                foreach ($c->getChildComponents() as $child) {
                    if ($child instanceof Toggle && $child->getName() === 'is_template') {
                        if ($hide) continue;
                        $child = $child->default(true)->disabled()->dehydrated(true);
                    } else {
                        $child = $map($child);
                    }
                    if ($child) $children[] = $child;
                }
                if (method_exists($c, 'childComponents'))   $c = $c->childComponents($children);
                elseif (method_exists($c, 'schema'))        $c = $c->schema($children);
            } else {
                if ($c instanceof Toggle && $c->getName() === 'is_template') {
                    return $hide ? Toggle::make('is_template')->hidden()->default(true)->dehydrated(true)
                                 : $c->default(true)->disabled()->dehydrated(true);
                }
            }
            return $c;
        };

        $out = [];
        foreach ($components as $comp) {
            $out[] = $map($comp);
        }
        return $out;
    }

    // Resolve UUID for display
    private function resolveReferenceUuid(): ?string
    {
        /** @var FormElement|null $record */
        $record = $this->getRecord();

        if (! $record) return null;

        return $record->reference_uuid
            ?? $record->uuid
            ?? $record->public_id
            ?? data_get($record, 'meta.reference_uuid')
            ?? (string) $record->getAttribute('id');
    }
}
