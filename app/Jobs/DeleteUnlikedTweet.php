<?php

namespace App\Jobs;

use App\Models\Tweet;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DeleteUnlikedTweet implements ShouldQueue
{
    use Queueable;

    // ジョブ実行前に Tweet が手動で削除されていたら，エラーにせずジョブを捨てる
    public $deleteWhenMissingModels = true;

    /**
     * Create a new job instance.
     */
    public function __construct(public Tweet $tweet)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // いいねが1件もなければ削除（いいね・コメントは cascadeOnDelete で一緒に消える）
        if ($this->tweet->liked()->doesntExist()) {
            $this->tweet->delete();
            return;
        }

        // いいねがあれば残し，チェック済みにする（一覧のカウントダウン表示も消える）
        $this->tweet->like_checked_at = now();
        $this->tweet->save();
    }
}
