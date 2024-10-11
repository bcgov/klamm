<?php

namespace App\Filament\Forms\Resources;

use App\Filament\Forms\Resources\RenderedFormResource\Pages;
use App\Models\RenderedForm;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;
use App\Filament\Components\JsonValidator;

class RenderedFormResource extends Resource
{
    protected static ?string $model = RenderedForm::class;

    protected static ?string $navigationIcon = 'heroicon-o-document';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationGroup = 'Form Rendering';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('description')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\BelongsToSelect::make('ministry_id')
                        ->relationship('ministry', 'name')
                        ->required(),
                    Forms\Components\BelongsToSelect::make('created_by')
                        ->relationship('user', 'name')
                        ->required()
                        ->default(auth()->id()),
                    JsonValidator::make()->jsonToValidate('structure'),
                    Forms\Components\Textarea::make('structure')
                        ->required()
                        ->maxLength(65535)
                        ->rows(15),
                ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('user.name')->label('Created By')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('created_at')->label('Created At')->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Preview Form')
                    ->url(fn (RenderedForm $record): string => route('forms.rendered_forms.view', $record)),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                //
            ])
            ->paginated([
                10, 25, 50, 100,
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
            'index' => Pages\ListRenderedForms::route('/'),
            'create' => Pages\CreateRenderedForm::route('/create'),
            'form-builder' => Pages\FormBuilderPage::route('/form-builder'),
            'edit' => Pages\EditRenderedForm::route('/{record}/edit'),
        ];
    }
}
