<?php

namespace Tests\Feature;

// 🔽 2行追加
use App\Jobs\DeleteUnlikedTweet;
use App\Models\Tweet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TweetTest extends TestCase
{
  // 🔽 追加（テストごとにデータベースを元に戻す）
  use RefreshDatabase;

  // 🔽 test_example を削除して一覧取得のテストを追加
  public function test_displays_tweets(): void
  {
    // ユーザを作成
    $user = User::factory()->create();

    // ユーザを認証
    $this->actingAs($user);

    // Tweetを作成
    $tweet = Tweet::factory()->create();

    // GETリクエスト
    $response = $this->get('/tweets');

    // レスポンスにTweetの内容と投稿者名が含まれていることを確認
    $response->assertStatus(200);
    $response->assertSee($tweet->tweet);
    $response->assertSee($tweet->user->name);
  }
  // 作成画面のテスト
  public function test_displays_the_create_tweet_page(): void
  {
    // テスト用のユーザーを作成
    $user = User::factory()->create();

    // ユーザーを認証（ログイン）
    $this->actingAs($user);

    // 作成画面にアクセス
    $response = $this->get('/tweets/create');

    // ステータスコードが200であることを確認
    $response->assertStatus(200);
  }
  // 作成処理のテスト
  public function test_allows_authenticated_users_to_create_a_tweet(): void
  {
    // 自動削除ジョブがその場で実行されないようにする（テスト中のキューは即時実行のため）
    Queue::fake();

    // ユーザを作成
    $user = User::factory()->create();

    // ユーザを認証
    $this->actingAs($user);

    // Tweetを作成
    $tweetData = ['tweet' => 'This is a test tweet.'];

    // POSTリクエスト
    $response = $this->post('/tweets', $tweetData);

    // データベースに保存されたことを確認
    $this->assertDatabaseHas('tweets', $tweetData);

    // レスポンスの確認
    $response->assertStatus(302);
    $response->assertRedirect('/tweets');
  }
  // 作成時に自動削除ジョブが予約されることのテスト
  public function test_schedules_a_delete_job_when_a_tweet_is_created(): void
  {
    Queue::fake();

    $user = User::factory()->create();

    $this->actingAs($user)->post('/tweets', ['tweet' => 'This is a test tweet.']);

    $tweet = Tweet::first();

    // 投稿時刻 + Tweet::LIKE_LIMIT_MINUTES 分後に実行されるよう予約されていることを確認
    Queue::assertPushed(DeleteUnlikedTweet::class, function (DeleteUnlikedTweet $job) use ($tweet) {
      return $job->tweet->is($tweet)
        && $job->delay->equalTo($tweet->created_at->copy()->addMinutes(Tweet::LIKE_LIMIT_MINUTES));
    });
  }
  // 一覧画面のカウントダウン表示のテスト
  public function test_shows_countdown_only_for_tweets_awaiting_a_like(): void
  {
    $user = User::factory()->create();

    // いいね待ちの Tweet だけの場合は，カウントダウンが表示される
    $tweet = Tweet::factory()->create();
    $this->actingAs($user)->get('/tweets')->assertSee('削除まで');

    // いいねが付くと，カウントダウンは表示されない
    $tweet->liked()->attach($user);
    $this->actingAs($user)->get('/tweets')->assertDontSee('削除まで');
  }
  // チェック済みの Tweet にはカウントダウンが表示されないことのテスト
  public function test_does_not_show_countdown_for_checked_tweets(): void
  {
    $user = User::factory()->create();

    $tweet = Tweet::factory()->create();
    $tweet->like_checked_at = now();
    $tweet->save();

    $this->actingAs($user)->get('/tweets')->assertDontSee('削除まで');
  }
  // 詳細画面のテスト
  public function test_displays_a_tweet(): void
  {
    // ユーザを作成
    $user = User::factory()->create();

    // ユーザを認証
    $this->actingAs($user);

    // Tweetを作成
    $tweet = Tweet::factory()->create();

    // GETリクエスト
    $response = $this->get("/tweets/{$tweet->id}");

    // レスポンスにTweetの内容・日時・投稿者名が含まれていることを確認
    $response->assertStatus(200);
    $response->assertSee($tweet->tweet);
    $response->assertSee($tweet->created_at->format('Y-m-d H:i'));
    $response->assertSee($tweet->updated_at->format('Y-m-d H:i'));
    $response->assertSee($tweet->user->name);
  }
  // 編集画面のテスト
  public function test_displays_the_edit_tweet_page(): void
  {
    // テスト用のユーザーを作成
    $user = User::factory()->create();

    // ユーザーを認証（ログイン）
    $this->actingAs($user);

    // Tweetを作成
    $tweet = Tweet::factory()->create(['user_id' => $user->id]);

    // 編集画面にアクセス
    $response = $this->get("/tweets/{$tweet->id}/edit");

    // ステータスコードが200であることを確認
    $response->assertStatus(200);

    // ビューにTweetの内容が含まれていることを確認
    $response->assertSee($tweet->tweet);
  }
  // 更新処理のテスト
  public function test_allows_a_user_to_update_their_tweet(): void
  {
    // ユーザを作成
    $user = User::factory()->create();

    // ユーザを認証
    $this->actingAs($user);

    // Tweetを作成
    $tweet = Tweet::factory()->create(['user_id' => $user->id]);

    // 更新データ
    $updatedData = ['tweet' => 'Updated tweet content.'];

    // PUTリクエスト
    $response = $this->put("/tweets/{$tweet->id}", $updatedData);

    // データベースが更新されたことを確認
    $this->assertDatabaseHas('tweets', $updatedData);

    // レスポンスの確認
    $response->assertStatus(302);
    $response->assertRedirect("/tweets/{$tweet->id}");
  }
  // 削除処理のテスト
  public function test_allows_a_user_to_delete_their_tweet(): void
  {
    // ユーザを作成
    $user = User::factory()->create();

    // ユーザを認証
    $this->actingAs($user);

    // Tweetを作成
    $tweet = Tweet::factory()->create(['user_id' => $user->id]);

    // DELETEリクエスト
    $response = $this->delete("/tweets/{$tweet->id}");

    // データベースから削除されたことを確認
    $this->assertDatabaseMissing('tweets', ['id' => $tweet->id]);

    // レスポンスの確認
    $response->assertStatus(302);
    $response->assertRedirect('/tweets');
  }
  // キーワードを含むツイートを検索できることを確認
  public function test_can_search_tweets_by_content_keyword(): void
  {
    $user = User::factory()->create();
    $this->actingAs($user);

    // キーワードを含むツイートを作成
    Tweet::factory()->create([
      'tweet' => 'This is a test tweet',
      'user_id' => $user->id,
    ]);

    // キーワードを含まないツイートを作成
    Tweet::factory()->create([
      'tweet' => 'This is another tweet',
      'user_id' => $user->id,
    ]);

    // キーワード "test" で検索
    $response = $this->get(route('tweets.search', ['keyword' => 'test']));

    $response->assertStatus(200);
    $response->assertSee('This is a test tweet');
    $response->assertDontSee('This is another tweet');
  }

  // 一致するツイートがない場合のテスト
  public function test_shows_no_tweets_if_no_match_found(): void
  {
    $user = User::factory()->create();
    $this->actingAs($user);

    Tweet::factory()->create([
      'tweet' => 'This is a tweet',
      'user_id' => $user->id,
    ]);

    // 存在しないキーワードで検索
    $response = $this->get(route('tweets.search', ['keyword' => 'nonexistent']));

    $response->assertStatus(200);
    $response->assertDontSee('This is a tweet');
    $response->assertSee('No tweets found.');
  }
}