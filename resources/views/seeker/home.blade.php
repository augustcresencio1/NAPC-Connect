@extends('layouts.app')
@section('title', __('My home') . ' | NAPC Connect')


@section('navbar')
    <x-navbar.seeker />
@endsection
@section('content')
    <x-page-header :title="'Kumusta, ' . auth()->user()->first_name" :subtitle="__('Find ayuda, scholarships, jobs and training that fit you.')" />

    <div class="section space-y-12">

        {{-- Profile prompt --}}
        <div class="card-lg flex flex-col gap-5 bg-[var(--mist)] md:flex-row md:items-center md:justify-between">
            <div class="max-w-2xl">
                <h2 class="h3">{{ __($completion >= 100 ? 'Your profile is complete' : 'Get suggestions made for you') }}
                </h2>
                <p class="lead mt-2 text-base">{{ __('Add your year level, school, location and sector. We use them to show listings matching at least one of your details, with a short reason for each match.') }}</p>
                @unless (auth()->user()->seekerProfile)
                    <p class="muted mt-2">{{ __('Until you complete your profile, only listings open to everyone appear.') }}</p>
                @endunless
            </div>
            <a href="{{ route('seeker.profile.edit') }}" class="btn-primary shrink-0">
                @if ($completion === 0)
                    {{ __('Set up profile') }}
                @elseif ($completion >= 100)
                    {{ __('Edit profile') }}
                @else
                    {{ __('Finish profile (:percent%)', ['percent' => $completion]) }}
                @endif
            </a>
        </div>

        {{-- Recommendations --}}
        <section>
            <h2 class="h2">{{ __('Closing soon') }}</h2>
            <p class="muted">{{ __('Listings that fit you and close within the next 3 days.') }}</p>
            <div class="listing-grid mt-6">
                @forelse ($closingSoon as $listing)
                    <x-seeker.listing-card :listing="$listing" />
                @empty
                    <p class="muted">{{ __('No matching deadlines in the next 3 days.') }}</p>
                @endforelse
            </div>
        </section>

        <section>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="h2">{{ __('Recommended for you') }}</h2>
                @if ($recommended->isNotEmpty())
                    <a href="{{ route('seeker.listings.index') }}" class="font-bold underline">{{ __('Browse all listings') }}</a>
                @endif
            </div>

            @if ($recommended->isNotEmpty())
                <div class="mt-6 grid gap-6 md:grid-cols-2">
                    @foreach ($recommended as $listing)
                        <x-seeker.listing-card :listing="$listing" />
                    @endforeach
                </div>
            @else
                <div class="card-lg mt-6 border-2 border-dashed border-[var(--navy)]/30 text-center">
                    @if ($completion === 0)
                        <p class="h3">{{ __('Your suggestions will show up here') }}</p>
                        <p class="lead mx-auto mt-2 text-base">{{ __('Set up your profile and the best matches appear first.') }}</p>
                    @else
                        <p class="h3">{{ __('No matches yet') }}</p>
                        <p class="lead mx-auto mt-2 text-base">{{ __('Nothing open fits your profile right now. Check back soon, or update your profile.') }}</p>
                    @endif
                </div>
            @endif
        </section>

        {{-- Browse --}}
        <section>
            <h2 class="h2">{{ __('Browse by category') }}</h2>
            <div class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
                <a href="{{ route('seeker.listings.index', ['type' => 'ayuda']) }}"
                    class="cat-card cat-ayuda !min-h-[120px]"><span class="text-xl font-extrabold">{{ __('Ayuda') }}</span></a>
                <a href="{{ route('seeker.listings.index', ['type' => 'scholarship']) }}"
                    class="cat-card cat-scholarship !min-h-[120px]"><span
                        class="text-xl font-extrabold">{{ __('Scholarships') }}</span></a>
                <a href="{{ route('seeker.listings.index', ['type' => 'job']) }}"
                    class="cat-card cat-job !min-h-[120px]"><span class="text-xl font-extrabold">{{ __('Jobs') }}</span></a>
                <a href="{{ route('seeker.listings.index', ['type' => 'training']) }}"
                    class="cat-card cat-training !min-h-[120px]"><span class="text-xl font-extrabold">{{ __('Training') }}</span></a>
            </div>
        </section>
    </div>
@endsection
