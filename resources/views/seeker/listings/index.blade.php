@extends('layouts.app')
@section('title', __('Browse listings') . ' | NAPC Connect')

@section('navbar')
    <x-navbar.seeker />
@endsection

@php
    $options = ['all' => __('All') . ' (' . $counts['all'] . ')'];
    foreach (config('napc.types') as $key => $label) {
        $options[$key] = __($label) . ' (' . $counts[$key] . ')';
    }

    $hasFilters = $search !== '' || $region !== '' || $sort !== 'relevance';
@endphp

@section('content')
    <x-page-header :title="__('Browse listings')" :subtitle="__('Ayuda, scholarships, jobs and training you can apply for.')" />

    <div class="container-page space-y-6 py-10">

        @unless (auth()->user()->seekerProfile)
            <x-alert type="warning">{{ __('You are seeing listings open to everyone.') }} <a href="{{ route('seeker.profile.edit') }}" class="font-bold underline">{{ __('Complete your profile') }}</a> {{ __('to see more matches.') }}</x-alert>
        @endunless

        <x-filter-tabs :options="$options" :current="$type" param="type" />

        <form method="GET" action="{{ route('seeker.listings.index') }}" class="space-y-4">
            @if ($type !== 'all')
                <input type="hidden" name="type" value="{{ $type }}">
            @endif

            {{-- Search and sort --}}
            <div class="browse-filter">
                <div>
                    <label for="q" class="label">{{ __('Search') }}</label>
                    <div class="relative">
                        <svg class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-[var(--navy)]"
                            width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="11" cy="11" r="7" />
                            <path d="m20 20-3.5-3.5" />
                        </svg>
                        <input id="q" type="search" name="q" value="{{ $search }}"
                            class="input !bg-none !pl-12" placeholder="{{ __('Title or keyword') }}">
                    </div>
                </div>

                <div>
                    <label for="sort" class="label">{{ __('Sort by') }}</label>
                    <select id="sort" name="sort" class="input">
                        <option value="relevance" @selected($sort === 'relevance')>{{ __('Best matches') }}</option>
                        <option value="latest" @selected($sort === 'latest')>{{ __('Newest') }}</option>
                        <option value="deadline" @selected($sort === 'deadline')>{{ __('Deadline soonest') }}</option>
                    </select>
                </div>
            </div>

            {{-- Location --}}
            <x-location-picker :region="$region" :province="$province" :city="$city" :columns="3" region-label="Region"
                :region-placeholder="__('Anywhere in the Philippines')" :province-placeholder="__('Any province')"
                :city-label="__('City or municipality')" :city-placeholder="__('Any city or municipality')" />

            {{-- Buttons --}}
            <div class="filter-actions">
                @if ($hasFilters)
                    <a href="{{ route('seeker.listings.index', $type !== 'all' ? ['type' => $type] : []) }}"
                        class="btn-ghost border-2 border-[var(--navy)]">{{ __('Clear') }}</a>
                @endif
                <button type="submit" class="btn-primary">{{ __('Search') }}</button>
            </div>
        </form>

        {{-- Result count --}}
        <p class="muted font-semibold">
            {{ __(':count listings', ['count' => $listings->total()]) }}
            @if ($search !== '')
                {{ __('for :search', ['search' => $search]) }}
            @endif
            @if ($area)
                {{ __('for :area (including nationwide and wider-area listings)', ['area' => $area]) }}
            @endif
        </p>

        {{-- Results --}}
        <div class="grid gap-6 md:grid-cols-2">
            @forelse ($listings as $listing)
                <x-seeker.listing-card :listing="$listing" />
            @empty
                <div class="md:col-span-2">
                    <x-empty-state :title="__('Nothing found')">
                        @if ($hasFilters || $type !== 'all')
                            {{ __('Try another category, a different keyword, or clear the filters.') }}
                        @else
                            {{ __('No listings are open right now. Please check back soon.') }}
                        @endif
                    </x-empty-state>
                </div>
            @endforelse
        </div>

        {{ $listings->links('pagination.brand') }}
    </div>
@endsection
