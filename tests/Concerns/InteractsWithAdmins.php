<?php

namespace Tests\Concerns;

use App\Models\User;

trait InteractsWithAdmins
{
    /**
     * Create an administrator account for feature tests.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function adminUser(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);

        $user->forceFill(['is_admin' => true])->save();

        return $user;
    }
}
