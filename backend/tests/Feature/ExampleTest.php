<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_redirects_to_expedientes(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/expedientes');
    }

    public function test_expedientes_index_is_accessible(): void
    {
        $response = $this->get('/expedientes');

        $response->assertOk();
    }
}
