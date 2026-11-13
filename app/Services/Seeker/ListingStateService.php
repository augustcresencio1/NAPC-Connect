<?php

namespace App\Services\Seeker;

use App\Models\Listing;
use App\Models\User;
use App\Services\RecommendationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ListingStateService
{
    public function __construct(private RecommendationService $recommendations) {}

    public function saved(User $user): Builder
    {
        return $this->recommendations->visibleTo($user)->where('status', 'published')
            ->join('seeker_listing_states as seeker_state', 'seeker_state.listing_id', '=', 'listings.id')
            ->where('seeker_state.user_id', $user->id)->whereNotNull('seeker_state.saved_at')
            ->addSelect(['seeker_state.saved_at', 'seeker_state.reminder_read_deadline']);
    }

    public function closingSoon(User $user): Builder
    {
        return $this->recommendations->visibleTo($user)->open()->whereNotNull('deadline')
            ->whereDate('deadline', '<=', today()->addDays(3));
    }

    public function reminders(User $user): Builder
    {
        return $this->saved($user)->open()->whereNotNull('deadline')
            ->whereDate('deadline', '<=', today()->addDays(3));
    }

    public function unreadCount(User $user): int
    {
        return $this->reminders($user)->where(function (Builder $query): void {
            $query->whereNull('seeker_state.reminder_read_deadline')
                ->orWhereRaw('DATE(seeker_state.reminder_read_deadline) <> DATE(listings.deadline)');
        })->count();
    }

    public function state(User $user, Listing $listing): ?object
    {
        return DB::table('seeker_listing_states')->where('user_id', $user->id)->where('listing_id', $listing->id)->first();
    }

    public function visibleListing(User $user, Listing $listing): Listing
    {
        return $this->recommendations->visibleTo($user)->where('status', 'published')->whereKey($listing->id)->firstOrFail();
    }

    /** @param array<string, mixed> $changes */
    public function update(User $user, Listing $listing, array $changes): void
    {
        DB::table('seeker_listing_states')->upsert([
            ['user_id' => $user->id, 'listing_id' => $listing->id, ...$changes],
        ], ['user_id', 'listing_id'], array_keys($changes));
    }
}
