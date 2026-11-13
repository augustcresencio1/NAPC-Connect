@php
    $unreadReminders = app(\App\Services\Seeker\ListingStateService::class)->unreadCount(auth()->user());
    $links = [
        ['label' => __('My home'), 'href' => route('seeker.home'), 'active' => request()->routeIs('seeker.home')],
        ['label' => __('Browse'), 'href' => route('seeker.listings.index'), 'active' => request()->routeIs('seeker.listings.*')],
        ['label' => __('Saved'), 'href' => route('seeker.saved.index'), 'active' => request()->routeIs('seeker.saved.*')],
        ['label' => __('Applications'), 'href' => route('seeker.applications.index'), 'active' => request()->routeIs('seeker.applications.*')],
        ['label' => __('Reminders') . ($unreadReminders ? ' (' . $unreadReminders . ')' : ''), 'href' => route('seeker.reminders.index'), 'active' => request()->routeIs('seeker.reminders.*')],
        ['label' => __('Profile'), 'href' => route('seeker.profile.edit'), 'active' => request()->routeIs('seeker.profile.*')],
    ];
@endphp
<x-navbar.shell :links="$links" />
