<?php

namespace App\Filament\Forms\Resources;

use App\Filament\Forms\Resources\FormDeploymentResource\Pages;
use App\Models\FormDeployment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class FormDeploymentResource extends Resource
{
    protected static ?string $model = FormDeployment::class;

    protected static ?string $navigationIcon = 'heroicon-o-cloud-arrow-up';

    protected static ?string $navigationGroup = 'Form Management';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('form_version_id')
                    ->relationship('formVersion', 'id')
                    ->required()
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('environment')
                    ->options([
                        'test' => 'Test',
                        'dev' => 'Development',
                        'prod' => 'Production',
                    ])
                    ->required(),
                Forms\Components\DateTimePicker::make('deployed_at')
                    ->required()
                    ->default(now()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('formVersion.id')
                    ->label('Form Version')
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('environment')
                    ->colors([
                        'danger' => 'prod',
                        'warning' => 'dev',
                        'success' => 'test',
                    ]),
                Tables\Columns\TextColumn::make('deployed_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('environment')
                    ->options([
                        'test' => 'Test',
                        'dev' => 'Development',
                        'prod' => 'Production',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListFormDeployments::route('/'),
            'create' => Pages\CreateFormDeployment::route('/create'),
            'edit' => Pages\EditFormDeployment::route('/{record}/edit'),
        ];
    }
}
