<x-layouts.app :title="__('Tweet一覧')">
  <div class="p-6">
    <h2 class="font-semibold text-xl mb-4">{{ __('Tweet一覧') }}</h2>
    @foreach ($tweets as $tweet)
      <div class="mb-4 p-4 bg-gray-100 dark:bg-gray-700 rounded-lg"
        @if ($tweet->isAwaitingLike())
          {{-- いいねが無いまま削除期限が近づくほど，背景を赤くする --}}
          x-data="{
            remaining: {{ $tweet->secondsUntilLikeDeadline() }},
            // 残りこの秒数を切ったら赤くなり始める
            redStart: 60,
            timer: null,
            init() {
              // PC の時計のずれに影響されないよう，サーバーで計算した残り秒数から数える
              const deadline = Date.now() + this.remaining * 1000;
              this.timer = setInterval(() => {
                this.remaining = Math.max(0, Math.ceil((deadline - Date.now()) / 1000));
              }, 1000);
            },
            destroy() {
              clearInterval(this.timer);
            },
            get redness() {
              // 残り redStart 秒以上は 0（赤くしない），そこから 0 秒に向けて 0 → 1
              return Math.max(0, 1 - this.remaining / this.redStart);
            },
            get label() {
              if (this.remaining === 0) return 'まもなく削除されます';
              const m = Math.floor(this.remaining / 60);
              const s = String(this.remaining % 60).padStart(2, '0');
              return `削除まで ${m}:${s}`;
            },
          }"
          :style="`
            background-image: linear-gradient(rgba(220, 38, 38, ${redness * 0.8}), rgba(220, 38, 38, ${redness * 0.8}));
            opacity: ${remaining === 0 ? 0.5 : 1};
          `"
        @endif
      >
        <p>{{ $tweet->tweet }}</p>
        @if ($tweet->isAwaitingLike())
          <p class="text-sm font-semibold" x-text="label"></p>
        @endif
        {{-- 🔽 投稿者名部分にリンクを追加 --}}
        <a href="{{ route('profile.show', $tweet->user) }}">
          <p class="text-sm text-gray-500">投稿者: {{ $tweet->user->name }}</p>
        </a>
        <a href="{{ route('tweets.show', $tweet) }}" class="text-blue-500 hover:text-blue-700">詳細を見る</a>

        <div class="flex mt-2">
          @if ($tweet->liked->contains(auth()->id()))
            <form action="{{ route('tweets.dislike', $tweet) }}" method="POST">
              @csrf
              @method('DELETE')
              <button type="submit" class="text-red-500 hover:text-red-700">dislike {{ $tweet->liked->count() }}</button>
            </form>
          @elseif ($tweet->user_id !== auth()->id())
            <form action="{{ route('tweets.like', $tweet) }}" method="POST">
              @csrf
              <button type="submit" class="text-blue-500 hover:text-blue-700">like {{ $tweet->liked->count() }}</button>
            </form>
          @else
            {{-- 自分の Tweet にはいいねできないので，数だけ表示 --}}
            <span class="text-gray-500">like {{ $tweet->liked->count() }}</span>
          @endif
        </div>
      </div>
    @endforeach
    </div>
</x-layouts.app>