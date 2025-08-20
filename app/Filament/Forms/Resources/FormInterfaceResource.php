<?php

namespace App\Filament\Forms\Resources;

use App\Filament\Forms\Resources\FormInterfaceResource\Pages;
use App\Models\FormMetadata\FormInterface;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use App\Http\Middleware\CheckRole;
use App\Models\FormMetadata\InterfaceAction;
use Filament\Forms\Components\Grid;

class FormInterfaceResource extends Resource
{
    protected static ?string $model = FormInterface::class;

    protected static ?string $navigationIcon = 'icon-terminal';

    protected static ?string $navigationGroup = 'Form Building';

    protected static ?string $navigationLabel = 'Form Interfaces';

    public static function shouldRegisterNavigation(): bool
    {
        return CheckRole::hasRole(request(), 'admin', 'form-developer');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(1)
                    ->schema([
                        Forms\Components\TextInput::make('label')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('description')
                            ->maxLength(1000),
                        Forms\Components\TextInput::make('type')
                            ->required()
                            ->autocomplete(false)
                            ->datalist(FormInterface::types())
                            ->maxLength(255),
                        Forms\Components\TextInput::make('mode')
                            ->required()
                            ->autocomplete(false)
                            ->datalist(FormInterface::modes())
                            ->maxLength(255),
                        Forms\Components\TextInput::make('style')
                            ->maxLength(255),
                        Forms\Components\Textarea::make('condition')
                            ->label('Conditional Logic'),
                    ]),
                Forms\Components\Repeater::make('actions')
                    ->relationship('actions')
                    ->orderColumn('order')
                    ->reorderable()
                    ->collapsible()
                    ->schema([
                        Forms\Components\TextInput::make('label')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('action_type')->maxLength(255)
                            ->autocomplete(false)
                            ->datalist(InterfaceAction::actionTypes()),
                        Forms\Components\TextInput::make('type')->maxLength(255)
                            ->autocomplete(false)
                            ->datalist(InterfaceAction::types()),
                        Forms\Components\TextInput::make('host')->maxLength(255),
                        Forms\Components\TextInput::make('path')->maxLength(255),
                        Forms\Components\TextInput::make('authentication')->maxLength(255),
                        Forms\Components\Repeater::make('headers')
                            ->label('Headers')
                            ->schema([
                                Forms\Components\TextInput::make('key')->required(),
                                Forms\Components\Textarea::make('value')->required(),
                            ])
                            ->reorderable(false)
                            ->collapsible()
                            ->collapsed(true)
                            ->default([])
                            ->columnSpanFull()
                            ->itemLabel(fn(array $state) => self::getKeyValueItemLabel($state, 'Header')),
                        Forms\Components\Repeater::make('body')
                            ->label('Body')
                            ->schema([
                                Forms\Components\TextInput::make('key')->required(),
                                Forms\Components\Textarea::make('value')->required(),
                            ])
                            ->reorderable(false)
                            ->collapsible()
                            ->collapsed(true)
                            ->default([])
                            ->columnSpanFull()
                            ->itemLabel(fn(array $state) => self::getKeyValueItemLabel($state, 'Body Item')),
                        Forms\Components\Repeater::make('parameters')
                            ->label('Parameters')
                            ->schema([
                                Forms\Components\TextInput::make('key')->required(),
                                Forms\Components\Textarea::make('value')->required(),
                            ])
                            ->reorderable(false)
                            ->collapsible()
                            ->collapsed(true)
                            ->default([])
                            ->columnSpanFull()
                            ->itemLabel(fn(array $state) => self::getKeyValueItemLabel($state, 'Parameter')),
                        Forms\Components\Hidden::make('order'),
                    ])
                    ->label('Actions')
                    ->itemLabel(
                        fn(array $state) => (isset($state['label']) && $state['label'] ? $state['order'] . '. ' . $state['label'] : 'New Action')
                    )
                    ->columnSpanFull()
                    ->mutateRelationshipDataBeforeCreateUsing(function (array $data, $get) {
                        $data['order'] = count($get('actions') ?? []);
                        return $data;
                    })
                    ->mutateRelationshipDataBeforeSaveUsing(function (array $data, $get) {
                        if (!isset($data['order'])) {
                            $data['order'] = count($get('actions') ?? []);
                        }
                        return $data;
                    }),
            ]);
    }

    private static function getKeyValueItemLabel(array $state, string $itemType): string
    {
        if (!isset($state['key']) || empty($state['key'])) {
            return "New {$itemType}";
        }

        $key = $state['key'];

        if (!isset($state['value']) || empty($state['value'])) {
            return "{$key}: [valueNotSet]";
        }

        $value = $state['value'];
        if (strlen($value) > 30) {
            $value = substr($value, 0, 30) . '...';
        }

        return "{$key}: {$value}";
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFormInterfaces::route('/'),
            'create' => Pages\CreateFormInterface::route('/create'),
            'view' => Pages\ViewFormInterface::route('/{record}'),
            'edit' => Pages\EditFormInterface::route('/{record}/edit'),
        ];
    }

    public static function getModelLabel(): string
    {
        return 'Form Interface';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Form Interfaces';
    }
}
