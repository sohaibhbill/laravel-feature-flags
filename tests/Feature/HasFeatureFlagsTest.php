<?php

namespace MugiWara\FeatureFlags\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MugiWara\FeatureFlags\Concerns\HasFeatureFlags;
use MugiWara\FeatureFlags\Facades\Feature;
use MugiWara\FeatureFlags\Tests\TestCase;

// Minimal Eloquent model that uses the trait, pointing at a simple table
class Article extends Model
{
    use HasFeatureFlags;

    protected $table = 'articles';
    protected $guarded = [];
    public $timestamps = false;

    public function getFeatureFlag(): string
    {
        return 'articles_feature';
    }
}

class HasFeatureFlagsTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
        $app['config']->set('features.flags', ['articles_feature' => true]);
    }

    protected function defineDatabaseMigrations(): void
    {
        // Create a minimal articles table for testing
        $this->app['db']->statement('CREATE TABLE articles (id INTEGER PRIMARY KEY, title TEXT)');
    }

    private function seedArticles(): void
    {
        Article::withoutGlobalScopes()->insert([
            ['title' => 'Article One'],
            ['title' => 'Article Two'],
        ]);
    }

    // -----------------------------------------------------------------------
    // Global scope behaviour
    // -----------------------------------------------------------------------

    public function test_queries_return_results_when_feature_enabled(): void
    {
        Feature::enable('articles_feature');
        $this->seedArticles();

        $this->assertCount(2, Article::all());
    }

    public function test_queries_return_empty_collection_when_feature_disabled(): void
    {
        Feature::disable('articles_feature');
        $this->seedArticles();

        $this->assertCount(0, Article::all());
    }

    public function test_find_returns_null_when_feature_disabled(): void
    {
        Feature::disable('articles_feature');
        $this->seedArticles();

        $this->assertNull(Article::first());
    }

    public function test_without_global_scopes_bypasses_feature_flag(): void
    {
        Feature::disable('articles_feature');
        $this->seedArticles();

        // Developers can still access records when they explicitly bypass scopes
        $this->assertCount(2, Article::withoutGlobalScopes()->get());
    }

    // -----------------------------------------------------------------------
    // Instance helpers
    // -----------------------------------------------------------------------

    public function test_feature_is_enabled_returns_true_when_on(): void
    {
        Feature::enable('articles_feature');
        $article = new Article();

        $this->assertTrue($article->featureIsEnabled());
    }

    public function test_feature_is_disabled_returns_true_when_off(): void
    {
        Feature::disable('articles_feature');
        $article = new Article();

        $this->assertTrue($article->featureIsDisabled());
    }

    public function test_when_feature_enabled_executes_callback(): void
    {
        Feature::enable('articles_feature');
        $called = false;
        $article = new Article();

        $article->whenFeatureEnabled(function () use (&$called) {
            $called = true;
        });

        $this->assertTrue($called);
    }

    public function test_when_feature_enabled_skips_callback_when_disabled(): void
    {
        Feature::disable('articles_feature');
        $called = false;
        $article = new Article();

        $article->whenFeatureEnabled(function () use (&$called) {
            $called = true;
        });

        $this->assertFalse($called);
    }

    public function test_when_feature_enabled_executes_default_when_disabled(): void
    {
        Feature::disable('articles_feature');
        $article = new Article();

        $result = $article->whenFeatureEnabled(
            fn() => 'premium',
            fn() => 'fallback'
        );

        $this->assertSame('fallback', $result);
    }
}
