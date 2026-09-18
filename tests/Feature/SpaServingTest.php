<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Production serves the built SPA from the same origin as the API (Sanctum
 * cookie auth needs that). Client routes must survive a refresh; unknown
 * API paths must stay JSON 404s.
 */
class SpaServingTest extends TestCase
{
    private string $index;

    protected function setUp(): void
    {
        parent::setUp();
        $this->index = tempnam(sys_get_temp_dir(), 'spa').'.html';
        file_put_contents($this->index, '<!doctype html><div id="root"></div>');
        config(['app.spa_index' => $this->index]);
    }

    protected function tearDown(): void
    {
        @unlink($this->index);
        parent::tearDown();
    }

    public function test_the_root_and_client_side_routes_return_the_spa(): void
    {
        $this->get('/')->assertOk()->assertSee('<div id="root">', false);
        $this->get('/dashboard')->assertOk()->assertSee('<div id="root">', false);
        $this->get('/sell/pos?x=1')->assertOk()->assertSee('<div id="root">', false);
    }

    public function test_api_auth_and_non_get_paths_are_never_answered_with_html(): void
    {
        $this->getJson('/api/does-not-exist')->assertNotFound();
        $this->get('/auth/nothing')->assertNotFound();
        $this->post('/dashboard')->assertStatus(405);
        $this->get('/up')->assertOk();
    }

    public function test_a_missing_build_is_a_clear_503(): void
    {
        config(['app.spa_index' => $this->index.'.missing']);

        $this->get('/dashboard')->assertStatus(503);
    }
}
