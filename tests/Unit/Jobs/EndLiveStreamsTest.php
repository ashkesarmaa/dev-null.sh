<?php

declare(strict_types=1);

use App\Jobs\EndLiveStreams;
use App\Models\Notification;
use App\Models\User;

it('clears the streams that ran out, with their notifications', function (): void {
    $ended = User::factory()->live()->create(['live_ends_at' => now()->subMinute()]);
    $running = User::factory()->live()->create();
    $endedNotification = Notification::factory()->wentLive()->create(['actor_id' => $ended->id]);
    $runningNotification = Notification::factory()->wentLive()->create(['actor_id' => $running->id]);

    dispatch_sync(new EndLiveStreams());

    expect($ended->refresh())
        ->live_platform->toBeNull()
        ->live_url->toBeNull()
        ->live_ends_at->toBeNull()
        ->and($running->refresh()->liveStream()?->url)->toBe('https://www.youtube.com/watch?v=dQw4w9WgXcQ')
        ->and($endedNotification->fresh())->toBeNull()
        ->and($runningNotification->fresh())->not->toBeNull();
});
