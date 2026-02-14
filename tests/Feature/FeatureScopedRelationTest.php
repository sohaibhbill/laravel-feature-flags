<?php

namespace MugiWara\FeatureFlags\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MugiWara\FeatureFlags\Concerns\FeatureScopedRelation;
use MugiWara\FeatureFlags\Facades\Feature;
use MugiWara\FeatureFlags\Tests\TestCase;

// ---------------------------------------------------------------------------
// Minimal models backed by in-memory SQLite tables
// ---------------------------------------------------------------------------

class Post extends Model
{
    protected $table = 'posts';
    protected $guarded = [];
    public $timestamps = false;
}

class Comment extends Model
{
    use FeatureScopedRelation;

    protected $table = 'comments';
    protected $guarded = [];
    public $timestamps = false;

    /** Generic featureRelation() helper */
    public function post(): Relation
    {
        return $this->featureRelation('comments_feature', fn() => $this->belongsTo(Post::class));
    }

    /** Typed helper — hasManyWithFeature */
    public function tags(): HasMany
    {
        return $this->hasManyWithFeature('tags_feature', Tag::class, 'comment_id');
    }
}

class Tag extends Model
{
    protected $table = 'tags';
    protected $guarded = [];
    public $timestamps = false;
}

// ---------------------------------------------------------------------------

class FeatureScopedRelationTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('features.flags', [
            'comments_feature' => true,
            'tags_feature'     => true,
        ]);
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->app['db']->statement('CREATE TABLE posts    (id INTEGER PRIMARY KEY, title TEXT)');
        $this->app['db']->statement('CREATE TABLE comments (id INTEGER PRIMARY KEY, post_id INTEGER, body TEXT)');
        $this->app['db']->statement('CREATE TABLE tags     (id INTEGER PRIMARY KEY, comment_id INTEGER, name TEXT)');
    }

    // -----------------------------------------------------------------------
    // featureRelation() — the previously-broken generic helper
    // -----------------------------------------------------------------------

    public function test_feature_relation_returns_relation_instance_when_enabled(): void
    {
        Feature::enable('comments_feature');
        $comment = Comment::forceCreate(['id' => 1, 'post_id' => 1, 'body' => 'Hello']);

        $this->assertInstanceOf(Relation::class, $comment->post());
    }

    public function test_feature_relation_returns_relation_instance_when_disabled(): void
    {
        // Previously returned null — now returns a constrained Relation
        Feature::disable('comments_feature');
        $comment = Comment::forceCreate(['id' => 1, 'post_id' => 1, 'body' => 'Hello']);

        $relation = $comment->post();

        $this->assertInstanceOf(Relation::class, $relation);
    }

    public function test_feature_relation_returns_empty_result_when_disabled(): void
    {
        Post::forceCreate(['id' => 1, 'title' => 'My Post']);
        $comment = Comment::forceCreate(['id' => 1, 'post_id' => 1, 'body' => 'Hello']);

        Feature::disable('comments_feature');

        // Safe to call ->get() / ->first() without null check on the caller side
        $this->assertNull($comment->post()->first());
    }

    public function test_feature_relation_returns_real_result_when_enabled(): void
    {
        Post::forceCreate(['id' => 1, 'title' => 'My Post']);
        $comment = Comment::forceCreate(['id' => 1, 'post_id' => 1, 'body' => 'Hello']);

        Feature::enable('comments_feature');

        $this->assertNotNull($comment->post()->first());
        $this->assertSame('My Post', $comment->post()->first()->title);
    }

    // -----------------------------------------------------------------------
    // hasManyWithFeature (typed helper — already correct, regression guard)
    // -----------------------------------------------------------------------

    public function test_has_many_with_feature_returns_empty_when_disabled(): void
    {
        $comment = Comment::forceCreate(['id' => 1, 'post_id' => 1, 'body' => 'Hello']);
        Tag::forceCreate(['id' => 1, 'comment_id' => 1, 'name' => 'php']);

        Feature::disable('tags_feature');

        $this->assertCount(0, $comment->tags()->get());
    }

    public function test_has_many_with_feature_returns_results_when_enabled(): void
    {
        $comment = Comment::forceCreate(['id' => 1, 'post_id' => 1, 'body' => 'Hello']);
        Tag::forceCreate(['id' => 1, 'comment_id' => 1, 'name' => 'php']);

        Feature::enable('tags_feature');

        $this->assertCount(1, $comment->tags()->get());
    }
}
