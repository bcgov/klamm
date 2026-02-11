<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {

        Schema::table('users', function (Blueprint $table) {
            $table->softDeletes(); // adds deleted_at
        });

        DB::transaction(function () {
            // Create the user role if it doesn't exist yet
            $userRole = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);

            // Find users with the view-only roles and assign them the user role
            $viewOnlyRoles = ['fodig-view-only', 'forms-view-only', 'bre-view-only'];
            foreach ($viewOnlyRoles as $roleName) {
                try {
                    $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();

                    if ($role) {
                        // Get users with this role
                        $users = User::role($roleName)->get();

                        foreach ($users as $user) {
                            // Remove the old view-only role
                            $user->removeRole($roleName);
                            // Assign the new user role
                            if (!$user->hasRole('user')) {
                                $user->assignRole('user');
                            }
                        }

                        // Delete the old role
                        $role->delete();
                    }
                } catch (\Exception $e) {
                    // Log the error but continue with other roles
                    Log::warning("Error processing role {$roleName}: " . $e->getMessage());
                }
            }

            // Find users with no roles and assign them the user role
            try {
                $usersWithoutRoles = User::doesntHave('roles')->get();

                foreach ($usersWithoutRoles as $user) {
                    $user->assignRole('user');
                }
            } catch (\Exception $e) {
                Log::warning("Error assigning user role to users without roles: " . $e->getMessage());
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });


        DB::transaction(function () {
            $viewOnlyRoles = ['fodig-view-only', 'forms-view-only', 'bre-view-only'];

            foreach ($viewOnlyRoles as $roleName) {
                Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            }

            // Note: We don't reassign users back to their original view-only roles
            // since we don't know which specific view-only role they had originally.
            // This rollback creates the roles but leaves users with the 'user' role.
        });
    }
};
