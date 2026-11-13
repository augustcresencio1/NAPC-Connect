@props(['listing'])

@if ($listing->apply_mode === 'form')
    @php $availability = $listing->availability(); @endphp
    <div class="slot-availability" x-data="slotAvailability"
        data-listing-id="{{ $listing->id }}" data-initial="{{ json_encode($availability) }}"
        data-url="{{ route('seeker.listings.availability') }}"
        data-unavailable-label="{{ __('Listing unavailable') }}">
        <p class="label">{{ __('Acceptance slots') }}</p>
        <p aria-live="polite" aria-atomic="true" x-text="label">{{ $availability['label'] }}</p>
        <p class="muted" x-show="$store.listingAvailability.stale" x-cloak>{{ __('Live updates paused. Retrying automatically.') }}</p>
    </div>
@endif
