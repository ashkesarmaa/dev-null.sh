<?php

declare(strict_types=1);

use App\Actions\CreateLiveNotifications;
use App\Enums\NotificationType;
use App\Models\User;

it('notifies every follower', function (): void {
    $user = User::factory()->create();
    $followers = User::factory()->count(2)->create();
    $stranger = User::factory()->create();

    $user->followers()->attach($followers);

    resolve(CreateLiveNotifications::class)->handle($user);

    foreach ($followers as $follower) {
        $notification = $follower->notifications()->sole();

        expect($notification->type)->toBe(NotificationType::WentLive)
            ->and($notification->actor_id)->toBe($user->id)
            ->and($notification->commit_id)->toBeNull();
    }

    expect($stranger->notifications()->exists())->toBeFalse()
        ->and($user->notifications()->exists())->toBeFalse();
});

it('is idempotent', function (): void {
    $user = User::factory()->create();
    $follower = User::factory()->create();

    $user->followers()->attach($follower);

    $action = resolve(CreateLiveNotifications::class);

    $action->handle($user);
    $action->handle($user);

    expect($follower->notifications()->count())->toBeOne();
});

it('does not notify a follower who muted the user', function (): void {
    $user = User::factory()->create();
    $follower = User::factory()->create();

    $user->followers()->attach($follower);
    $follower->muting()->attach($user);

    resolve(CreateLiveNotifications::class)->handle($user);

    expect($follower->notifications()->exists())->toBeFalse();
});
