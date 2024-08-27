<?php

namespace App\Filament\Forms\Resources;

use App\Filament\Forms\Resources\FormVersionResource\Pages;
use App\Filament\Forms\Resources\FormVersionResource\RelationManagers;
use App\Models\FormVersion;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class FormVersionResource extends Resource
{
    protected static ?string $model = FormVersion::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('form_id')
                    ->relationship('form', 'form_title')
                    ->required(),
                Forms\Components\TextInput::make('version_number')
                    ->required(),
                Forms\Components\Select::make('status')
                    ->options([
                        'Requested' => 'Requested',
                        'Active' => 'Active',
                        'Archived' => 'Archived',
                        'In Review' => 'In Review',
                        'Ready to Release' => 'Ready to Release',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('form_requester_name')
                    ->maxLength(255),
                Forms\Components\TextInput::make('form_requester_email')
                    ->email()
                    ->maxLength(255),
                Forms\Components\TextInput::make('form_developer_name')
                    ->maxLength(255),
                Forms\Components\TextInput::make('form_developer_email')
                    ->email()
                    ->maxLength(255),
                Forms\Components\TextInput::make('form_approver_name')
                    ->maxLength(255),
                Forms\Components\TextInput::make('form_approver_email')
                    ->email()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('form_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('version_number')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->searchable(),
                Tables\Columns\TextColumn::make('form_requester_name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('form_requester_email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('form_developer_name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('form_developer_email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('form_approver_name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('form_approver_email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
            'index' => Pages\ListFormVersions::route('/'),
            'create' => Pages\CreateFormVersion::route('/create'),
            'view' => Pages\ViewFormVersion::route('/{record}'),
            'edit' => Pages\EditFormVersion::route('/{record}/edit'),
        ];
    }
}
