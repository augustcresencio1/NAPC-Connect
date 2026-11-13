{{-- The "How to apply" box on a listing page. Needs: $listing, $applied --}}
@props(['listing', 'applied' => false, 'application' => null])

<aside class="card-lg space-y-4 border-2 border-[var(--navy)]">
    <h2 class="h3">{{ __('How to apply') }}</h2>
    <p class="muted font-semibold">{{ $listing->deadlineLabel() }}</p>
    <x-seeker.slot-availability :listing="$listing" />

    @if ($listing->apply_mode === 'form' && $applied)
        <span class="badge-approved">{{ __('You already applied') }}</span>
        @if ($application)
            <p class="label">{{ __('Status: :status', ['status' => $application->statusLabel()]) }}</p>
            @if ($application->hasTicket())
                <a href="{{ route('seeker.applications.ticket', $application) }}" class="btn-primary w-full">{{ __('Download ticket (PDF)') }}</a>
            @endif
        @endif
        <a href="{{ route('seeker.applications.index') }}" class="btn-ghost w-full border-2 border-[var(--navy)]">{{ __('View my applications') }}</a>
    @elseif ($listing->isExpired())
        <x-alert type="warning">{{ __('The deadline for this listing has passed.') }}</x-alert>
    @else
        @if ($listing->apply_mode === 'form')
            <a href="{{ route('seeker.applications.create', $listing) }}" class="btn-accent w-full">{{ __('Apply now') }}</a>
        @elseif ($listing->apply_mode === 'link')
            <a href="{{ $listing->apply_url }}" target="_blank" rel="noopener noreferrer" class="btn-accent w-full">{{ __('Open the application form') }}</a>
            <p class="muted">{{ __('This opens the organization’s own form in a new tab.') }}</p>
        @endif

        @if ($listing->how_to_apply)
            <div>
                <h3 class="label">{{ __($listing->apply_mode === 'info' ? 'Instructions' : 'Before you apply') }}</h3>
                <p class="text-block">{{ $listing->how_to_apply }}</p>
            </div>
        @endif
    @endif

    @if ($listing->apply_mode !== 'form')
        <p class="muted">{{ __('NAPC Connect tickets are only available for accepted applications submitted through this site.') }}</p>
    @endif
</aside>
