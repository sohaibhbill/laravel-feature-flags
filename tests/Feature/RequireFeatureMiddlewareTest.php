<?php

namespace MugiWara\FeatureFlags\Tests\Feature;

use MugiWara\FeatureFlags\Facades\Feature;
use MugiWara\FeatureFlags\Middleware\RequireFeature;
use MugiWara\FeatureFlags\Tests\TestCase;

class RequireFeatureMiddlewareTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('features.flags', ['test_feature' => false]);
    }

    private function registerRoute(string $responseType = ''): void
    {
        $middleware = $responseType
            ? "feature:test_feature,{$responseType}"
            : 'feature:test_feature';

        $this->app['router']->get('/test-route', fn() => 'OK')->middleware($middleware);
    }

    // -----------------------------------------------------------------------
    // Feature enabled — always passes through
    // -----------------------------------------------------------------------

    public function test_passes_through_when_feature_is_enabled(): void
    {
        Feature::enable('test_feature');
        $this->registerRoute();

        $this->get('/test-route')->assertOk()->assertSee('OK');
    }

    // -----------------------------------------------------------------------
    // Feature disabled — default (404)
    // -----------------------------------------------------------------------

    public function test_returns_404_by_default_when_disabled(): void
    {
        $this->registerRoute();

        $this->get('/test-route')->assertNotFound();
    }

    // -----------------------------------------------------------------------
    // Feature disabled — json response
    // -----------------------------------------------------------------------

    public function test_returns_json_404_when_response_type_is_json(): void
    {
        $this->registerRoute('json');

        $this->getJson('/test-route')
            ->assertNotFound()
            ->assertJson(['error' => 'Feature not available']);
    }

    // -----------------------------------------------------------------------
    // Feature disabled — forbidden (403)
    // -----------------------------------------------------------------------

    public function test_returns_403_when_response_type_is_forbidden(): void
    {
        $this->registerRoute('forbidden');

        $this->get('/test-route')->assertForbidden();
    }

    // -----------------------------------------------------------------------
    // Feature disabled — explicit 'abort' (same as default)
    // -----------------------------------------------------------------------

    public function test_returns_404_when_response_type_is_abort(): void
    {
        $this->registerRoute('abort');

        $this->get('/test-route')->assertNotFound();
    }

    public function test_unrecognised_response_type_falls_back_to_404(): void
    {
        $this->registerRoute('unknown_type');

        $this->get('/test-route')->assertNotFound();
    }

    // -----------------------------------------------------------------------
    // Feature disabled — redirect
    // -----------------------------------------------------------------------

    public function test_redirects_when_response_type_is_redirect(): void
    {
        // The middleware redirects to route('home'); register a home route first.
        $this->app['router']->get('/home', fn() => 'home')->name('home');
        $this->registerRoute('redirect');

        $this->get('/test-route')
            ->assertRedirect('/home');
    }
}
