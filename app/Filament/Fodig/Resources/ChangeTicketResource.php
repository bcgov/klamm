<?php

namespace App\Filament\Fodig\Resources;

use App\Filament\Fodig\Resources\ChangeTicketResource\Pages;
use App\Models\Anonymizer\ChangeTicket;
use App\Models\Anonymizer\AnonymousUpload;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms;
use Filament\Forms\Components\Section as FormSection;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ChangeTicketResource extends Resource
{
    protected static ?string $model = ChangeTicket::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';
    protected static ?string $navigationGroup = 'Anonymizer';
    protected static ?string $navigationLabel = 'Change Tickets';
    protected static ?int $navigationSort = 75;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->orderByRaw("CASE severity WHEN 'high' THEN 3 WHEN 'medium' THEN 2 WHEN 'low' THEN 1 ELSE 0 END DESC")
            ->orderByRaw("CASE status WHEN 'open' THEN 3 WHEN 'in_progress' THEN 2 WHEN 'resolved' THEN 1 WHEN 'dismissed' THEN 0 ELSE 0 END DESC")
            ->orderByDesc('created_at');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                FormSection::make('Ticket')
                    ->schema([
                        TextInput::make('title')->required()->maxLength(255),
                        ToggleButtons::make('status')
                            ->options([
                                'open' => 'Open',
                                'in_progress' => 'In Progress',
                                'resolved' => 'Resolved',
                                'dismissed' => 'Dismissed',
                            ])->inline()->required(),
                        ToggleButtons::make('priority')
                            ->options([
                                'low' => 'Low',
                                'normal' => 'Normal',
                                'high' => 'High',
                            ])->inline()->required(),
                        ToggleButtons::make('severity')
                            ->options([
                                'low' => 'Low',
                                'medium' => 'Medium',
                                'high' => 'High',
                            ])
                            ->inline()
                            ->required(),
                        Select::make('scope_type')
                            ->options([
                                'database' => 'Database',
                                'schema' => 'Schema',
                                'table' => 'Table',
                                'column' => 'Column',
                                'upload' => 'Upload',
                            ]),
                        TextInput::make('scope_name'),
                        Select::make('upload_id')
                            ->relationship('upload', 'original_name')
                            ->searchable(),
                        Textarea::make('impact_summary')->rows(4),
                        Textarea::make('diff_payload')->rows(6),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->wrap(),
                TextColumn::make('status')->badge(),
                TextColumn::make('priority')->badge(),
                TextColumn::make('severity')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'high' => 'danger',
                        'medium' => 'warning',
                        'low' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('scope_type')->label('Scope')->toggleable(),
                TextColumn::make('scope_name')->toggleable(),
                TextColumn::make('upload.original_name')->label('Upload')->toggleable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
                TextColumn::make('resolved_at')->dateTime()->sortable()->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'open' => 'Open',
                        'in_progress' => 'In Progress',
                        'resolved' => 'Resolved',
                        'dismissed' => 'Dismissed',
                    ]),
                Tables\Filters\SelectFilter::make('priority')
                    ->options([
                        'low' => 'Low',
                        'normal' => 'Normal',
                        'high' => 'High',
                    ]),
                Tables\Filters\SelectFilter::make('severity')
                    ->options([
                        'high' => 'High',
                        'medium' => 'Medium',
                        'low' => 'Low',
                    ]),
                Tables\Filters\Filter::make('latest_upload')
                    ->label('Latest Upload')
                    ->toggle()
                    ->query(function (Builder $query): Builder {
                        $latestId = AnonymousUpload::query()->max('id');
                        if ($latestId) {
                            $query->where('upload_id', $latestId);
                        }
                        return $query;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListChangeTickets::route('/'),
            'create' => Pages\CreateChangeTicket::route('/create'),
            'edit' => Pages\EditChangeTicket::route('/{record}/edit'),
        ];
    }
}
