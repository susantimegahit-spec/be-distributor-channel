<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/docs/login');
    }

    public function test_docs_requires_login(): void
    {
        $response = $this->get('/docs');
        $response->assertRedirect('/docs/login');
    }

    public function test_openapi_yaml_can_be_retrieved_with_session(): void
    {
        $response = $this->withSession([
            'docs_authenticated' => true,
            'docs_last_activity' => time(),
        ])->get('/docs/openapi.yaml');

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/yaml; charset=utf-8');
    }

    public function test_root_openapi_yaml_can_be_retrieved_with_session(): void
    {
        $response = $this->withSession([
            'docs_authenticated' => true,
            'docs_last_activity' => time(),
        ])->get('/openapi.yaml');

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/yaml; charset=utf-8');
    }

    public function test_docs_login_page_renders_pixel_art_template(): void
    {
        $response = $this->get('/docs/login');

        $response->assertStatus(200);
        $response->assertSee('SMESTA');
        $response->assertSee('pixelScene');
        $response->assertSee('NaCl • PIXEL ENGINE');
        $response->assertSee('PT SUSANTI MEGAH');
    }

    public function test_docs_login_authentication_success_and_failure(): void
    {
        // Failed login
        $failRes = $this->post('/docs/login', [
            'username' => 'wrong',
            'password' => 'wrong',
        ]);
        $failRes->assertRedirect('/docs/login');
        $failRes->assertSessionHas('error');

        // Success login
        $successRes = $this->post('/docs/login', [
            'username' => 'adminsm',
            'password' => 'adminsm!',
        ]);
        $successRes->assertRedirect('/docs');
        $successRes->assertSessionHas('docs_authenticated', true);
    }
}
