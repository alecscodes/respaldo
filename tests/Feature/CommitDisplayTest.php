<?php

use App\Models\User;

test('shares short commit hash on inertia pages', function () {
    config(['app.commit' => 'abcdef1234567890']);

    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->where('commit', 'abcdef1'));
});

test('commit is null when APP_COMMIT is not set', function () {
    config(['app.commit' => null]);

    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->where('commit', null));
});
