<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Listing;
use App\Models\User;
use App\Services\Seeker\ListingStateService;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SeekerToolsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo('2026-11-13 12:00:00');
    }

    private function seeker(): User
    {
        return User::factory()->create(['role' => 'seeker']);
    }

    private function listing(array $attributes = []): Listing
    {
        return User::factory()->create(['role' => 'poster', 'poster_status' => 'approved'])->listings()->create(array_merge([
            'title' => 'Community support', 'description' => 'A program for the community', 'type' => 'ayuda',
            'status' => 'published', 'published_at' => now(), 'apply_mode' => 'form',
            'details' => ['documents' => "Valid ID\nBarangay certificate"],
        ], $attributes));
    }

    private function application(User $seeker, ?Listing $listing = null): Application
    {
        return ($listing ?? $this->listing())->applications()->create(['user_id' => $seeker->id]);
    }

    public function test_saving_is_idempotent_and_updates_heart_and_saved_page(): void
    {
        $user = $this->seeker();
        $listing = $this->listing();

        $this->actingAs($user)->post(route('seeker.saved.store', $listing))->assertRedirect();
        $this->post(route('seeker.saved.store', $listing))->assertRedirect();
        $this->assertDatabaseCount('seeker_listing_states', 1);
        $this->get(route('seeker.saved.index'))->assertOk()->assertSee($listing->title)->assertSee('aria-pressed="true"', false);
        $this->get(route('seeker.listings.show', $listing))->assertOk()->assertSee('Remove from saved');
        $this->delete(route('seeker.saved.destroy', $listing))->assertRedirect();
        $this->get(route('seeker.saved.index'))->assertOk()->assertSee('No saved listings yet');
    }

    public function test_saved_listings_recheck_current_eligibility_and_publish_status(): void
    {
        $user = $this->seeker();
        $listing = $this->listing();
        $this->actingAs($user)->post(route('seeker.saved.store', $listing));
        $listing->update(['sectors' => ['pwd']]);

        $this->get(route('seeker.saved.index'))->assertOk()->assertDontSee($listing->title);
        $this->post(route('seeker.saved.store', $listing))->assertNotFound();
        $listing->update(['sectors' => [], 'status' => 'draft']);
        $this->get(route('seeker.saved.index'))->assertOk()->assertDontSee($listing->title);
    }

    public function test_unsaving_does_not_change_another_seekers_saved_listing(): void
    {
        $owner = $this->seeker();
        $other = $this->seeker();
        $listing = $this->listing();
        app(ListingStateService::class)->update($owner, $listing, ['saved_at' => now()]);

        $this->actingAs($other)->delete(route('seeker.saved.destroy', $listing))->assertNotFound();

        $this->assertDatabaseHas('seeker_listing_states', ['user_id' => $owner->id, 'listing_id' => $listing->id]);
        $this->assertNotNull(app(ListingStateService::class)->state($owner, $listing)->saved_at);
        $this->get(route('seeker.saved.index'))->assertOk()->assertDontSee($listing->title);
    }

    public function test_saved_page_is_paginated(): void
    {
        $user = $this->seeker();
        for ($i = 0; $i < 11; $i++) {
            app(ListingStateService::class)->update($user, $this->listing(), ['saved_at' => now()]);
        }

        $this->actingAs($user)->get(route('seeker.saved.index'))->assertOk()
            ->assertViewHas('listings', fn ($rows) => $rows->total() === 11 && $rows->count() === 9);
        $this->get(route('seeker.saved.index', ['page' => 2]))->assertOk()
            ->assertViewHas('listings', fn ($rows) => $rows->count() === 2);
        $this->put(route('seeker.preferences.update'), ['locale' => 'fil']);
        $this->get(route('seeker.saved.index'))->assertOk()->assertSee('Susunod')->assertSee('Pahina 1 sa 2');
    }

    public function test_reminders_include_today_through_three_days_and_only_saved_eligible_listings(): void
    {
        $user = $this->seeker();
        $states = app(ListingStateService::class);
        foreach (['2026-11-12', '2026-11-13', '2026-11-16', '2026-11-17'] as $date) {
            $states->update($user, $this->listing(['title' => 'Deadline '.$date, 'deadline' => $date]), ['saved_at' => now()]);
        }
        $this->listing(['title' => 'Unsaved soon', 'deadline' => '2026-11-14']);
        $states->update($user, $this->listing(['title' => 'Not eligible', 'deadline' => '2026-11-14', 'sectors' => ['pwd']]), ['saved_at' => now()]);

        $this->actingAs($user)->get(route('seeker.reminders.index'))->assertOk()
            ->assertSee('Deadline 2026-11-13')->assertSee('Deadline 2026-11-16')
            ->assertDontSee('Deadline 2026-11-12')->assertDontSee('Deadline 2026-11-17')
            ->assertDontSee('Unsaved soon')->assertDontSee('Not eligible');
        $this->assertSame(2, $states->unreadCount($user));
        $this->get(route('seeker.home'))->assertOk()->assertSee('Closing soon')->assertSee('Unsaved soon');
    }

    public function test_reminder_read_state_is_private_and_resets_for_changed_deadlines(): void
    {
        $user = $this->seeker();
        $other = $this->seeker();
        $listing = $this->listing(['deadline' => '2026-11-15']);
        $states = app(ListingStateService::class);
        $states->update($user, $listing, ['saved_at' => now()]);

        $this->actingAs($other)->patch(route('seeker.reminders.update', $listing))->assertNotFound();
        $this->actingAs($user)->patch(route('seeker.reminders.update', $listing))->assertRedirect();
        $this->assertSame(0, $states->unreadCount($user));
        $this->get(route('seeker.reminders.index'))->assertOk()->assertSee('Read')->assertDontSee('Mark as read');
        $listing->update(['deadline' => '2026-11-16']);
        $this->assertSame(1, $states->unreadCount($user));
        $this->travel(4)->days();
        $this->assertSame(0, $states->unreadCount($user));
    }

    public function test_checklist_persists_independently_of_saved_status_and_cannot_change_another_users_progress(): void
    {
        $user = $this->seeker();
        $other = $this->seeker();
        $listing = $this->listing();
        $key = hash('sha256', 'Valid ID');

        $this->actingAs($user)->put(route('seeker.checklist.update', $listing), ['checked' => [$key], 'user_id' => $other->id])->assertRedirect();
        $this->assertDatabaseCount('seeker_listing_states', 1);
        $this->get(route('seeker.listings.show', $listing))->assertOk()->assertSee('Document checklist')
            ->assertViewHas('checkedDocuments', [$key]);
        $this->actingAs($other)->get(route('seeker.listings.show', $listing))->assertOk()->assertViewHas('checkedDocuments', []);
        $this->actingAs($user)->post(route('seeker.saved.store', $listing));
        $this->delete(route('seeker.saved.destroy', $listing));
        $this->get(route('seeker.listings.show', $listing))->assertOk()->assertViewHas('checkedDocuments', [$key]);
        $this->put(route('seeker.checklist.update', $listing), [])->assertRedirect();
        $this->get(route('seeker.listings.show', $listing))->assertOk()->assertViewHas('checkedDocuments', []);
    }

    public function test_invalid_document_keys_or_ineligible_checklists_are_rejected(): void
    {
        $user = $this->seeker();
        $listing = $this->listing();

        $this->actingAs($user)->putJson(route('seeker.checklist.update', $listing), ['checked' => ['forged']])
            ->assertUnprocessable()->assertJsonValidationErrors('checked.0');
        $this->assertDatabaseCount('seeker_listing_states', 0);
        $listing->update(['sectors' => ['pwd']]);
        $this->put(route('seeker.checklist.update', $listing), [])->assertNotFound();
    }

    public function test_preferences_persist_and_translate_seeker_pages_with_accessibility_classes(): void
    {
        $user = $this->seeker();
        $listing = $this->listing(['title' => 'Original announcement']);

        $this->actingAs($user)->put(route('seeker.preferences.update'), ['locale' => 'fil', 'large_text' => 1, 'high_contrast' => 1, 'role' => 'admin'])->assertRedirect();
        $user->refresh();
        $this->assertSame('seeker', $user->role);
        $this->assertSame(['locale' => 'fil', 'large_text' => true, 'high_contrast' => true], $user->ui_preferences);
        $this->actingAs($user)->get(route('seeker.home'))->assertOk()->assertSee('lang="fil"', false)
            ->assertSee('large-text high-contrast')->assertSee('Malapit nang magsara')->assertSee('Mga mungkahi para sa iyo');
        $this->get(route('seeker.listings.index'))->assertOk()->assertSee('Maghanap ng mga anunsyo')->assertSee($listing->title);
        $this->get(route('seeker.applications.index'))->assertOk()->assertSee('Aking mga aplikasyon');
        $this->get(route('seeker.profile.edit'))->assertOk()->assertSee('Aking profile');
        $this->put(route('seeker.preferences.update'), ['locale' => 'en'])->assertRedirect();
        $this->get(route('seeker.home'))->assertOk()->assertSee('Closing soon')->assertDontSee('class="large-text high-contrast"', false);
    }

    public function test_unknown_language_is_rejected(): void
    {
        $user = $this->seeker();

        $this->actingAs($user)->putJson(route('seeker.preferences.update'), ['locale' => '../../env'])->assertUnprocessable()->assertJsonValidationErrors('locale');

        $this->assertNull($user->fresh()->ui_preferences);
    }

    public function test_review_and_decisions_have_dated_history_even_after_reversal(): void
    {
        $user = $this->seeker();
        $application = $this->application($user);
        $listing = $application->listing;

        $this->actingAs($listing->user)->patch(route('poster.applications.review', [$listing, $application]))->assertRedirect();
        $this->patch(route('poster.applications.review', [$listing, $application]))->assertRedirect();
        $application->refresh();
        $this->assertSame('2026-11-13 12:00:00', $application->reviewed_at->format('Y-m-d H:i:s'));
        $this->assertCount(2, $application->timelineEntries());
        $this->travel(1)->hours();
        $this->patch(route('poster.applications.update', [$listing, $application]), ['status' => 'accepted'])->assertRedirect();
        $this->travel(1)->hours();
        $this->patch(route('poster.applications.update', [$listing, $application]), ['status' => 'pending'])->assertRedirect();
        $application->refresh();
        $this->assertSame(['submitted', 'reviewed', 'accepted', 'pending'], array_column($application->timelineEntries(), 'event'));
        $this->assertSame('2026-11-13T13:00:00+00:00', $application->timelineEntries()[2]['at']);
        $this->actingAs($user)->get(route('seeker.applications.index'))->assertOk()->assertSee('Application timeline')->assertSee('Reviewed')->assertSee('Moved to pending');
    }

    public function test_pending_reviewed_application_can_be_withdrawn_and_poster_cannot_restore_it(): void
    {
        $user = $this->seeker();
        $application = $this->application($user);
        app(TicketService::class)->review($application);

        $this->actingAs($user)->patch(route('seeker.applications.withdraw', $application))->assertRedirect();
        $this->assertDatabaseHas('applications', ['id' => $application->id, 'status' => 'withdrawn', 'ticket_code' => null]);
        $this->assertNotNull($application->fresh()->withdrawn_at);
        $this->get(route('seeker.applications.index'))->assertOk()->assertSee('Withdrawn')->assertDontSee('Withdraw application');
        $this->actingAs($application->listing->user)->patch(route('poster.applications.update', [$application->listing, $application]), ['status' => 'accepted'])->assertConflict();
        $this->get(route('poster.listings.applications', ['listing' => $application->listing, 'status' => 'withdrawn']))->assertOk()->assertDontSee('Accept this application');
    }

    public function test_withdrawal_is_denied_after_decision_and_for_other_users(): void
    {
        $user = $this->seeker();
        $application = $this->application($user);
        $other = $this->seeker();

        $this->actingAs($other)->patch(route('seeker.applications.withdraw', $application))->assertNotFound();
        app(TicketService::class)->decide($application, 'accepted');
        $this->actingAs($user)->patch(route('seeker.applications.withdraw', $application))->assertConflict();
        $this->assertSame('accepted', $application->fresh()->status);
    }

    public function test_reviews_require_correct_listing_owner_and_pending_status(): void
    {
        $application = $this->application($this->seeker());
        $otherListing = $this->listing();

        $this->actingAs($otherListing->user)->patch(route('poster.applications.review', [$application->listing, $application]))->assertNotFound();
        $this->patch(route('poster.applications.review', [$otherListing, $application]))->assertNotFound();
        app(TicketService::class)->decide($application, 'rejected');
        $this->actingAs($application->listing->user)->patch(route('poster.applications.review', [$application->listing, $application]))->assertConflict();
        $this->assertNull($application->fresh()->reviewed_at);
    }

    public static function wrongRoles(): array
    {
        return ['poster' => ['poster'], 'admin' => ['admin']];
    }

    #[DataProvider('wrongRoles')]
    public function test_seeker_tools_are_hidden_from_other_roles(string $role): void
    {
        $user = User::factory()->create(['role' => $role]);
        $listing = $this->listing();

        $this->actingAs($user)->get(route('seeker.saved.index'))->assertNotFound();
        $this->post(route('seeker.saved.store', $listing))->assertNotFound();
        $this->put(route('seeker.checklist.update', $listing), [])->assertNotFound();
        $this->put(route('seeker.preferences.update'), ['locale' => 'fil'])->assertNotFound();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('seeker.saved.index'))->assertRedirect(route('login'));
        $this->get(route('seeker.reminders.index'))->assertRedirect(route('login'));
    }
}
