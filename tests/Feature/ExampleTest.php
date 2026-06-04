<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_docs_redirects_to_index_html(): void
    {
        $response = $this->get('/docs');
        $response->assertRedirect('/docs/index.html');
    }

    public function test_openapi_yaml_can_be_retrieved(): void
    {
        $response = $this->get('/docs/openapi.yaml');
        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/yaml; charset=utf-8');
    }

    public function test_root_openapi_yaml_can_be_retrieved(): void
    {
        $response = $this->get('/openapi.yaml');
        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/yaml; charset=utf-8');
    }
}
