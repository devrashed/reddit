<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollectionLog extends Model
{
    use HasFactory;

    public const TYPE_INITIAL = 'initial';

    public const TYPE_REGULAR = 'regular';

    public const TYPE_COMMENTS = 'comments';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'subreddit_id',
        'collection_type',
        'status',
        'posts_fetched',
        'posts_created',
        'posts_updated',
        'qualified_posts',
        'pending_posts_checked',
        'newly_qualified_posts',
        'comments_fetched',
        'failed_operations',
        'error_message',
        'notes',
        'started_at',
        'finished_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'posts_fetched' => 'integer',
            'posts_created' => 'integer',
            'posts_updated' => 'integer',
            'qualified_posts' => 'integer',
            'pending_posts_checked' => 'integer',
            'newly_qualified_posts' => 'integer',
            'comments_fetched' => 'integer',
            'failed_operations' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Subreddit, $this>
     */
    public function subreddit(): BelongsTo
    {
        return $this->belongsTo(Subreddit::class);
    }
}
