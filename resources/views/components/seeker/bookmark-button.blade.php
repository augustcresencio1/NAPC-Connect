@props(['listing'])

<form method="POST" action="{{ route($listing->is_saved ? 'seeker.saved.destroy' : 'seeker.saved.store', $listing) }}">
    @csrf
    @if ($listing->is_saved)
        @method('DELETE')
    @endif
    <button type="submit" class="bookmark-button" aria-pressed="{{ $listing->is_saved ? 'true' : 'false' }}"
        aria-label="{{ $listing->is_saved ? __('Remove from saved') : __('Save listing') }}: {{ $listing->title }}">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z" /></svg>
        <span>{{ $listing->is_saved ? __('Saved') : __('Save') }}</span>
    </button>
</form>
