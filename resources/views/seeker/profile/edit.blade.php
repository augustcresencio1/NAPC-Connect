@extends('layouts.app')
@section('title', __('My profile') . ' | NAPC Connect')

@section('navbar')
    <x-navbar.seeker />
@endsection

@php
    $config = [
        'yearLevel' => old('year_level', $profile->year_level),
    ];
@endphp

@section('content')
    <x-page-header :title="__('My profile')"
        :subtitle="__('We use this to suggest programs you can actually apply for. Everything is optional.')">
        <x-slot:actions>
            <a href="{{ route('seeker.home') }}" class="btn-ghost border-2 border-[var(--navy)]">{{ __('Back to home') }}</a>
        </x-slot:actions>
    </x-page-header>

    <div class="container-page py-10">
        <div class="mx-auto max-w-3xl">
            <form method="POST" action="{{ route('seeker.profile.update') }}" class="space-y-8" x-data="profileForm"
                data-config="{{ json_encode($config) }}">
                @csrf
                @method('PUT')

                @if ($errors->any())
                    <x-alert type="danger">
                        <p class="font-extrabold">{{ __('Please fix these first:') }}</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5">
                            @foreach ($errors->all() as $message)
                                <li>{{ $message }}</li>
                            @endforeach
                        </ul>
                    </x-alert>
                @endif

                {{-- 1. ABOUT YOU --}}
                <x-seeker.form-section number="1" :title="__('About you')">
                    <div class="grid gap-6 sm:grid-cols-2">
                        <x-field name="birthdate" type="date" :label="__('Birthdate')" :value="$profile->birthdate?->format('Y-m-d')"
                            :hint="__('Used to match age limits. Organizations you apply to see your age, not your birthdate.')" />
                        <x-field name="income_monthly" type="number" :label="__('Monthly household income (PHP)')"
                            :value="$profile->income_monthly" min="0"
                            :hint="__('For programs with an income limit. Organizations only see it when their listing has one.')" />
                    </div>

                    <x-seeker.check-group name="sectors" :label="__('Do any of these describe you?')" :options="config('napc.sectors')"
                        :selected="$profile->sectors ?? []" :hint="__('Tick all that apply. Leave empty if none.')" />
                </x-seeker.form-section>

                {{-- 2. LOCATION --}}
                <x-seeker.form-section number="2" :title="__('Where you live')"
                    :hint="__('We use this to show programs available where you live.')">
                    <x-location-picker :region="old('region', $profile->region)" :province="old('province', $profile->province)" :city="old('city', $profile->city)"
                        :city-label="__('City or municipality')" />
                </x-seeker.form-section>

                {{-- 3. SCHOOL --}}
                <x-seeker.form-section number="3" :title="__('Your schooling')">
                    <div>
                        <label for="year_level" class="label">{{ __('Current year level') }}</label>
                        <select id="year_level" name="year_level" class="input" x-model="yearLevel">
                            <option value="">{{ __('Choose one') }}</option>
                            <option value="none">{{ __('Not currently in school') }}</option>
                            @foreach (config('napc.year_levels') as $value => $text)
                                <option value="{{ $value }}">{{ __($text) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div x-show="isStudent" x-cloak class="space-y-6">
                        <div class="grid gap-6 sm:grid-cols-2">
                            <div>
                                <label for="school_type" class="label">{{ __('School type') }}</label>
                                <select id="school_type" name="school_type" class="input">
                                    <option value="">{{ __('Choose one') }}</option>
                                    @foreach (config('napc.school_types') as $value => $text)
                                        <option value="{{ $value }}" @selected(old('school_type', $profile->school_type) === $value)>
                                            {{ __($text) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <x-field name="school_name" :label="__('School name')" :value="$profile->school_name" maxlength="150"
                                placeholder="e.g. Batangas State University" />
                        </div>

                        <x-field name="course" :label="__('Course, strand or field')" :value="$profile->course" maxlength="100"
                            placeholder="e.g. BSIT, BS Nursing or STEM" />
                    </div>
                </x-seeker.form-section>

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <a href="{{ route('seeker.home') }}" class="btn-ghost">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn-accent">{{ __('Save profile') }}</button>
                </div>
            </form>
        </div>
    </div>
@endsection
