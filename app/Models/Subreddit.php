<?php

namespace App\Models;

use App\Enums\InitialCollectionStatus;
use App\Enums\SubredditStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subreddit extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'display_name',
        'description',
        'subscribers_count',
        'status',
        'last_collected_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SubredditStatus::class,
            'initial_collection_status' => InitialCollectionStatus::class,
            'subscribers_count' => 'integer',
            'initial_collection_started_at' => 'datetime',
            'initial_collection_completed_at' => 'datetime',
            'last_collected_at' => 'datetime',
        ];
    }

    /**
     * Normalize a user supplied subreddit name.
     */
    public static function normalizeName(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('#^(?:https?://)?(?:www\.)?reddit\.com/r/#i', '', $name) ?? $name;
        $name = preg_replace('#^/?r/#i', '', $name) ?? $name;
        $name = preg_replace('#\.json$#i', '', $name) ?? $name;

        return trim($name, "/ \t\n\r\0\x0B");
    }

    /**
     * Build the canonical Reddit JSON endpoint for a subreddit name.
     */
    public static function urlForName(string $name): string
    {
        return 'https://www.reddit.com/r/'.rawurlencode($name).'.json';
    }

    /**
     * @return HasMany<RedditPost, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(RedditPost::class);
    }

    /**
     * @return HasMany<CollectionLog, $this>
     */
    public function collectionLogs(): HasMany
    {
        return $this->hasMany(CollectionLog::class);
    }
}
