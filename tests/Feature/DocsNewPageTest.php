<?php

namespace Tests\Feature;

use Tests\TestCase;

class DocsNewPageTest extends TestCase
{
    public function test_docsnew_redirects_to_login_if_not_authenticated(): void
    {
        $response = $this->get('/docsnew');

        $response->assertRedirect('/docs/login');
    }

    public function test_docsnew_loads_when_authenticated(): void
    {
        $response = $this->withSession([
            'docs_authenticated' => true,
            'docs_last_activity' => time(),
        ])->get('/docsnew');

        $response->assertStatus(200);
        $filePath = $response->getFile()->getPathname();
        $this->assertFileExists($filePath);
        $content = file_get_contents($filePath);
        $this->assertStringContainsString('SMESTA API Documentation (Modern)', $content);
        $this->assertStringContainsString('scalar-reference-wrapper', $content);
        $this->assertStringContainsString('/docs/openapi.yaml', $content);
    }

    public function test_docs_page_has_docsnew_link(): void
    {
        $response = $this->withSession([
            'docs_authenticated' => true,
            'docs_last_activity' => time(),
        ])->get('/docs');

        $response->assertStatus(200);
        $filePath = $response->getFile()->getPathname();
        $this->assertFileExists($filePath);
        $content = file_get_contents($filePath);
        $this->assertStringContainsString('/docsnew', $content);
    }
}
