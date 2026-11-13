<?php

namespace App\Services;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;

class RecommendationService
{
    private const REASONS = [
        'location' => 'Available in your location',
        'year_levels' => 'Matches your year level',
        'school_types' => 'Accepts your school type',
        'courses' => 'Matches your course',
        'sectors' => 'Meant for your sector or group',
        'age' => 'Fits your age',
        'income' => 'Within the income limit',
    ];

    /** Eligibility and scores are calculated before limits, counts and pagination. */
    public function visibleTo(User $user): Builder
    {
        $base = Listing::query()->leftJoin('seeker_profiles as matching_profile', function (JoinClause $join) use ($user): void {
            $join->where('matching_profile.user_id', $user->id);
        });

        return $this->scored($base)->withExists(['savers as is_saved' => fn (Builder $query) => $query->where('users.id', $user->id)])
            ->withCount('acceptedApplications as accepted_count')
            ->where(function (Builder $query): void {
                $query->where('requirement_count', 0)->orWhereRaw($this->scoreExpression().' > 0');
            });
    }

    public function ranked(Builder $query): Builder
    {
        return $query->orderByDesc('match_score')->orderByRaw('deadline asc nulls last')->orderBy('id');
    }

    public function forUser(User $user, int $limit = 6): Collection
    {
        $listings = $this->ranked($this->visibleTo($user)->open())
            ->with('user:id,organization')
            ->whereNotIn('id', $user->applications()->select('listing_id'))
            ->limit($limit)->get();

        return $listings->each(fn (Listing $listing) => $this->explain($listing));
    }

    public function explain(Listing $listing): Listing
    {
        $reasons = [];
        foreach (self::REASONS as $key => $reason) {
            if ((int) $listing->getAttribute('matches_'.$key) === 1) {
                $reasons[] = __($reason);
            }
        }
        $listing->setAttribute('match_reasons', $reasons ?: [__('Open to everyone')]);

        return $listing;
    }

    /** Score a page of applicants with one query, using the same criteria as seeker visibility. */
    public function applicantScores(Listing $listing, Collection $applications): Collection
    {
        if ($applications->isEmpty()) {
            return collect();
        }
        $base = Listing::query()
            ->join('applications as matching_application', 'matching_application.listing_id', '=', 'listings.id')
            ->leftJoin('seeker_profiles as matching_profile', 'matching_profile.user_id', '=', 'matching_application.user_id')
            ->where('listings.id', $listing->id)
            ->whereIn('matching_application.id', $applications->modelKeys());

        return $this->scored($base, ['matching_application.id as application_id'])->get()->keyBy('application_id');
    }

    /** @param array<int, string> $extraColumns */
    private function scored(Builder $base, array $extraColumns = []): Builder
    {
        $base->select(['listings.*', ...$extraColumns]);
        $configured = [];
        foreach ($this->criteria() as $key => [$active, $matches]) {
            $configured[] = "CASE WHEN {$active} THEN 1 ELSE 0 END";
            $base->selectRaw("CASE WHEN ({$active}) AND ({$matches}) THEN 1 ELSE 0 END as matches_{$key}");
        }
        $base->selectRaw(implode(' + ', $configured).' as requirement_count');

        return Listing::query()->fromSub($base, 'listings')->select('listings.*')
            ->selectRaw($this->scoreExpression().' as match_score');
    }

    private function scoreExpression(): string
    {
        return implode(' + ', array_map(fn (string $key): string => 'matches_'.$key, array_keys(self::REASONS)));
    }

    /** @return array<string, array{string, string}> */
    private function criteria(): array
    {
        $postgres = (new Listing)->getConnection()->getDriverName() === 'pgsql';
        $locationActive = [];
        $locationMatches = [];
        foreach (['region', 'province', 'city'] as $field) {
            $locationActive[] = "NULLIF(TRIM(listings.{$field}), '') IS NOT NULL";
            $locationMatches[] = "(NULLIF(TRIM(listings.{$field}), '') IS NULL OR listings.{$field} = matching_profile.{$field})";
        }
        $criteria = ['location' => [implode(' OR ', $locationActive), implode(' AND ', $locationMatches)]];

        foreach (['year_levels' => 'year_level', 'school_types' => 'school_type', 'courses' => 'course', 'sectors' => 'sectors'] as $field => $profileField) {
            $target = $postgres
                ? "jsonb_array_elements_text(COALESCE(listings.{$field}, '[]'::jsonb)) as target(value)"
                : "json_each(COALESCE(listings.{$field}, '[]')) as target";
            $active = "EXISTS (SELECT 1 FROM {$target})";
            if ($field === 'sectors') {
                $mine = $postgres
                    ? "jsonb_array_elements_text(COALESCE(matching_profile.sectors, '[]'::jsonb)) as mine(value)"
                    : "json_each(COALESCE(matching_profile.sectors, '[]')) as mine";
                $matches = "EXISTS (SELECT 1 FROM {$target} JOIN {$mine} ON target.value = mine.value)";
            } else {
                $comparison = "target.value = matching_profile.{$profileField}";
                if ($field === 'courses') {
                    $course = 'LOWER(TRIM(target.value))';
                    $mine = 'LOWER(TRIM(matching_profile.course))';
                    $overlap = $postgres
                        ? "POSITION({$course} IN {$mine}) > 0 OR POSITION({$mine} IN {$course}) > 0"
                        : "INSTR({$mine}, {$course}) > 0 OR INSTR({$course}, {$mine}) > 0";
                    $comparison = "{$course} <> '' AND {$mine} <> '' AND ({$overlap})";
                }
                $matches = "EXISTS (SELECT 1 FROM {$target} WHERE {$comparison})";
                if ($field === 'year_levels') {
                    $matches .= " AND matching_profile.year_level <> 'none'";
                }
            }
            $criteria[$field] = [$active, $matches];
        }

        $today = today()->toDateString();
        $age = $postgres
            ? "EXTRACT(YEAR FROM AGE(DATE '{$today}', matching_profile.birthdate))"
            : "(CAST(strftime('%Y', '{$today}') AS INTEGER) - CAST(strftime('%Y', matching_profile.birthdate) AS INTEGER) - (strftime('%m-%d', '{$today}') < strftime('%m-%d', matching_profile.birthdate)))";
        $criteria['age'] = [
            'listings.age_min IS NOT NULL OR listings.age_max IS NOT NULL',
            "matching_profile.birthdate IS NOT NULL AND matching_profile.birthdate <= '{$today}' AND (listings.age_min IS NULL OR {$age} >= listings.age_min) AND (listings.age_max IS NULL OR {$age} <= listings.age_max)",
        ];
        $criteria['income'] = [
            'listings.income_max IS NOT NULL',
            'matching_profile.income_monthly IS NOT NULL AND matching_profile.income_monthly <= listings.income_max',
        ];

        return $criteria;
    }
}
