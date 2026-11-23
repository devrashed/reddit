<?php

namespace App\Services;

use App\Enums\InitialCollectionStatus;
use App\Enums\SubredditStatus;
use App\Models\Subreddit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SubredditService
{
    public const PER_PAGE = 10;

    /**
     * Paginate subreddits using the supplied search and status filters.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Subreddit>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = $filters['status'] ?? null;

        return Subreddit::query()
            ->withCount('posts')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('display_name', 'like', "%{$search}%");
                });
            })
            ->when($status, fn (Builder $query, string $status) => $query->where('status', $status))
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    /**
     * Create a subreddit and prepare its initial historical collection.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Subreddit
    {
        $name = Subreddit::normalizeName((string) $data['name']);

        $this->ensureNameIsUnique($name);

        $subreddit = new Subreddit;
        $subreddit->name = $name;
        $subreddit->display_name = $this->displayName($data, $name);
        $subreddit->description = $this->description($data);
        $subreddit->url = Subreddit::urlForName($name);
        $subreddit->status = SubredditStatus::Active;
        $subreddit->initial_collection_status = InitialCollectionStatus::Pending;

        $this->persist($subreddit);

        return $subreddit;
    }

    /**
     * Update an existing subreddit.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Subreddit $subreddit, array $data): Subreddit
    {
        $name = Subreddit::normalizeName((string) $data['name']);

        $this->ensureNameIsUnique($name, $subreddit->getKey());

        $subreddit->name = $name;
        $subreddit->display_name = $this->displayName($data, $name);
        $subreddit->description = $this->description($data);
        $subreddit->url = Subreddit::urlForName($name);

        $this->persist($subreddit);

        return $subreddit;
    }

    /**
     * Activate or pause a subreddit.
     */
    public function updateStatus(Subreddit $subreddit, SubredditStatus $status): Subreddit
    {
        if ($subreddit->status !== $status) {
            $subreddit->status = $status;
            $subreddit->save();
        }

        return $subreddit;
    }

    /**
     * Delete a subreddit. Related posts are removed by the foreign key cascade.
     */
    public function delete(Subreddit $subreddit): void
    {
        DB::transaction(fn () => $subreddit->delete());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function displayName(array $data, string $name): string
    {
        $displayName = trim((string) ($data['display_name'] ?? ''));

        return $displayName !== '' ? $displayName : $name;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function description(array $data): ?string
    {
        $description = trim((string) ($data['description'] ?? ''));

        return $description !== '' ? $description : null;
    }

    private function ensureNameIsUnique(string $name, ?int $ignoreId = null): void
    {
        $query = Subreddit::query()
            ->whereRaw('LOWER(name) = ?', [Str::lower($name)]);

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'name' => 'This subreddit has already been added.',
            ]);
        }
    }

    private function persist(Subreddit $subreddit): void
    {
        try {
            $subreddit->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'name' => 'This subreddit has already been added.',
            ]);
        }
    }
}
