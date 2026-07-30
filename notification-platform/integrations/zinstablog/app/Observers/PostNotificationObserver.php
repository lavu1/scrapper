<?php

namespace App\Observers;

use App\Jobs\PublishBrowserNotification;
use App\Models\Post;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class PostNotificationObserver
{
    public function created(Post $post): void
    {
        $this->queueIfNewlyPublic($post, true);
    }

    public function updated(Post $post): void
    {
        $wasPublic = $post->getOriginal('status') === Post::STATUS_PUBLISHED
            && $post->getOriginal('published_at')
            && Carbon::parse($post->getOriginal('published_at'))->lte(now());
        $this->queueIfNewlyPublic($post, ! $wasPublic);
    }

    private function queueIfNewlyPublic(Post $post, bool $newlyPublic): void
    {
        if (! $newlyPublic || $post->status !== Post::STATUS_PUBLISHED || ! $post->published_at || $post->published_at->isFuture()) {
            return;
        }

        try {
            PublishBrowserNotification::dispatch('zinstablog-post-'.$post->id.'-published', [
                'id' => (string) $post->id,
                'type' => 'news',
                'title' => $post->title,
                'description' => Str::limit(strip_tags((string) ($post->excerpt ?: $post->body)), 240),
                'url' => route('posts.show', $post),
                'imageUrl' => $post->featured_image_url,
                'category' => $post->category?->name,
            ])->afterCommit();
        } catch (\Throwable $exception) {
            Log::warning('Could not queue news browser notification; publishing will continue.', ['post_id' => $post->id, 'error' => $exception->getMessage()]);
        }
    }
}
