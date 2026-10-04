<?php

namespace Tests\Feature;

// 🔽 2行追加
use App\Models\Tweet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class TweetLikeTest extends TestCase
{
  // 🔽 追加
  use RefreshDatabase;

  // 🔽 test_example を削除して以下を追加
  // likeのテスト
  public function test_allows_a_user_to_like_a_tweet(): void
  {
    $user = User::factory()->create();
    $tweet = Tweet::factory()->create();

    $this->actingAs($user)
      ->post(route('tweets.like', ['tweet' => $tweet->id]))
      ->assertStatus(302);

    $this->assertDatabaseHas('tweet_user', [
      'user_id' => $user->id,
      'tweet_id' => $tweet->id
    ]);
  }

  // 自分の Tweet にはいいねできないことのテスト
  public function test_does_not_allow_a_user_to_like_their_own_tweet(): void
  {
    $user = User::factory()->create();
    $tweet = Tweet::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
      ->post(route('tweets.like', ['tweet' => $tweet->id]))
      ->assertStatus(403);

    $this->assertDatabaseMissing('tweet_user', [
      'user_id' => $user->id,
      'tweet_id' => $tweet->id
    ]);
  }

  // 自分の Tweet には like ボタンが表示されないことのテスト
  public function test_hides_the_like_button_on_own_tweets(): void
  {
    $user = User::factory()->create();
    $ownTweet = Tweet::factory()->create(['user_id' => $user->id]);
    $otherTweet = Tweet::factory()->create();

    $response = $this->actingAs($user)->get('/tweets');

    $response->assertDontSee(route('tweets.like', $ownTweet));
    $response->assertSee(route('tweets.like', $otherTweet));
  }

  // dislikeのテスト
  public function test_allows_a_user_to_dislike_a_tweet(): void
  {
    $user = User::factory()->create();
    $tweet = Tweet::factory()->create();

    // 最初にlikeをする
    $user->likes()->attach($tweet);

    $this->actingAs($user)
      ->delete(route('tweets.dislike', ['tweet' => $tweet->id]))
      ->assertStatus(302);

    $this->assertDatabaseMissing('tweet_user', [
      'user_id' => $user->id,
      'tweet_id' => $tweet->id
    ]);
  }
}