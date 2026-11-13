<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Application extends Model
{
    public const STATUSES = ['pending', 'accepted', 'rejected'];

    public const FILTER_STATUSES = ['pending', 'accepted', 'rejected', 'withdrawn'];

    protected $attributes = ['status' => 'pending'];

    protected $fillable = ['listing_id', 'user_id', 'answers', 'status'];

    protected function casts(): array
    {
        return ['answers' => 'array', 'accepted_at' => 'datetime', 'reviewed_at' => 'datetime',
            'withdrawn_at' => 'datetime', 'activity' => 'array'];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending', 'submitted' => $this->reviewed_at ? __('Reviewed') : __('Pending'),
            'reviewed' => __('Reviewed'),
            'accepted' => __('Accepted'),
            'rejected' => __('Not selected'),
            'withdrawn' => __('Withdrawn'),
            default => ucfirst((string) $this->status),
        };
    }

    public function hasTicket(): bool
    {
        return $this->status === 'accepted' && filled($this->ticket_code) && $this->accepted_at !== null;
    }

    /** @return array<int, array{event: string, at: string}> */
    public function timelineEntries(): array
    {
        $events = $this->activity ?? [];
        if ($events === []) {
            if ($this->reviewed_at) {
                $events[] = ['event' => 'reviewed', 'at' => $this->reviewed_at->toIso8601String()];
            }
            if ($this->status === 'accepted' && $this->accepted_at) {
                $events[] = ['event' => 'accepted', 'at' => $this->accepted_at->toIso8601String()];
            }
            if ($this->status === 'rejected') {
                $events[] = ['event' => 'rejected', 'at' => $this->updated_at->toIso8601String()];
            }
        }

        return [['event' => 'submitted', 'at' => $this->created_at->toIso8601String()], ...$events];
    }

    public function recordActivity(string $event): void
    {
        $this->activity = [...array_slice($this->timelineEntries(), 1), ['event' => $event, 'at' => now()->toIso8601String()]];
    }
}
