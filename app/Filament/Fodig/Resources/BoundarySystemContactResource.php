<?php

namespace App\Filament\Fodig\Resources;

use App\Filament\Fodig\Resources\BoundarySystemContactResource\Pages;
use App\Models\BoundarySystemContact;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Repeater;

class BoundarySystemContactResource extends Resource
{
    protected static ?string $model = BoundarySystemContact::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Contacts';

    protected static ?string $navigationGroup = 'Data Gateway';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->columnSpanFull()
                    ->maxLength(255),
                TextInput::make('organization')
                    ->required()
                    ->columnSpanFull()
                    ->maxLength(255),
                Textarea::make('notes')
                    ->columnSpanFull(),
                Repeater::make('emails')
                    ->label('Email Addresses')
                    ->relationship('emails')
                    ->columnSpanFull()
                    ->minItems(1)
                    ->schema([
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->maxLength(255),
                    ])
                    ->addActionLabel('Add Email'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->columnSpanFull()
                    ->searchable(),
                Tables\Columns\TextColumn::make('organization')
                    ->columnSpanFull()
                    ->searchable(),
                Tables\Columns\TextColumn::make('emails_list')
                    ->label('Emails')
                    ->getStateUsing(fn($record) => $record->emails->pluck('email')->join(', '))
                    ->columnSpanFull()
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                //
            ])
            ->paginated([
                10,
                25,
                50,
                100,
            ]);;
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
            'index' => Pages\ListBoundarySystemContacts::route('/'),
            'create' => Pages\CreateBoundarySystemContact::route('/create'),
            'view' => Pages\ViewBoundarySystemContact::route('/{record}'),
            'edit' => Pages\EditBoundarySystemContact::route('/{record}/edit'),
        ];
    }
}
