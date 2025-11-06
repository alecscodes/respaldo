<?php

use App\Models\App;
use App\Models\User;
use App\Services\StorageConverter;

test('guests cannot access apps', function () {
    $response = $this->get(route('apps.index'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can view apps index', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('apps.index'));
    $response->assertStatus(200);
});

test('users can create an app', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('apps.store'), [
        'name' => 'Test App',
        'storage_size' => 10.5, // GB
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('apps', [
        'name' => 'Test App',
        'user_id' => $user->id,
        'storage_size' => StorageConverter::gbToBytes(10.5),
    ]);
});

test('users can view their app', function () {
    $user = User::factory()->create();
    $app = App::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    $response = $this->get(route('apps.show', $app));
    $response->assertStatus(200);
});

test('users cannot view other users apps', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $app = App::factory()->create(['user_id' => $otherUser->id]);
    $this->actingAs($user);

    $response = $this->get(route('apps.show', $app));
    $response->assertForbidden();
});

test('users can update their app', function () {
    $user = User::factory()->create();
    $app = App::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    $response = $this->put(route('apps.update', $app), [
        'name' => 'Updated App',
        'storage_size' => 20,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('apps', [
        'id' => $app->id,
        'name' => 'Updated App',
        'storage_size' => StorageConverter::gbToBytes(20),
    ]);
});

test('users can delete their app', function () {
    $user = User::factory()->create();
    $app = App::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    $response = $this->delete(route('apps.destroy', $app));
    $response->assertRedirect(route('apps.index'));
    $this->assertDatabaseMissing('apps', ['id' => $app->id]);
});
