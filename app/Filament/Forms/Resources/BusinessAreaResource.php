<?php

namespace App\Filament\Forms\Resources;

use App\Filament\Forms\Resources\BusinessAreaResource\Pages;
use App\Models\BusinessArea;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class BusinessAreaResource extends Resource
{
    protected static ?string $model = BusinessArea::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Form Metadata';

    protected static ?string $navigationLabel = 'Business Areas';


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required(),
                        Forms\Components\TextInput::make('short_name'),
                        Forms\Components\Textarea::make('description')
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Relationships')
                    ->schema([
                        Forms\Components\Select::make('ministries')
                            ->multiple()
                            ->preload()
                            ->relationship('ministries', 'name'),

                        Forms\Components\Select::make('users')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->relationship('users', 'name')
                            ->label('Contacts')
                            ->getOptionLabelFromRecordUsing(fn(User $user) => "{$user->name} ({$user->email})")
                            ->createOptionAction(
                                fn(Forms\Components\Actions\Action $action) => $action
                                    ->visible(fn() => Gate::allows('admin'))
                            )
                            ->createOptionForm(
                                [
                                    Forms\Components\TextInput::make('name')
                                        ->required(),
                                    Forms\Components\TextInput::make('email')
                                        ->email()
                                        ->required(),
                                ]
                            )->createOptionUsing(function (array $data) {
                                $password = \Illuminate\Support\Str::password(20);

                                $user = User::create([
                                    'name' => $data['name'],
                                    'email' => $data['email'],
                                    'password' => bcrypt($password),
                                    'created_via_business_area' => true,
                                ]);
                                $user->assignRole('forms-view-only');

                                $user->notify(new \App\Notifications\FormAccountCreatedNotification());

                                return $user->id;
                            }),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(BusinessArea::query()->with('users'))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('short_name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('ministries.name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('users')
                    ->label('Contacts')
                    ->listWithLineBreaks()
                    ->limitList(2)
                    ->expandableLimitedList()
                    ->formatStateUsing(function ($state) {
                        $state = $state->name . ' (' . $state->email . ')';
                        return $state;
                    })
            ])
            ->filters([
                //
            ])
            ->actions([
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
            'index' => Pages\ListBusinessAreas::route('/'),
            'create' => Pages\CreateBusinessArea::route('/create'),
            'edit' => Pages\EditBusinessArea::route('/{record}/edit'),
        ];
    }
}
