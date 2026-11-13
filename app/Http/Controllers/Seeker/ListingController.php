<?php

namespace App\Http\Controllers\Seeker;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Listing;
use App\Models\Province;
use App\Services\RecommendationService;
use App\Services\Seeker\ListingStateService;
use App\Support\Locations;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ListingController extends Controller
{
    private const SORTS = ['relevance', 'latest', 'deadline'];

    public function index(Request $request, RecommendationService $recommendations): View
    {
        $types = config('napc.types');

        $type = array_key_exists((string) $request->query('type'), $types) ? $request->query('type') : 'all';
        $sort = in_array($request->query('sort'), self::SORTS, true) ? $request->query('sort') : 'relevance';
        $search = trim((string) $request->query('q', ''));

        // Location filter. Each level only counts if it belongs to the one above it.
        $regions = Locations::regions();
        $region = array_key_exists((string) $request->query('region'), $regions) ? (string) $request->query('region') : '';

        $province = $region !== '' && Province::where('code', (string) $request->query('province'))
            ->where('region_code', $region)->exists()
            ? (string) $request->query('province') : '';

        $city = $region !== '' && City::where('code', (string) $request->query('city'))
            ->where('region_code', $region)
            ->when($province !== '', fn ($q) => $q->where(
                fn ($w) => $w->where('province_code', $province)->orWhereNull('province_code')
            ))
            ->exists()
            ? (string) $request->query('city') : '';

        $area = match (true) {
            $city !== '' => Locations::city($city),
            $province !== '' => Locations::province($province),
            $region !== '' => $regions[$region],
            default => null,
        };

        $filtered = $recommendations->visibleTo($request->user())->open()
            ->when($search !== '', function ($q) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';

                $q->where(fn ($w) => $w->whereRaw("LOWER(title) LIKE LOWER(?) ESCAPE '\\'", [$like])
                    ->orWhereRaw("LOWER(description) LIKE LOWER(?) ESCAPE '\\'", [$like]));
            })
            // Nationwide listings fit everyone. A narrower listing only shows if it covers the chosen place.
            ->when($region !== '', function ($q) use ($region, $province, $city) {
                $q->where(function ($w) use ($region, $province, $city) {
                    $w->whereNull('region')
                        ->orWhere(function ($in) use ($region, $province, $city) {
                            $in->where('region', $region)
                                ->when($province !== '', fn ($p) => $p->where(
                                    fn ($x) => $x->whereNull('province')->orWhere('province', $province)
                                ))
                                ->when($city !== '', fn ($c) => $c->where(
                                    fn ($x) => $x->whereNull('city')->orWhere('city', $city)
                                ));
                        });
                });
            });

        $byType = (clone $filtered)
            ->select([])
            ->selectRaw('type, count(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $counts = collect($types)->map(fn ($label, $key) => (int) ($byType[$key] ?? 0))->all();
        $counts['all'] = array_sum($counts);

        $query = (clone $filtered)->with('user:id,organization')
            ->when($type !== 'all', fn ($q) => $q->where('type', $type));
        if ($sort === 'relevance') {
            $recommendations->ranked($query);
        } elseif ($sort === 'deadline') {
            $query->orderByRaw('deadline asc nulls last')->latest('published_at')->orderBy('id');
        } else {
            $query->latest('published_at')->orderBy('id');
        }
        $listings = $query->paginate(9)->withQueryString();
        $listings->getCollection()->each(fn (Listing $listing) => $recommendations->explain($listing));

        return view('seeker.listings.index', compact(
            'listings',
            'counts',
            'type',
            'region',
            'province',
            'city',
            'area',
            'sort',
            'search'
        ));
    }

    public function show(Request $request, Listing $listing, RecommendationService $recommendations, ListingStateService $states): View
    {
        $listing = $recommendations->visibleTo($request->user())
            ->where('status', 'published')->whereKey($listing->id)->firstOrFail();
        $recommendations->explain($listing);

        $listing->load('user:id,organization');

        $application = $listing->applications()
            ->where('user_id', $request->user()->id)
            ->first();
        $applied = $application !== null;
        $checkedDocuments = json_decode($states->state($request->user(), $listing)?->checked_documents ?? '[]', true) ?? [];

        return view(
            'seeker.listings.show',
            compact('listing', 'applied', 'application', 'checkedDocuments')
        );
    }
}
