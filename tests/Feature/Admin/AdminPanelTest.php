<?php

use App\Models\User;

it('shows the filament admin login page', function () {
    $response = $this->get('/admin/login');

    $response->assertStatus(200);
    $response->assertSee('KAE');
});

it('redirects unauthenticated requests to admin login', function () {
    $response = $this->get('/admin');

    $response->assertRedirectToRoute('filament.admin.auth.login');
});

it('lets an authenticated user access the admin dashboard', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/admin');

    $response->assertSuccessful();
});
