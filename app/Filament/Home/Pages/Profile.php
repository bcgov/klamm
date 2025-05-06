<?php

namespace App\Filament\Home\Pages;

use Filament\Forms;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Section;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Actions;
use Illuminate\Support\Str;

class Profile extends Page
{
    protected static ?string $navigationGroup = 'My Account';
    protected static ?string $navigationLabel = 'Profile';
    protected static ?string $navigationIcon = 'heroicon-o-user';
    protected static string $view = 'filament.home.pages.profile';
    protected static ?int $navigationSort = 2;

    public $name;
    public $email;
    public $current_password;
    public $new_password;
    public $new_password_confirmation;
    public $api_token;

    public function mount()
    {
        $user = Auth::user();

        if (!$user) {
            abort(401, '');
        }

        $this->name = $user->name;
        $this->email = $user->email;
    }

    protected function getFormSchema(): array
    {
        $schema = [
            Section::make('Profile Information')
                ->schema([
                    TextInput::make('name')
                        ->label('Name')
                        ->required(),
                    TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->required(),
                    Actions::make([
                        Action::make('updateProfile')
                            ->label('Update Profile')
                            ->action('updateProfile')
                    ])
                ]),
            Section::make('Update Password')
                ->schema([
                    TextInput::make('current_password')
                        ->label('Current Password')
                        ->password()
                        ->required(),
                    TextInput::make('new_password')
                        ->label('New Password')
                        ->password()
                        ->required(),
                    TextInput::make('new_password_confirmation')
                        ->label('Confirm New Password')
                        ->password()
                        ->same('new_password')
                        ->required(),
                    Actions::make([
                        Action::make('updatePassword')
                            ->label('Update Password')
                            ->action('updatePassword')
                    ])
                ]),
        ];

        if (optional(Auth::user())->hasRole('admin')) {
            $schema[] = Section::make('API Token Management')
                ->schema([
                    TextInput::make('api_token')
                        ->label('API Token')
                        ->disabled()
                        ->default(fn() => session('api_token_plaintext')),
                    Actions::make([
                        Action::make('updateApiToken')
                            ->label('Regenerate API Token')
                            ->action('updateApiToken')
                            ->color('danger')
                    ])
                ]);
        }

        return $schema;
    }

    public function updateProfile()
    {
        $user = Auth::user();

        $validatedData = $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
        ]);

        $user->name = $validatedData['name'];
        $user->email = $validatedData['email'];

        /** @var \App\Models\User $user **/
        $user->save();

        Notification::make()
            ->title("Profile updated successfully!")
            ->success()
            ->send();
    }

    public function updatePassword()
    {
        $user = Auth::user();

        if (!Hash::check($this->current_password, $user->password)) {
            $this->addError('current_password', 'The provided password does not match your current password.');
            return;
        }

        $validatedData = $this->validate([
            'current_password' => 'required',
            'new_password' => 'required|string|min:8|confirmed',
            'new_password_confirmation' => 'required|string|same:new_password',
        ]);

        $user->password = Hash::make($this->new_password);

        /** @var \App\Models\User $user **/
        $user->save();

        $this->current_password = '';
        $this->new_password = '';
        $this->new_password_confirmation = '';

        Notification::make()
            ->title("Password updated successfully!")
            ->success()
            ->send();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return Auth::check();
    }

    public function updateApiToken()
    {
        $user = Auth::user();

        /** @var \App\Models\User $user **/

        $tokenId = $user->email;
        $user->tokens()->where('name', $tokenId)->delete();

        $abilities = $user->getRoleNames()->toArray();
        $token = $user->createToken($tokenId, $abilities);
        $tokenPlainText = $token->plainTextToken;

        $this->api_token = $tokenPlainText;

        Notification::make()
            ->title("API Token updated successfully!")
            ->success()
            ->send();
    }
}
