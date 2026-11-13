@extends('layouts.app')
@section('title', __('My applications') . ' | NAPC Connect')

@section('navbar')
    <x-navbar.seeker />
@endsection

@section('content')
    <x-page-header :title="__('My applications')" :subtitle="__('Everything you applied for through NAPC Connect.')">
        <x-slot:actions>
            <a href="{{ route('seeker.listings.index') }}" class="btn-accent">{{ __('Browse listings') }}</a>
        </x-slot:actions>
    </x-page-header>

    <div class="container-page space-y-6 py-10">

        <p class="muted font-semibold">
            {{ $applications->total() }}
            {{ \Illuminate\Support\Str::plural('application', $applications->total()) }}
        </p>

        <div class="space-y-6">
            @forelse ($applications as $application)
                @php $listing = $application->listing; @endphp

                <article class="card-lg border-2 border-[var(--navy)]">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="badge-{{ $listing->type }}">{{ $listing->typeLabel() }}</span>
                        <span class="badge-muted">{{ $application->statusLabel() }}</span>
                    </div>

                    <h3 class="h3 mt-3 break-words">
                        <a href="{{ route('seeker.listings.show', $listing) }}"
                            class="hover:underline">{{ $listing->title }}</a>
                    </h3>

                    @if ($listing->user?->organization)
                        <p class="muted mt-1 font-semibold">{{ $listing->user->organization }}</p>
                    @endif

                    <dl class="detail-list mt-4">
                        <div>
                            <dt>{{ __('Applied on') }}</dt>
                            <dd>{{ $application->created_at->format('M j, Y') }}</dd>
                        </div>
                        <div>
                            <dt>{{ __('Listing') }}</dt>
                            <dd>{{ __($listing->status === 'published' && !$listing->isExpired() ? 'Still open' : 'Closed') }}
                            </dd>
                        </div>
                    </dl>

                    <x-seeker.application-timeline :application="$application" />

                    <div class="mt-6 seeker-actions">
                        @if ($application->status === 'pending')
                            <form method="POST" action="{{ route('seeker.applications.withdraw', $application) }}"
                                x-data="confirmForm" data-confirm="{{ __('Withdraw this application? This cannot be undone.') }}" @submit="submit($event)">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn-danger btn-sm">{{ __('Withdraw application') }}</button>
                            </form>
                        @endif
                        @if ($listing->apply_mode === 'form' && $application->hasTicket())
                            <a href="{{ route('seeker.applications.ticket', $application) }}"
                                class="btn-primary btn-sm">{{ __('Download ticket (PDF)') }}</a>
                        @endif
                        <a href="{{ route('seeker.listings.show', $listing) }}"
                            class="btn-ghost btn-sm border-2 border-[var(--navy)]">{{ __('View listing') }}</a>
                    </div>
                </article>
            @empty
                <x-empty-state :title="__('No applications yet')">{{ __('Applications you send through an NAPC Connect form show up here.') }}</x-empty-state>
            @endforelse
        </div>

        {{ $applications->links('pagination.brand') }}
    </div>
@endsection
