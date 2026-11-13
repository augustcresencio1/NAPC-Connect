@extends('layouts.app')
@section('title', __('Saved listings') . ' | NAPC Connect')
@section('navbar')<x-navbar.seeker />@endsection
@section('content')
    <x-page-header :title="__('Saved listings')" :subtitle="__('Keep the opportunities you want to come back to.')" />
    <div class="container-page seeker-tools-page">
        <p class="muted">{{ __('Only published listings that fit your current profile appear here.') }}</p>
        <div class="listing-grid">
            @forelse ($listings as $listing)
                <x-seeker.listing-card :listing="$listing" />
            @empty
                <x-empty-state :title="__('No saved listings yet')">{{ __('Tap the heart on a listing to save it here.') }}</x-empty-state>
            @endforelse
        </div>
        {{ $listings->links('pagination.brand') }}
    </div>
@endsection
