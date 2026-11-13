<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\User;
use App\Services\RecommendationService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ListingMatchingTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo('2026-11-13 12:00:00');
    }

    private function listing(array $attributes = [], ?User $owner = null): Listing
    {
        $owner ??= User::factory()->create(['role' => 'poster', 'poster_status' => 'approved']);

        return $owner->listings()->create(array_merge([
            'title' => 'Community support', 'description' => 'Support for seekers',
            'type' => 'ayuda', 'status' => 'published', 'published_at' => now(),
            'apply_mode' => 'form',
        ], $attributes));
    }

    public function test_one_match_keeps_a_listing_visible_despite_other_mismatches(): void
    {
        $seeker = User::factory()->create(['role' => 'seeker']);
        $seeker->seekerProfile()->create(['sectors' => ['pwd'], 'birthdate' => '2000-01-01']);
        $listing = $this->listing(['sectors' => ['pwd'], 'age_min' => 60]);

        $this->actingAs($seeker)->get(route('seeker.listings.index'))
            ->assertOk()->assertSee($listing->title)->assertSee('Meant for your sector or group');
        $this->get(route('seeker.listings.show', $listing))->assertOk();
        $this->get(route('seeker.applications.create', $listing))->assertOk();
        $this->post(route('seeker.applications.store', $listing))->assertRedirect(route('seeker.applications.index'));
        $this->assertDatabaseHas('applications', ['listing_id' => $listing->id, 'user_id' => $seeker->id]);
    }

    public function test_missing_profile_sees_only_open_listings_in_home_browse_and_counts(): void
    {
        $seeker = User::factory()->create(['role' => 'seeker']);
        $open = $this->listing(['title' => 'Open support', 'sectors' => [], 'courses' => []]);
        $targeted = $this->listing(['title' => 'Targeted support', 'region' => '01']);

        $this->actingAs($seeker)->get(route('seeker.home'))->assertOk()
            ->assertSee($open->title)->assertDontSee($targeted->title)->assertSee('only listings open to everyone');
        $this->get(route('seeker.listings.index'))->assertOk()
            ->assertSee('Complete your profile')->assertSee('Open to everyone')
            ->assertViewHas('counts', fn ($counts) => $counts['all'] === 1 && $counts['ayuda'] === 1);
        $this->get(route('seeker.listings.show', $targeted))->assertNotFound();
    }

    public function test_ineligible_direct_links_and_post_fail_before_form_validation(): void
    {
        $seeker = User::factory()->create(['role' => 'seeker']);
        $seeker->seekerProfile()->create(['sectors' => []]);
        $listing = $this->listing(['sectors' => ['pwd'], 'application_form' => [
            ['key' => 'reason', 'type' => 'text', 'label' => 'Reason', 'required' => true],
        ]]);

        $this->actingAs($seeker)->get(route('seeker.listings.show', $listing))->assertNotFound();
        $this->get(route('seeker.applications.create', $listing))->assertNotFound();
        $this->post(route('seeker.applications.store', $listing), [])->assertNotFound();
        $this->assertDatabaseCount('applications', 0);
    }

    public static function criterionCases(): array
    {
        return [
            'region matches' => [['region' => '01'], ['region' => '01'], true],
            'city mismatch despite same region' => [['region' => '01', 'city' => '0101'], ['region' => '01', 'city' => '0102'], false],
            'missing province' => [['province' => '010'], [], false],
            'year level matches' => [['year_levels' => ['college1', 'college2']], ['year_level' => 'college2'], true],
            'not in school' => [['year_levels' => ['college1']], ['year_level' => 'none'], false],
            'school type matches' => [['school_types' => ['public']], ['school_type' => 'public'], true],
            'school type missing' => [['school_types' => ['public']], [], false],
            'course ignores case and surrounding spaces' => [['courses' => ['Computer Science']], ['course' => ' computer SCIENCE '], true],
            'course mismatch' => [['courses' => ['Nursing']], ['course' => 'Engineering'], false],
            'course preserves partial text matching' => [['courses' => ['Computer Science']], ['course' => 'BS Computer Science'], true],
            'blank course never matches' => [['courses' => ['Nursing']], ['course' => '  '], false],
            'sector overlap' => [['sectors' => ['pwd', 'ip']], ['sectors' => ['ip', '4ps']], true],
            'missing sectors' => [['sectors' => ['pwd']], [], false],
            'age inclusive' => [['age_min' => 26, 'age_max' => 26], ['birthdate' => '2000-11-13'], true],
            'birthday tomorrow' => [['age_min' => 26], ['birthdate' => '2000-11-14'], false],
            'age upper limit only' => [['age_max' => 25], ['birthdate' => '2000-11-14'], true],
            'missing birthday' => [['age_max' => 25], [], false],
            'income inclusive' => [['income_max' => 10000], ['income_monthly' => 10000], true],
            'zero income limit' => [['income_max' => 0], ['income_monthly' => 0], true],
            'income too high' => [['income_max' => 10000], ['income_monthly' => 10001], false],
            'missing income' => [['income_max' => 10000], [], false],
        ];
    }

    #[DataProvider('criterionCases')]
    public function test_each_criterion_controls_visibility(array $requirements, array $profile, bool $visible): void
    {
        $seeker = User::factory()->create(['role' => 'seeker']);
        $seeker->seekerProfile()->create($profile);
        $listing = $this->listing($requirements);

        $actual = app(RecommendationService::class)->visibleTo($seeker)->whereKey($listing->id)->exists();

        $this->assertSame($visible, $actual);
    }

    public function test_pagination_and_counts_use_all_matching_rows_and_relevance_order(): void
    {
        $seeker = User::factory()->create(['role' => 'seeker']);
        $seeker->seekerProfile()->create(['sectors' => ['pwd'], 'income_monthly' => 0]);
        $owner = User::factory()->create(['role' => 'poster', 'poster_status' => 'approved']);
        for ($i = 0; $i < 12; $i++) {
            $this->listing(['sectors' => ['pwd'], 'deadline' => '2026-12-01'], $owner);
        }
        $best = $this->listing(['title' => 'Best match', 'sectors' => ['pwd'], 'income_max' => 0, 'deadline' => '2026-12-31'], $owner);
        $nearest = $this->listing(['title' => 'Nearest match', 'sectors' => ['pwd'], 'deadline' => '2026-11-14'], $owner);
        $this->listing(['sectors' => ['ip']], $owner);

        $this->actingAs($seeker)->get(route('seeker.listings.index'))->assertOk()
            ->assertViewHas('listings', fn ($rows) => $rows->total() === 14 && $rows->count() === 9
                && $rows->first()->id === $best->id && $rows->getCollection()->get(1)->id === $nearest->id)
            ->assertViewHas('counts', fn ($counts) => $counts['all'] === 14);
        $this->get(route('seeker.listings.index', ['page' => 2]))->assertOk()
            ->assertViewHas('listings', fn ($rows) => $rows->count() === 5);
        $this->get(route('seeker.listings.index', ['sort' => 'deadline']))->assertOk()
            ->assertViewHas('listings', fn ($rows) => $rows->first()->id === $nearest->id);
    }

    public function test_search_location_and_category_counts_share_filters(): void
    {
        $seeker = User::factory()->create(['role' => 'seeker']);
        DB::table('psgc_regions')->insert(['code' => '01', 'name' => 'Test region']);
        $this->listing(['title' => 'Rice support']);
        $this->listing(['title' => 'Rice training', 'type' => 'training']);
        $this->listing(['title' => 'Other support']);
        $this->listing(['title' => 'Rice restricted', 'region' => '02']);

        $this->actingAs($seeker)->get(route('seeker.listings.index', ['q' => 'Rice', 'region' => '01', 'type' => 'training']))
            ->assertOk()->assertSee('Rice training')->assertDontSee('Rice restricted')
            ->assertViewHas('counts', fn ($counts) => $counts['all'] === 2 && $counts['ayuda'] === 1 && $counts['training'] === 1)
            ->assertViewHas('listings', fn ($rows) => $rows->total() === 1);
    }

    public function test_home_has_no_300_row_cutoff_and_excludes_applied_and_non_open_listings(): void
    {
        $seeker = User::factory()->create(['role' => 'seeker']);
        $seeker->seekerProfile()->create(['sectors' => ['pwd'], 'income_monthly' => 0]);
        $owner = User::factory()->create(['role' => 'poster', 'poster_status' => 'approved']);
        $best = $this->listing(['sectors' => ['pwd'], 'income_max' => 0, 'published_at' => '2026-01-01'], $owner);
        $rows = [];
        for ($i = 0; $i < 301; $i++) {
            $rows[] = ['user_id' => $owner->id, 'title' => 'Recent listing', 'description' => 'Support',
                'type' => 'ayuda', 'status' => 'published', 'published_at' => '2026-11-01'];
        }
        DB::table('listings')->insert($rows);
        $applied = $this->listing(['sectors' => ['pwd'], 'income_max' => 0], $owner);
        $applied->applications()->create(['user_id' => $seeker->id]);
        $this->listing(['status' => 'draft', 'sectors' => ['pwd'], 'income_max' => 0], $owner);
        $this->listing(['deadline' => '2026-11-12', 'sectors' => ['pwd'], 'income_max' => 0], $owner);

        $this->actingAs($seeker)->get(route('seeker.home'))->assertOk()
            ->assertViewHas('recommended', fn ($rows) => $rows->count() === 6 && $rows->first()->id === $best->id && ! $rows->contains('id', $applied->id));
    }

    public function test_poster_sees_applicant_match_counts_including_missing_profiles(): void
    {
        $listing = $this->listing(['sectors' => ['pwd'], 'income_max' => 10000, 'age_min' => 60]);
        $seeker = User::factory()->create(['role' => 'seeker']);
        $seeker->seekerProfile()->create(['sectors' => ['pwd'], 'income_monthly' => 5000]);
        $listing->applications()->create(['user_id' => $seeker->id]);
        $missingProfile = User::factory()->create(['role' => 'seeker']);
        $listing->applications()->create(['user_id' => $missingProfile->id]);

        $this->actingAs($listing->user)->get(route('poster.listings.applications', $listing))->assertOk()
            ->assertSee('Matches 2 of 3 requirements')->assertSee('Matches 0 of 3 requirements');
    }

    public static function unauthorizedApplicants(): array
    {
        return [
            'other approved poster' => ['poster', 'approved'],
            'unapproved owner' => ['poster', 'pending'],
            'seeker' => ['seeker', null],
            'admin' => ['admin', null],
        ];
    }

    #[DataProvider('unauthorizedApplicants')]
    public function test_applicants_are_hidden_from_unauthorized_users(string $role, ?string $status): void
    {
        $user = User::factory()->create(['role' => $role, 'poster_status' => $status]);
        $listing = $this->listing([], $status === 'pending' ? $user : null);

        $this->actingAs($user)->get(route('poster.listings.applications', $listing))->assertNotFound();
    }

    public function test_wrong_role_cannot_open_seeker_listing(): void
    {
        $listing = $this->listing();

        $this->actingAs($listing->user)->get(route('seeker.listings.show', $listing))->assertNotFound();
    }

    public function test_profile_changes_do_not_remove_my_applications(): void
    {
        $seeker = User::factory()->create(['role' => 'seeker']);
        $listing = $this->listing(['sectors' => ['pwd']]);
        $listing->applications()->create(['user_id' => $seeker->id]);

        $this->actingAs($seeker)->get(route('seeker.applications.index'))->assertOk()->assertSee($listing->title);
    }

    public function test_seven_criteria_count_once_each_including_location_and_age_range(): void
    {
        $seeker = User::factory()->create(['role' => 'seeker']);
        $seeker->seekerProfile()->create([
            'region' => '01', 'province' => '010', 'city' => '0101',
            'year_level' => 'college1', 'school_type' => 'public', 'course' => 'Nursing',
            'sectors' => ['pwd', 'ip'], 'birthdate' => '2000-01-01', 'income_monthly' => 0,
        ]);
        $listing = $this->listing([
            'region' => '01', 'province' => '010', 'city' => '0101',
            'year_levels' => ['college1'], 'school_types' => ['public'], 'courses' => ['Nursing'],
            'sectors' => ['pwd', 'ip'], 'age_min' => 18, 'age_max' => 30, 'income_max' => 10000,
        ]);

        $this->actingAs($seeker)->get(route('seeker.listings.show', $listing))->assertOk()
            ->assertViewHas('listing', fn ($row) => (int) $row->match_score === 7 && (int) $row->requirement_count === 7
                && count($row->match_reasons) === 7);
    }

    public function test_search_treats_wildcards_as_literal_text_and_sort_is_allowlisted(): void
    {
        $seeker = User::factory()->create(['role' => 'seeker']);
        $this->listing(['title' => '100%_support']);
        $this->listing(['title' => '100 extra support']);

        $this->actingAs($seeker)->get(route('seeker.listings.index', ['q' => '%_', 'sort' => 'id desc; drop table listings']))
            ->assertOk()->assertSee('100%_support')->assertDontSee('100 extra support')
            ->assertViewHas('sort', 'relevance')->assertViewHas('listings', fn ($rows) => $rows->total() === 1);
        $this->assertDatabaseCount('listings', 2);
    }

    public function test_listing_text_remains_escaped(): void
    {
        $seeker = User::factory()->create(['role' => 'seeker']);
        $this->listing(['title' => '<script>alert(1)</script>']);

        $this->actingAs($seeker)->get(route('seeker.listings.index'))->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $listing = $this->listing();

        $this->get(route('seeker.listings.show', $listing))->assertRedirect(route('login'));
    }
}
