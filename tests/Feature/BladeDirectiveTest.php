<?php

namespace MugiWara\FeatureFlags\Tests\Feature;

use Illuminate\Support\Facades\Blade;
use MugiWara\FeatureFlags\Facades\Feature;
use MugiWara\FeatureFlags\Tests\TestCase;

class BladeDirectiveTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('features.flags', [
            'beta_dashboard' => false,
            'classic_ui'     => true,
        ]);
    }

    // -----------------------------------------------------------------------
    // @feature / @endfeature
    // -----------------------------------------------------------------------

    public function test_feature_renders_content_when_enabled(): void
    {
        Feature::enable('beta_dashboard');

        $output = Blade::render("@feature('beta_dashboard') BETA @endfeature");

        $this->assertStringContainsString('BETA', $output);
    }

    public function test_feature_hides_content_when_disabled(): void
    {
        Feature::disable('beta_dashboard');

        $output = Blade::render("@feature('beta_dashboard') BETA @endfeature");

        $this->assertStringNotContainsString('BETA', $output);
    }

    // -----------------------------------------------------------------------
    // @elsefeature
    // -----------------------------------------------------------------------

    public function test_elsefeature_renders_fallback_when_disabled(): void
    {
        Feature::disable('beta_dashboard');

        $output = Blade::render(
            "@feature('beta_dashboard') BETA @elsefeature CLASSIC @endfeature"
        );

        $this->assertStringNotContainsString('BETA', $output);
        $this->assertStringContainsString('CLASSIC', $output);
    }

    public function test_elsefeature_does_not_render_fallback_when_enabled(): void
    {
        Feature::enable('beta_dashboard');

        $output = Blade::render(
            "@feature('beta_dashboard') BETA @elsefeature CLASSIC @endfeature"
        );

        $this->assertStringContainsString('BETA', $output);
        $this->assertStringNotContainsString('CLASSIC', $output);
    }

    // -----------------------------------------------------------------------
    // @unlessfeature / @endunlessfeature
    // -----------------------------------------------------------------------

    public function test_unlessfeature_renders_content_when_disabled(): void
    {
        Feature::disable('beta_dashboard');

        $output = Blade::render(
            "@unlessfeature('beta_dashboard') LEGACY @endunlessfeature"
        );

        $this->assertStringContainsString('LEGACY', $output);
    }

    public function test_unlessfeature_hides_content_when_enabled(): void
    {
        Feature::enable('beta_dashboard');

        $output = Blade::render(
            "@unlessfeature('beta_dashboard') LEGACY @endunlessfeature"
        );

        $this->assertStringNotContainsString('LEGACY', $output);
    }

    // -----------------------------------------------------------------------
    // Nesting / multiple flags in one template
    // -----------------------------------------------------------------------

    public function test_multiple_directives_work_independently(): void
    {
        Feature::enable('classic_ui');
        Feature::disable('beta_dashboard');

        $output = Blade::render(
            "@feature('classic_ui') CLASSIC @endfeature " .
            "@feature('beta_dashboard') BETA @endfeature"
        );

        $this->assertStringContainsString('CLASSIC', $output);
        $this->assertStringNotContainsString('BETA', $output);
    }

    public function test_directive_works_with_html_content(): void
    {
        Feature::enable('beta_dashboard');

        $output = Blade::render(
            "@feature('beta_dashboard')<div class=\"beta\">Hello</div>@endfeature"
        );

        $this->assertStringContainsString('<div class="beta">Hello</div>', $output);
    }

    // -----------------------------------------------------------------------
    // @featureany / @endfeatureany
    // -----------------------------------------------------------------------

    public function test_featureany_renders_when_one_enabled(): void
    {
        Feature::enable('beta_dashboard');
        Feature::disable('classic_ui');

        $output = Blade::render(
            "@featureany(['beta_dashboard', 'classic_ui']) ANY @endfeatureany"
        );

        $this->assertStringContainsString('ANY', $output);
    }

    public function test_featureany_renders_when_all_enabled(): void
    {
        Feature::enable('beta_dashboard');
        Feature::enable('classic_ui');

        $output = Blade::render(
            "@featureany(['beta_dashboard', 'classic_ui']) ANY @endfeatureany"
        );

        $this->assertStringContainsString('ANY', $output);
    }

    public function test_featureany_hides_when_all_disabled(): void
    {
        Feature::disable('beta_dashboard');
        Feature::disable('classic_ui');

        $output = Blade::render(
            "@featureany(['beta_dashboard', 'classic_ui']) ANY @endfeatureany"
        );

        $this->assertStringNotContainsString('ANY', $output);
    }

    public function test_featureany_works_with_html_content(): void
    {
        Feature::enable('beta_dashboard');

        $output = Blade::render(
            "@featureany(['beta_dashboard', 'classic_ui'])<div class=\"any\">Content</div>@endfeatureany"
        );

        $this->assertStringContainsString('<div class="any">Content</div>', $output);
    }
}
