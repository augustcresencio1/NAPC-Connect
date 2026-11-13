{{-- One listing with its matching reasons, shared by browse and home. --}}
@props(['listing'])

<article class="card-lg flex h-full flex-col border-2 border-[var(--navy)]">
    <div class="listing-card-heading">
        <span class="badge-{{ $listing->type }} self-start">{{ $listing->typeLabel() }}</span>
        <x-seeker.bookmark-button :listing="$listing" />
    </div>

    <h3 class="h3 mt-3 break-words">
        <a href="{{ route('seeker.listings.show', $listing) }}" class="hover:underline">{{ $listing->title }}</a>
    </h3>

    @if ($listing->user?->organization)
        <p class="muted mt-1 font-semibold">{{ $listing->user->organization }}</p>
    @endif

    <p class="mt-3 text-sm">{{ \Illuminate\Support\Str::limit(strip_tags($listing->description), 140) }}</p>

    <x-seeker.match-reasons :listing="$listing" />
    <x-seeker.slot-availability :listing="$listing" />

    <dl class="detail-list mt-4">
        <div>
            <dt>{{ __('Available in') }}</dt>
            <dd>{{ $listing->locationLabel() }}</dd>
        </div>
        <div>
            <dt>{{ __('Deadline') }}</dt>
            <dd>{{ $listing->deadlineLabel() }}</dd>
        </div>
        @if ($listing->type === 'job' && !empty($listing->details['salary']))
            <div>
                <dt>{{ __('Salary') }}</dt>
                <dd>{{ $listing->details['salary'] }}</dd>
            </div>
        @endif
    </dl>

    <div class="mt-auto pt-6">
        <a href="{{ route('seeker.listings.show', $listing) }}" class="btn-primary btn-sm w-full sm:w-auto">{{ __('View details') }}</a>
    </div>
</article>
