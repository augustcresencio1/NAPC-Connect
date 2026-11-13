<?php

namespace App\Models;

use App\Support\Locations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Listing extends Model
{
    protected $fillable = [
        'type',
        'title',
        'description',
        'status',
        'published_at',
        'deadline',
        'source_url',
        'region',
        'province',
        'city',
        'year_levels',
        'school_types',
        'courses',
        'sectors',
        'age_min',
        'age_max',
        'income_max',
        'other_requirements',
        'details',
        'apply_mode',
        'apply_url',
        'how_to_apply',
        'application_form',
        'acceptance_limit',
    ];

    protected function casts(): array
    {
        return [
            'deadline' => 'date',
            'published_at' => 'datetime',
            'year_levels' => 'array',
            'school_types' => 'array',
            'courses' => 'array',
            'sectors' => 'array',
            'details' => 'array',
            'application_form' => 'array',
            'taken_down_at' => 'datetime',
            'acceptance_limit' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function savers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'seeker_listing_states')->wherePivotNotNull('saved_at');
    }

    public function acceptedApplications(): HasMany
    {
        return $this->applications()->where('status', 'accepted');
    }

    /** @return array{limit: ?int, accepted: int, remaining: ?int, label: string} */
    public function availability(): array
    {
        $accepted = isset($this->accepted_count) ? (int) $this->accepted_count : $this->acceptedApplications()->count();
        $limit = $this->apply_mode === 'form' ? $this->acceptance_limit : null;
        $remaining = $limit === null ? null : max(0, $limit - $accepted);

        return [
            'limit' => $limit, 'accepted' => $accepted, 'remaining' => $remaining,
            'label' => $remaining === null ? __('No acceptance limit set')
                : __(':remaining of :total slots left', ['remaining' => $remaining, 'total' => $limit]),
        ];
    }

    public function typeLabel(): string
    {
        return __(config("napc.types.{$this->type}", ucfirst((string) $this->type)));
    }

    public function locationLabel(): string
    {
        if (! $this->region) {
            return __('Nationwide');
        }

        return collect([
            Locations::city($this->city),
            Locations::province($this->province) ?: Locations::region($this->region),
        ])->filter()->implode(', ');
    }

    public function isExpired(): bool
    {
        return $this->deadline !== null && $this->deadline->copy()->endOfDay()->isPast();
    }

    /** Removed by an admin. The organization cannot publish it again until it is restored. */
    public function isTakenDown(): bool
    {
        return $this->taken_down_at !== null;
    }

    public function deadlineLabel(): string
    {
        if (! $this->deadline) {
            return __('No deadline');
        }

        $date = $this->deadline->translatedFormat('M j, Y');

        if ($this->isExpired()) {
            return __('Deadline passed (:date)', ['date' => $date]);
        }

        $left = (int) now()->startOfDay()->diffInDays($this->deadline->copy()->startOfDay(), false);

        return match (true) {
            $left <= 0 => __('Closes today (:date)', ['date' => $date]),
            $left === 1 => __('Closes tomorrow (:date)', ['date' => $date]),
            default => __('Closes :date (:days days left)', ['date' => $date, 'days' => $left]),
        };
    }

    /** Published and not past its deadline: what seekers can browse */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->where(fn ($q) => $q->whereNull('deadline')->orWhereDate('deadline', '>=', today()));
    }

    /** "Who can apply" as label => value, skipping anything left open */
    public function requirementRows(): array
    {
        $labels = fn (string $group, ?array $keys) => collect($keys ?? [])
            ->map(fn ($key) => __(config("napc.{$group}.{$key}", $key)))
            ->implode(', ');

        $age = match (true) {
            $this->age_min !== null && $this->age_max !== null => __(':min to :max years old', ['min' => $this->age_min, 'max' => $this->age_max]),
            $this->age_min !== null => __(':age years old and above', ['age' => $this->age_min]),
            $this->age_max !== null => __('Up to :age years old', ['age' => $this->age_max]),
            default => null,
        };

        return array_filter([
            'Location' => $this->locationLabel(),
            'Year level' => $labels('year_levels', $this->year_levels),
            'School type' => $labels('school_types', $this->school_types),
            'Courses' => collect($this->courses ?? [])->implode(', '),
            'Sector or group' => $labels('sectors', $this->sectors),
            'Age' => $age,
            'Household income' => $this->income_max !== null
                ? __('Up to PHP :amount per month', ['amount' => number_format($this->income_max)])
                : null,
            'Other requirements' => $this->other_requirements,
        ], fn ($value) => filled($value));
    }

    /** The extra details the organization added, with their labels */
    public function detailRows(): array
    {
        return collect($this->details ?? [])
            ->reject(fn ($value, $key) => $this->apply_mode === 'form' && in_array($key, ['slots', 'vacancies'], true))
            ->mapWithKeys(fn ($value, $key) => [
                __(config("napc.detail_fields.{$this->type}.{$key}.label", ucfirst(str_replace('_', ' ', $key)))) => $value,
            ])
            ->all();
    }

    /** Stable keys keep progress tied to the document text when a poster edits the list. */
    public function documentItems(): array
    {
        $text = $this->details['documents'] ?? $this->details['what_to_bring'] ?? '';

        return collect(preg_split('/\R/u', (string) $text) ?: [])
            ->map(fn (string $line): string => trim(preg_replace('/^\s*[-*•]\s*/u', '', $line)))
            ->filter()->unique()->mapWithKeys(fn (string $line): array => [hash('sha256', $line) => $line])->all();
    }
}
