<?php

namespace App\Services\Ai;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Throwable;

class AiSettings
{
    public const GROUP = 'ai';

    public const KEY_MODEL = 'ai.model';

    public const KEY_TIMEOUT = 'ai.timeout';

    public const KEY_COMMENTS_LIMIT = 'ai.comments_limit';

    public const KEY_CONTENT_LIMIT = 'ai.content_limit';

    /**
     * @var array<string, mixed>|null
     */
    private ?array $resolved = null;

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        return $this->resolved = array_replace($this->defaults(), $this->stored());
    }

    public function model(): string
    {
        $model = trim((string) $this->all()[self::KEY_MODEL]);

        return $model !== '' ? $model : (string) config('deepseek.model', 'deepseek-chat');
    }

    public function timeout(): int
    {
        return max(10, min(180, (int) $this->all()[self::KEY_TIMEOUT]));
    }

    public function commentsLimit(): int
    {
        return max(0, min(100, (int) $this->all()[self::KEY_COMMENTS_LIMIT]));
    }

    public function contentLimit(): int
    {
        return max(2000, min(60000, (int) $this->all()[self::KEY_CONTENT_LIMIT]));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(array $data): void
    {
        $rows = [
            self::KEY_MODEL => ['value' => trim((string) $data['deepseek_model']), 'type' => 'string'],
            self::KEY_TIMEOUT => ['value' => (string) $data['deepseek_timeout'], 'type' => 'integer'],
            self::KEY_COMMENTS_LIMIT => ['value' => (string) $data['ai_comments_limit'], 'type' => 'integer'],
            self::KEY_CONTENT_LIMIT => ['value' => (string) $data['ai_content_limit'], 'type' => 'integer'],
        ];

        DB::transaction(function () use ($rows): void {
            foreach ($rows as $key => $row) {
                Setting::query()->updateOrCreate(
                    ['key' => $key],
                    ['group' => self::GROUP, 'value' => $row['value'], 'type' => $row['type']]
                );
            }
        });

        $this->resolved = null;
    }

    /**
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        return [
            self::KEY_MODEL => (string) config('deepseek.model', 'deepseek-chat'),
            self::KEY_TIMEOUT => (int) config('deepseek.timeout', 60),
            self::KEY_COMMENTS_LIMIT => (int) config('deepseek.comments_limit', 40),
            self::KEY_CONTENT_LIMIT => (int) config('deepseek.prompt_char_limit', 24000),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function stored(): array
    {
        try {
            $rows = Setting::query()
                ->where('group', self::GROUP)
                ->get(['key', 'value', 'type']);
        } catch (Throwable) {
            return [];
        }

        $defaults = $this->defaults();
        $values = [];

        foreach ($rows as $row) {
            if (! array_key_exists($row->key, $defaults)) {
                continue;
            }

            $values[$row->key] = $row->type === 'integer'
                ? (int) $row->value
                : (string) $row->value;
        }

        return $values;
    }
}
