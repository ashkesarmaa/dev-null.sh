<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\User;
use App\Queries\SilencedActorQuery;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class CreateLiveNotifications
{
    /**
     * Tells every follower the user went live, once per stream: ending the stream removes the notifications. Nobody
     * hears from people they mute, and a block already ended any follow across it.
     */
    public function handle(User $user): void
    {
        DB::table('follows')
            ->where('followed_id', $user->id)
            ->whereNotExists(new SilencedActorQuery('follows.follower_id', 'follows.followed_id')->mutes(...))
            ->select('follower_id')
            ->chunkById(1000, fn (Collection $follows): int => Notification::query()->insertOrIgnore($follows->map(fn (object $follow): array => [
                'id' => Str::uuid7()->toString(),
                'recipient_id' => $follow->follower_id,
                'actor_id' => $user->id,
                'type' => NotificationType::WentLive->value,
                'commit_id' => null,
                'key' => 'went_live:'.$user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all()), 'follower_id');
    }
}
