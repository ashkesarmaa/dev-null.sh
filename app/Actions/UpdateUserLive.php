<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\LivePlatform;
use App\Models\User;

final readonly class UpdateUserLive
{
    public function __construct(private CreateLiveNotifications $createLiveNotifications)
    {
        //
    }

    public function handle(User $user, LivePlatform $platform, string $url, int $hours, bool $notify): void
    {
        $user->update([
            'live_platform' => $platform,
            'live_url' => $platform->driver()->stream($url)->url,
            'live_ends_at' => now()->addHours($hours),
        ]);

        if ($notify) {
            $this->createLiveNotifications->handle($user);
        }
    }
}
