<?php

namespace App\Filament\Forms\Resources;

use App\Filament\Forms\Resources\FormInterfaceResource\Pages;
use App\Models\FormMetadata\FormInterface;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Http\Middleware\CheckRole;
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
                        Forms\Components\TextInput::make('action_type')->maxLength(255),
                        Forms\Components\TextInput::make('type')->maxLength(255),
                        Forms\Components\TextInput::make('host')->maxLength(255),
                        Forms\Components\TextInput::make('path')->maxLength(255),
                        Forms\Components\TextInput::make('authentication')->maxLength(255),
                        Forms\Components\Repeater::make('headers')
                            ->label('Headers')
                            ->orderColumn('order')
                            ->reorderable()
                            ->schema([
                                Forms\Components\TextInput::make('key')->required(),
                                Forms\Components\TextInput::make('value')->required(),
                                Forms\Components\Hidden::make('order'),
                            ])
                            ->default([]),
                        Forms\Components\Repeater::make('body')
                            ->label('Body')
                            ->orderColumn('order')
                            ->reorderable()
                            ->schema([
                                Forms\Components\TextInput::make('key')->required(),
                                Forms\Components\TextInput::make('value')->required(),
                                Forms\Components\Hidden::make('order'),
                            ])
                            ->default([]),
                        Forms\Components\Repeater::make('parameters')
                            ->label('Parameters')
                            ->orderColumn('order')
                            ->reorderable()
                            ->schema([
                                Forms\Components\TextInput::make('key')->required(),
                                Forms\Components\TextInput::make('value')->required(),
                                Forms\Components\Hidden::make('order'),
                            ])
                            ->default([]),
                        Forms\Components\Hidden::make('order'),
                    ])
                    ->label('Actions')
                    ->itemLabel(
                        fn(array $state, ?int $index = null) =>
                        // Prefer $index (provided by Filament), fallback to 'order', fallback to 0
                        (isset($state['label']) && $state['label'] ? $state['label'] : '')
                    )
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('label')->searchable(),
                Tables\Columns\TextColumn::make('description')->searchable(),
                Tables\Columns\TextColumn::make('formVersion.form.name')->label('Form Name')->searchable(),
                Tables\Columns\TextColumn::make('type')->searchable(),
                Tables\Columns\TextColumn::make('style')->searchable(),

                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
