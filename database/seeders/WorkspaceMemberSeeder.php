<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class WorkspaceMemberSeeder extends Seeder
{
    public function run(): void
    {
        $workspace = Workspace::first();

        if (! $workspace) {
            $this->command->warn('WorkspaceMemberSeeder: no workspace found — skipping.');

            return;
        }

        if ($workspace->members()->count() > 1) {
            $this->command->info('WorkspaceMemberSeeder: workspace already has members — skipping.');

            return;
        }

        $members = [
            ['name' => 'Alice Editor', 'email' => 'alice@demo.test', 'role' => Role::Editor],
            ['name' => 'Bob Member', 'email' => 'bob@demo.test', 'role' => Role::Member],
            ['name' => 'Carol Viewer', 'email' => 'carol@demo.test', 'role' => Role::Viewer],
        ];

        foreach ($members as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                ['name' => $data['name'], 'password' => bcrypt('password')],
            );

            if (! $workspace->members()->where('users.id', $user->id)->exists()) {
                $workspace->members()->attach($user->id, [
                    'id' => Str::uuid()->toString(),
                    'role' => $data['role'],
                    'joined_at' => now()->subDays(rand(1, 30)),
                ]);
            }
        }

        $this->command->info('WorkspaceMemberSeeder: added '.count($members).' demo members.');
    }
}
