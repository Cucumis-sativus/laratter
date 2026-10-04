<?php

namespace Tests\Feature;

use App\Jobs\DeleteUnlikedTweet;
use App\Models\Comment;
use App\Models\Tweet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeleteUnlikedTweetTest extends TestCase
{
  use RefreshDatabase;

  // いいねが無い Tweet は削除されることのテスト
  public function test_deletes_a_tweet_without_likes(): void
  {
    $tweet = Tweet::factory()->create();

    (new DeleteUnlikedTweet($tweet))->handle();

    $this->assertDatabaseMissing('tweets', ['id' => $tweet->id]);
  }

  // いいねがある Tweet は残り，チェック済みになることのテスト
  public function test_keeps_a_liked_tweet_and_marks_it_as_checked(): void
  {
    $this->freezeTime();

    $tweet = Tweet::factory()->create();
    $tweet->liked()->attach(User::factory()->create());

    (new DeleteUnlikedTweet($tweet))->handle();

    $this->assertDatabaseHas('tweets', [
      'id' => $tweet->id,
      'like_checked_at' => now(),
    ]);
  }

  // Tweet と一緒にコメントも削除されることのテスト
  public function test_deletes_comments_together_with_the_tweet(): void
  {
    $tweet = Tweet::factory()->create();
    $comment = Comment::factory()->create(['tweet_id' => $tweet->id]);

    (new DeleteUnlikedTweet($tweet))->handle();

    $this->assertDatabaseMissing('comments', ['id' => $comment->id]);
  }

  // ジョブ実行前に Tweet が削除されていてもエラーにならないことのテスト
  public function test_does_not_fail_when_the_tweet_is_already_deleted(): void
  {
    $tweet = Tweet::factory()->create();
    $tweet->delete();

    // テスト中のキューは即時実行なので，dispatch した時点でジョブが動く
    DeleteUnlikedTweet::dispatch($tweet);

    $this->assertDatabaseCount('tweets', 0);
  }

  // 削除期限までの残り秒数のテスト
  public function test_calculates_seconds_until_the_like_deadline(): void
  {
    $this->freezeTime();

    $tweet = Tweet::factory()->create();
    $limit = Tweet::LIKE_LIMIT_MINUTES * 60;

    $this->assertSame($limit, $tweet->secondsUntilLikeDeadline());

    // 1分経過
    $this->travel(1)->minutes();
    $this->assertSame($limit - 60, $tweet->secondsUntilLikeDeadline());

    // 期限を過ぎたら 0 のまま（マイナスにならない）
    $this->travel(Tweet::LIKE_LIMIT_MINUTES)->minutes();
    $this->assertSame(0, $tweet->secondsUntilLikeDeadline());
  }

  // いいね待ちかどうかの判定のテスト
  public function test_determines_whether_a_tweet_is_awaiting_a_like(): void
  {
    // いいね無し・未チェック → いいね待ち
    $tweet = Tweet::factory()->create();
    $this->assertTrue($tweet->fresh()->isAwaitingLike());

    // いいねが付いた → いいね待ちではない
    $tweet->liked()->attach(User::factory()->create());
    $this->assertFalse($tweet->fresh()->isAwaitingLike());

    // チェック済み → いいね待ちではない
    $checked = Tweet::factory()->create();
    $checked->like_checked_at = now();
    $checked->save();
    $this->assertFalse($checked->fresh()->isAwaitingLike());
  }
}
