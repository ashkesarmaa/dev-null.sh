<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Commit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final readonly class CommitStargazersQuery
{
    public const int PER_PAGE = 30;

    /**
     * The people who starred a commit, the newest star first. Nobody across a block from the viewer is listed, and a
     * viewer also gets whether they follow each one; a guest follows nobody.
     *
     * @return BelongsToMany<User, Commit>
     */
    public function builder(Commit $commit, ?User $viewer): BelongsToMany
    {
        $stars = $commit->stars();

        if ($viewer instanceof User) {
            $stars->whereNotIn('users.id', $viewer->idsAcrossBlocks());
        }

        return $stars
            ->withExists(['followers as is_followed' => fn (Builder $query): Builder => $viewer instanceof User ? $query->whereKey($viewer->id) : $query->whereRaw('1 = 0')])
            ->latest('stars.created_at')
            ->latest('users.id');
    }
}
