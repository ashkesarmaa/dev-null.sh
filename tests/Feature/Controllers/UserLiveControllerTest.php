<?php

declare(strict_types=1);

use App\Enums\LivePlatform;
use App\Enums\NotificationType;
use App\Models\Commit;
use App\Models\User;

it('goes live with a YouTube stream for a few hours', function (string $url): void {
    $user = User::factory()->create();

    $this->actingAs($user)->fromRoute('user.show', $user)
        ->put(route('user-live.update'), ['url' => " {$url} ", 'hours' => 3])
        ->assertRedirectToRoute('user.show', $user);

    expect($user->refresh())
        ->live_platform->toBe(LivePlatform::YouTube)
        ->live_url->toBe('https://www.youtube.com/watch?v=dQw4w9WgXcQ')
        ->and($user->live_ends_at?->toIso8601String())->toBe(now()->addHours(3)->toIso8601String());

    $this->get(route('user.show', $user))
        ->assertInertia(fn ($page) => $page
            ->where('user.live', [
                'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'thumbnail_url' => 'https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault_live.jpg',
            ]));
})->with([
    'live link' => 'https://www.youtube.com/live/dQw4w9WgXcQ?si=share',
    'watch link' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=42s',
    'short link' => 'https://youtu.be/dQw4w9WgXcQ',
]);

it('tells their followers only when asked to', function (array $payload, bool $notifies): void {
    $user = User::factory()->create();
    $follower = User::factory()->create();
    $user->followers()->attach($follower);

    $this->actingAs($user)
        ->put(route('user-live.update'), ['url' => 'https://youtu.be/dQw4w9WgXcQ', 'hours' => 2, ...$payload])
        ->assertRedirect();

    expect($follower->notifications()->where('type', NotificationType::WentLive)->exists())->toBe($notifies);
})->with([
    'asked' => [['notify' => true], true],
    'declined' => [['notify' => false], false],
    'not asked' => [[], false],
]);

it('rejects a notify that is not a yes or no', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->putJson(route('user-live.update'), ['url' => 'https://youtu.be/dQw4w9WgXcQ', 'hours' => 2, 'notify' => 'loudly'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('notify');

    expect($user->refresh()->live_url)->toBeNull();
});

it('shows the live marker on their commits in the log', function (): void {
    $user = User::factory()->live()->create();
    Commit::factory()->for($user)->create();

    $this->actingAs(User::factory()->create())
        ->get(route('user.show', $user))
        ->assertInertia(fn ($page) => $page
            ->where('commits.data.0.user.live.url', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ')
            ->missing('commits.data.0.user.live_url')
            ->missing('commits.data.0.user.live_ends_at'));
});

it('rejects links that are not a YouTube video', function (array $payload): void {
    $user = User::factory()->create();

    $this->actingAs($user)->putJson(route('user-live.update'), [...$payload, 'hours' => 2])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('url');

    expect($user->refresh()->live_url)->toBeNull();
})->with([
    'missing' => fn (): array => [],
    'not a link' => fn (): array => ['url' => 'youtube'],
    'not a stream' => fn (): array => ['url' => 'https://example.com/live'],
    'twitch' => fn (): array => ['url' => 'https://www.twitch.tv/octocat'],
    'playlist' => fn (): array => ['url' => 'https://www.youtube.com/playlist?list=PL590L5WQmH8fJ54F369BLDSqIwcs-TCfs'],
    'too long' => fn (): array => ['url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&x='.str_repeat('a', 2048)],
]);

it('takes between one and twelve hours', function (mixed $hours): void {
    $user = User::factory()->create();

    $this->actingAs($user)->putJson(route('user-live.update'), ['url' => 'https://youtu.be/dQw4w9WgXcQ', 'hours' => $hours])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('hours');

    expect($user->refresh()->live_url)->toBeNull();
})->with([
    'missing' => null,
    'none' => 0,
    'too many' => User::MAX_LIVE_HOURS + 1,
    'not a number' => 'all day',
]);

it('ends the stream', function (): void {
    $user = User::factory()->live()->create();

    $this->actingAs($user)->fromRoute('user.show', $user)
        ->delete(route('user-live.destroy'))
        ->assertRedirectToRoute('user.show', $user);

    expect($user->refresh())
        ->live_platform->toBeNull()
        ->live_url->toBeNull()
        ->live_ends_at->toBeNull();

    $this->get(route('user.show', $user))
        ->assertInertia(fn ($page) => $page->where('user.live', null));
});

it('stops showing the stream once its hours are up', function (): void {
    $user = User::factory()->live()->create();

    $this->travel(2)->hours();

    $this->get(route('user.show', $user))
        ->assertInertia(fn ($page) => $page->where('user.live', null));
});

it('requires authentication', function (): void {
    $this->putJson(route('user-live.update'), ['url' => 'https://youtu.be/dQw4w9WgXcQ', 'hours' => 2])->assertUnauthorized();
    $this->deleteJson(route('user-live.destroy'))->assertUnauthorized();
});
