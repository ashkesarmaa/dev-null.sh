<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class DeleteUserLive
{
    /**
     * Telling followers about a stream that ended only sends them to nothing, so its notifications go with it.
     */
    public function handle(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user->update(['live_platform' => null, 'live_url' => null, 'live_ends_at' => null]);

            Notification::query()->where('type', NotificationType::WentLive)->where('actor_id', $user->id)->delete();
        });
    }
}
