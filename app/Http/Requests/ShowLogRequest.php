<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Commit;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

final class ShowLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'since' => ['nullable', 'uuid'],
            'through' => ['nullable', 'uuid'],
            'pending' => ['sometimes', 'array', 'list'],
            'pending.*' => ['required', 'uuid', 'distinct'],
        ];
    }

    /**
     * Keep the loaded range when a follow changes who belongs in the log, or new commits arrive on top of it.
     */
    public function through(): ?Commit
    {
        return $this->filled('through')
            ? Commit::query()->withTrashed()->visibleTo($this->user())->find($this->string('through')->value())
            : null;
    }

    /**
     * The newest commit the user has already seen, even if it was deleted since.
     */
    public function since(): ?Commit
    {
        return $this->filled('since')
            ? Commit::query()->withTrashed()->find($this->string('since')->value())
            : null;
    }

    /**
     * @return list<string>
     */
    public function pending(): array
    {
        /** @var list<string> $pending */
        $pending = $this->validated('pending', []);

        return $pending;
    }

    /**
     * When the newest commit the user saw on their last visit to the Following log was committed, as their browser
     * remembers it, unless another account visited it last in the same browser.
     */
    public function followingLastVisitAt(User $user): ?CarbonInterface
    {
        $lastVisit = $this->cookie('following_last_visit');

        if (! is_string($lastVisit) || ! str_starts_with($lastVisit, $user->id.'|')) {
            return null;
        }

        return rescue(fn (): ?CarbonInterface => Date::createFromFormat('Y-m-d\TH:i:s.uP', Str::after($lastVisit, '|')), report: false);
    }

    public function prepareForValidation(): void
    {
        $this->merge(['through' => $this->header('X-Log-Through')]);
    }
}
