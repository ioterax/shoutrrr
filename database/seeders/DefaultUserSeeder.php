<?php

namespace Database\Seeders;

use App\Enums\InstanceRole;
use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Database\Seeder;
use LogicException;

class DefaultUserSeeder extends Seeder
{
    /**
     * Seed the default development user and workspace.
     */
    public function run(): void
    {
        if (! app()->isLocal()) {
            throw new LogicException('The default development users may be seeded only in the local environment.');
        }

        $user = User::query()->firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => 'password',
                'email_verified_at' => now(),
                'instance_role' => InstanceRole::Owner,
            ],
        );

        if (! $user->isInstanceOwner()) {
            $user->forceFill(['instance_role' => InstanceRole::Owner])->save();
        }

        $workspace = Workspace::query()->firstOrCreate(
            ['slug' => 'test-workspace'],
            [
                'name' => 'Test Workspace',
                'owner_id' => $user->id,
                'timezone' => 'UTC',
            ],
        );

        WorkspaceMembership::query()->firstOrCreate(
            [
                'workspace_id' => $workspace->id,
                'user_id' => $user->id,
            ],
            ['role' => WorkspaceRole::Owner],
        );

        $user->forceFill(['current_workspace_id' => $workspace->id])->save();

        $secondUser = User::query()->firstOrCreate(
            ['email' => 'test2@example.com'],
            [
                'name' => 'Test User 2',
                'password' => 'password',
                'email_verified_at' => now(),
            ],
        );

        WorkspaceMembership::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $secondUser->id)
            ->delete();

        $secondWorkspace = Workspace::query()->firstOrCreate(
            ['slug' => 'test-workspace-2'],
            [
                'name' => 'Test Workspace 2',
                'owner_id' => $secondUser->id,
                'timezone' => 'UTC',
            ],
        );

        WorkspaceMembership::query()->firstOrCreate(
            [
                'workspace_id' => $secondWorkspace->id,
                'user_id' => $secondUser->id,
            ],
            ['role' => WorkspaceRole::Owner],
        );

        $secondUser->forceFill(['current_workspace_id' => $secondWorkspace->id])->save();
    }
}
