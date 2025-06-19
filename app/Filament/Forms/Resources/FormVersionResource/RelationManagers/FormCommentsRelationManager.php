<?php

namespace App\Filament\Forms\Resources\FormVersionResource\RelationManagers;

use App\Models\FormComment;
use Filament\Actions\Modal\Actions\Action;
use Filament\Forms\Form;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Grid as InfolistGrid;

use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;

use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Grouping\Group;

class FormCommentsRelationManager extends RelationManager
{
    protected static string $relationship = 'formComments';

    protected static ?string $recordTitleAttribute = 'text';

    public function form(Form $form): Form
    {
        return $form->schema([
            //
        ]);
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make()
                    ->schema([
                        InfolistGrid::make(1)
                            ->schema([
                                TextEntry::make('commenter')
                                    ->label('Commenter'),
                                TextEntry::make('email')
                                    ->label('Email'),
                                TextEntry::make('threaded_context')
                                    ->label('Threaded Context')
                                    ->getStateUsing(fn($record) => $record->threaded_context)
                                    ->html(),
                                TextEntry::make('resolved')
                                    ->label('Resolved')
                                    ->badge()
                                    ->color(fn($state) => $state ? 'success' : 'danger')
                                    ->formatStateUsing(fn($state) => $state ? 'Yes' : 'No'),
                                TextEntry::make('created_at')
                                    ->label('Created')
                                    ->dateTime(),
                            ]),
                    ]),
            ]);
    }



    public function table(Table $table): Table
    {
        return $table
            ->heading('Form Comments')
            // ->deferLoading()
            ->query(
                FormComment::query()
                    ->where('form_version_id', $this->getOwnerRecord()->id)
                    ->whereNull('parent_comment_id')
                    ->with(['parent', 'children'])
            )
            ->columns([
                TextColumn::make('commenter')
                    ->label('Commenter')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('text')
                    ->label('Comment')
                    ->limit(80)
                    ->wrap(),
                TextColumn::make('resolved')
                    ->label('Resolved')
                    ->badge()
                    ->color(fn($state) => $state ? 'success' : 'danger')
                    ->formatStateUsing(fn($state) => $state ? 'Yes' : 'No'),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime(),
                TextColumn::make('threaded_context')
                    ->label('Threaded Context')
                    ->getStateUsing(fn($record) => $record->threaded_context)
                    ->html()
                    ->wrap(),
            ])
            ->defaultGroup('parent_comment_id')
            // ->defaultSort('created_at', 'desc')

            ->groups([
                Group::make('parent_comment_id')
                    ->collapsible()
                    ->label('Parent Comment')
                // ->orderQueryUsing(fn(Builder $query) => $query->orderBy('created_at', 'desc'))
                // ->groupBy(fn(Builder $query) => $query->whereNull('parent_comment_id')),
            ])
            ->bulkActions([
                BulkAction::make('resolve')
                    ->label('Resolve Selected')
                    ->action(function ($records) {
                        foreach ($records as $record) {
                            $record->update(['resolved' => true]);
                        }
                    })
                    ->requiresConfirmation()
                    ->color('success'),
                BulkAction::make('unresolve')
                    ->label('Unresolve Selected')
                    ->action(function ($records) {
                        foreach ($records as $record) {
                            $record->update(['resolved' => false]);
                        }
                    })
                    ->requiresConfirmation()
                    ->color('warning'),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
