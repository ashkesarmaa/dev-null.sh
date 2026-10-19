<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;
use App\Queries\CommitStargazersQuery;

it('lists the people who starred a commit, the newest star first', function (): void {
    $commit = Commit::factory()->create();
    $first = User::factory()->create();
    $second = User::factory()->create();
    $commit->stars()->attach($first, ['created_at' => now()->subMinute(), 'updated_at' => now()->subMinute()]);
    $commit->stars()->attach($second);

    $this->getJson(route('commit-stargazer.index', $commit))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.username', $second->username)
        ->assertJsonPath('data.1.username', $first->username)
        ->assertJsonPath('data.0.is_followed', false)
        ->assertJsonPath('next_cursor', null);
});

it('tells the viewer whom they already follow', function (): void {
    $viewer = User::factory()->create();
    $commit = Commit::factory()->create();
    $followed = User::factory()->create();
    $stranger = User::factory()->create();
    $viewer->following()->attach($followed);
    $commit->stars()->attach($stranger, ['created_at' => now()->subMinute(), 'updated_at' => now()->subMinute()]);
    $commit->stars()->attach($followed);

    $this->actingAs($viewer)
        ->getJson(route('commit-stargazer.index', $commit))
        ->assertOk()
        ->assertJsonPath('data.0.id', $followed->id)
        ->assertJsonPath('data.0.is_followed', true)
        ->assertJsonPath('data.1.id', $stranger->id)
        ->assertJsonPath('data.1.is_followed', false);
});

it('lists the stargazers a page at a time', function (): void {
    $commit = Commit::factory()->create();
    $commit->stars()->attach(User::factory()->count(CommitStargazersQuery::PER_PAGE + 1)->create());

    $response = $this->getJson(route('commit-stargazer.index', $commit))
        ->assertOk()
        ->assertJsonCount(CommitStargazersQuery::PER_PAGE, 'data');

    $this->getJson(route('commit-stargazer.index', [$commit, 'cursor' => $response->json('next_cursor')]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('next_cursor', null);
});

it('leaves out stargazers across a block either way', function (): void {
    $viewer = User::factory()->create();
    $commit = Commit::factory()->create();
    $blocked = User::factory()->create();
    $blocker = User::factory()->create();
    $visible = User::factory()->create();
    $viewer->blocking()->attach($blocked);
    $blocker->blocking()->attach($viewer);
    $commit->stars()->attach([$blocked->id, $blocker->id, $visible->id]);

    $this->actingAs($viewer)
        ->getJson(route('commit-stargazer.index', $commit))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $visible->id);
});

it('lets authors see who starred their commit', function (): void {
    $commit = Commit::factory()->create();
    $stargazer = User::factory()->create();
    $commit->stars()->attach($stargazer);

    $this->actingAs($commit->user)
        ->getJson(route('commit-stargazer.index', $commit))
        ->assertOk()
        ->assertJsonPath('data.0.id', $stargazer->id);
});

it('does not list the stargazers of a commit across a block', function (): void {
    $viewer = User::factory()->create();
    $commit = Commit::factory()->create();
    $viewer->blocking()->attach($commit->user);

    $this->actingAs($viewer)
        ->getJson(route('commit-stargazer.index', $commit))
        ->assertNotFound();
});

it('does not list the stargazers of a commit that is not visible', function (): void {
    $commit = Commit::factory()->pending()->create();

    $this->actingAs(User::factory()->create())
        ->getJson(route('commit-stargazer.index', $commit))
        ->assertNotFound();
});
