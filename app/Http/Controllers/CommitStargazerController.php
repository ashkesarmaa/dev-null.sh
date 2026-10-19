<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListCommitStargazersRequest;
use App\Models\Commit;
use App\Models\User;
use App\Queries\CommitStargazersQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class CommitStargazerController
{
    public function index(ListCommitStargazersRequest $request, #[CurrentUser] ?User $user, Commit $commit, CommitStargazersQuery $stargazers): JsonResponse
    {
        return response()->json($stargazers->builder($commit, $user)->cursorPaginate(CommitStargazersQuery::PER_PAGE));
    }
}
