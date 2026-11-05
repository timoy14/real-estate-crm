<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['admin@demo.test', 'Alex Admin', Role::Admin],
            ['anna@demo.test', 'Anna Reyes', Role::Agent],
            ['ben@demo.test', 'Ben Carter', Role::Agent],
        ];

        foreach ($users as [$email, $name, $role]) {
            $user = User::firstOrNew(['email' => $email]);
            $user->name = $name;
            $user->password = 'password'; // hashed by the model cast
            $user->role = $role;
            $user->email_verified_at ??= now();
            $user->save();
        }
    }
}
