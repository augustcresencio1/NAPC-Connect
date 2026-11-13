@extends('layouts.app')
@section('title', __('Deadline reminders') . ' | NAPC Connect')
@section('navbar')<x-navbar.seeker />@endsection
@section('content')
    <x-page-header :title="__('Deadline reminders')" :subtitle="__('Saved listings closing within the next 3 days, including today.')" />
    <div class="container-page seeker-tools-page">
        @forelse ($reminders as $listing)
            @php $isRead = $listing->reminder_read_deadline === $listing->deadline->toDateString(); @endphp
            <article class="card-lg reminder-card">
                <span class="badge-muted">{{ $isRead ? __('Read') : __('New reminder') }}</span>
                <h2 class="h3"><a href="{{ route('seeker.listings.show', $listing) }}">{{ $listing->title }}</a></h2>
                <p>{{ $listing->deadlineLabel() }}</p>
                <p class="muted">{{ $listing->user?->organization }}</p>
                <div class="seeker-actions">
                    <a href="{{ route('seeker.listings.show', $listing) }}" class="btn-primary btn-sm">{{ __('View listing') }}</a>
                    @unless ($isRead)
                        <form method="POST" action="{{ route('seeker.reminders.update', $listing) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="btn-ghost btn-sm">{{ __('Mark as read') }}</button>
                        </form>
                    @endunless
                </div>
            </article>
        @empty
            <x-empty-state :title="__('No deadline reminders')">{{ __('Save listings to receive reminders here as their deadlines approach.') }}</x-empty-state>
        @endforelse
        {{ $reminders->links('pagination.brand') }}
    </div>
@endsection
