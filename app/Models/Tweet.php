<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tweet extends Model
{
  use HasFactory;

  // 投稿からこの分数以内にいいねが無いと自動削除される
  public const LIKE_LIMIT_MINUTES = 3;

  protected $fillable = ['tweet'];
  // （Tweet から見て User は 多対1）
  public function user()
  {
    return $this->belongsTo(User::class);
  }

  public function liked()
  {
    return $this->belongsToMany(User::class)->withTimestamps();
  }
  public function comments()
  {
    return $this->hasMany(Comment::class)->orderBy('created_at', 'desc');
  }
  // 自動削除の対象になりうる（まだチェック前で，いいねが1件もない）かどうか
  public function isAwaitingLike(): bool
  {
    return is_null($this->like_checked_at) && $this->liked->isEmpty();
  }

  // 自動削除の期限までの残り秒数（期限を過ぎていれば 0）
  public function secondsUntilLikeDeadline(): int
  {
    $deadline = $this->created_at->copy()->addMinutes(self::LIKE_LIMIT_MINUTES);

    // 端数は切り上げ（画面側のカウントダウンと同じ数え方）
    return max(0, (int) ceil(now()->diffInSeconds($deadline, false)));
  }

  public function scopeKeyword(Builder $query, ?string $keyword): Builder
  {
    // キーワードが指定されている場合のみ絞り込む
   return $query->when($keyword, function (Builder $query, string $keyword) {
      // 部分一致（キーワードがどこかに含まれる）
      $query->where('tweet', 'like', '%' . $keyword . '%');

      // 前方一致（キーワードで始まる）
      // $query->where('tweet', 'like', $keyword . '%');

      // 後方一致（キーワードで終わる）
      // $query->where('tweet', 'like', '%' . $keyword);

      // 完全一致（キーワードと同じ文字列）
      // $query->where('tweet', $keyword);
    });
  }
    public function scopeTimeline(Builder $query, User $user): Builder
  {
    return $query
      ->where('user_id', $user->id) // 自分の Tweet
      ->orWhereIn('user_id', $user->follows->pluck('id')); // フォローしているユーザの Tweet
  }
}
