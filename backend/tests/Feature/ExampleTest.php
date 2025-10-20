<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_is_accessible(): void
    {
        $response = $this->get('/');

        $response->assertOk();
    }

    public function test_expedientes_requires_authentication(): void
    {
        $response = $this->get('/expedientes');

        $response->assertRedirect(route('login'));
    }

    public function test_expedientes_index_is_accessible_for_authenticated_users(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/expedientes');

        $response->assertOk();
    }
}
