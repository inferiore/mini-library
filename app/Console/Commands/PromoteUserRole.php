<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;

class PromoteUserRole extends Command
{
    protected $signature = 'users:promote {email} {role}';

    protected $description = 'Assign a role (admin|librarian|member) to an existing user by email';

    public function handle(): int
    {
        $email = $this->argument('email');
        $roleInput = strtolower($this->argument('role'));

        $role = UserRole::tryFrom($roleInput);

        if ($role === null) {
            $this->error(sprintf(
                'Invalid role "%s". Expected one of: %s.',
                $this->argument('role'),
                implode(', ', array_map(fn (UserRole $r) => $r->value, UserRole::cases())),
            ));

            return self::FAILURE;
        }

        $user = User::where('email', $email)->first();

        if ($user === null) {
            $this->error(sprintf('No user found with email "%s".', $email));

            return self::FAILURE;
        }

        $user->update(['role' => $role]);

        $this->info(sprintf('Updated %s to role "%s".', $user->email, $role->value));

        return self::SUCCESS;
    }
}
