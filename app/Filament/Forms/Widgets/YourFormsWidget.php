<?php

namespace App\Filament\Forms\Widgets;

use App\Models\Form;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\Auth;

class YourFormsWidget extends TableWidget
{
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $user = Auth::user();
        $businessAreaIds = $user->businessAreas->pluck('id')->toArray();

        if (empty($businessAreaIds)) {
            return $table->query(Form::whereNull('id'));
        }

        return $table
            ->query(
                Form::query()
                    ->whereHas('businessAreas', function ($query) use ($businessAreaIds) {
                        $query->whereIn('business_areas.id', $businessAreaIds);
                    })
            )
            ->columns([
                Tables\Columns\TextColumn::make('form_id')
                    ->searchable()
                    ->sortable()
                    ->url(fn(Form $record): string => route('filament.forms.resources.forms.view', ['record' => $record])),
                Tables\Columns\TextColumn::make('form_title')
                    ->searchable()
                    ->sortable()
                    ->url(fn(Form $record): string => route('filament.forms.resources.forms.view', ['record' => $record])),
                Tables\Columns\TextColumn::make('ministry.short_name'),
                Tables\Columns\TagsColumn::make('businessAreas.name'),
                Tables\Columns\IconColumn::make('decommissioned')
                    ->label('Active')
                    ->boolean()
                    ->getStateUsing(fn(Form $record): bool => (bool) !$record->decommissioned),
            ])
            ->paginated([10, 25, 50, 100]);
    }

    public static function canView(): bool
    {
        return auth()->check() && Auth::user()->businessAreas()->exists();
    }
}
