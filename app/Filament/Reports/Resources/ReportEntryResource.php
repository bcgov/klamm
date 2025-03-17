<?php

namespace App\Filament\Reports\Resources;

use App\Filament\Reports\Resources\ReportEntryResource\Pages;
use App\Models\ReportEntry;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\ExportAction;
use Filament\Tables\Actions\ImportAction;
use App\Filament\Exports\ReportEntryExporter;
use App\Filament\Imports\ReportEntryImporter;

class ReportEntryResource extends Resource
{
    protected static ?string $model = ReportEntry::class;

    protected static ?string $label = 'Report Label';

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Report Labels';


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('business_area_id')
                    ->relationship('reportBusinessArea', 'name')
                    ->required(),
                Forms\Components\Select::make('report_id')
                    ->relationship('report', 'name')
                    ->required(),
                Forms\Components\TextInput::make('existing_label')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('label_source_id')
                    ->relationship('labelSource', 'name'),
                Forms\Components\TextInput::make('data_field')
                    ->maxLength(255),
                Forms\Components\TextInput::make('icm_data_field_path')
                    ->label('ICM Data Field Path')
                    ->maxLength(255),
                Forms\Components\Select::make('data_matching_rate')
                    ->options([
                        'easy' => 'Easy',
                        'medium' => 'Medium',
                        'complex' => 'Complex',
                    ]),
                Forms\Components\Textarea::make('note')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reportBusinessArea.name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('report.name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('existing_label')
                    ->searchable(),
                Tables\Columns\TextColumn::make('labelSource.name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('data_field')
                    ->searchable(),
                Tables\Columns\TextColumn::make('icm_data_field_path')
                    ->label('ICM Data Field Path'),
                Tables\Columns\TextColumn::make('data_matching_rate')
                    ->badge()
                    ->colors([
                        'success' => static fn($state): bool => $state === 'easy',
                        'warning' => static fn($state): bool => $state === 'medium',
                        'danger' => static fn($state): bool => $state === 'complex',
                    ]),
                Tables\Columns\TextColumn::make('lastUpdatedBy.name')
                    ->label('Last Updated By'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('business_area_id')
                    ->relationship('reportBusinessArea', 'name')
                    ->preload()
                    ->label('Business Area'),
                Tables\Filters\SelectFilter::make('label_source_id')
                    ->relationship('labelSource', 'name')
                    ->preload()
                    ->label('Label Source'),
                Tables\Filters\SelectFilter::make('data_matching_rate')
                    ->options([
                        'easy' => 'Easy',
                        'medium' => 'Medium',
                        'complex' => 'Complex',
                    ])
                    ->label('Data Matching Rate'),
                Tables\Filters\SelectFilter::make('report_id')
                    ->relationship('report', 'name')
                    ->preload()
                    ->label('Report'),
                Tables\Filters\SelectFilter::make('last_updated_by')
                    ->relationship('lastUpdatedBy', 'name')
                    ->preload()
                    ->label('Last Updated By'),
            ])
            ->actions([
                ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\DeleteAction::make()
                        ->requiresConfirmation(),
                ])->icon('heroicon-m-ellipsis-vertical')
            ], position: ActionsPosition::BeforeColumns)
            ->headerActions([
                ExportAction::make()
                    ->exporter(ReportEntryExporter::class),
                ImportAction::make('Import CSV')
                    ->importer(ReportEntryImporter::class)
            ])
            ->paginated([
                10,
                25,
                50,
                100,
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
            'index' => Pages\ListReportEntries::route('/'),
            'create' => Pages\CreateReportEntry::route('/create'),
            'view' => Pages\ViewReportEntry::route('/{record}'),
            'edit' => Pages\EditReportEntry::route('/{record}/edit'),
        ];
    }
}
