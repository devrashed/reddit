<?php

namespace App\Models;

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
        'is_active',
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
            'is_active' => 'boolean',
            'subscribers_count' => 'integer',
            'last_collected_at' => 'datetime',
        ];
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
