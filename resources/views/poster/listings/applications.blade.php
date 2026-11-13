@extends('layouts.app')
@section('title', 'Applicants · NAPC Connect')

@section('navbar')
    <x-navbar.poster />
@endsection

@php
    $statusOptions = [
        'pending' => 'Pending (' . $counts['pending'] . ')',
        'accepted' => 'Accepted (' . $counts['accepted'] . ')',
        'rejected' => 'Rejected (' . $counts['rejected'] . ')',
        'withdrawn' => 'Withdrawn (' . $counts['withdrawn'] . ')',
    ];
@endphp

@section('content')
    <x-page-header title="Applicants" :subtitle="$listing->title">
        <x-slot:actions>
            <a href="{{ route('poster.listings.edit', $listing) }}" class="btn-ghost border-2 border-[var(--navy)]">Edit
                listing</a>
            <a href="{{ route('poster.listings.index') }}" class="btn-ghost border-2 border-[var(--navy)]">Back to
                listings</a>
        </x-slot:actions>
    </x-page-header>

    <div class="container-page space-y-6 py-10">

        @if ($errors->has('status'))
            <x-alert type="danger">{{ $errors->first('status') }}</x-alert>
        @endif

        @if ($listing->apply_mode === 'form')
            @php $availability = $listing->availability(); @endphp
            <p class="muted font-semibold">{{ $availability['label'] }} · {{ $availability['accepted'] }} accepted</p>
            @if ($availability['remaining'] === 0)
                <p class="muted">All slots are filled. Edit the listing to increase its limit, or move an accepted application back to pending to free a slot.</p>
            @endif
            <div class="applicant-toolbar">
                <x-filter-tabs :options="$statusOptions" :current="$status" />
                <a href="{{ route('poster.applications.export', $listing) }}" class="btn-primary btn-sm">Download accepted (CSV)</a>
            </div>
        @endif

        @if ($listing->apply_mode === 'link')
            <x-alert type="warning">This listing sends applicants to your own form, so their answers are not stored
                here.</x-alert>
        @elseif ($listing->apply_mode === 'info')
            <x-alert type="warning">This listing has no online application. Applicants follow your instructions
                instead.</x-alert>
        @endif

        <p class="muted font-semibold">
            {{ $applications->total() }} {{ \Illuminate\Support\Str::plural('application', $applications->total()) }}
        </p>

        @forelse ($applications as $application)
            @php
                $profileRows = $application->user->seekerProfile?->detailRows($listing->income_max !== null) ?? [];
            @endphp

            <article class="card-lg border-2 border-[var(--navy)]">
                <span class="badge-muted">{{ $application->status === 'rejected' ? 'Rejected' : $application->statusLabel() }}</span>
                @php $match = $matchScores->get($application->id); @endphp
                <p class="label">
                    @if ((int) $match->requirement_count === 0)
                        Open to everyone — no targeting requirements set
                    @else
                        Matches {{ $match->match_score }} of {{ $match->requirement_count }} requirements
                    @endif
                </p>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex min-w-0 items-center gap-3">
                        <span
                            class="avatar shrink-0">{{ mb_strtoupper(mb_substr($application->user->first_name, 0, 1)) }}</span>
                        <div class="min-w-0">
                            <h3 class="h3 truncate">{{ $application->user->name }}</h3>
                            <p class="muted truncate">{{ $application->user->email }}</p>
                        </div>
                    </div>
                    <span class="muted font-semibold">Applied {{ $application->created_at->format('M j, Y') }}</span>
                </div>

                @if ($profileRows)
                    <p class="label mt-5">Profile</p>
                    <dl class="detail-list">
                        @foreach ($profileRows as $label => $value)
                            <div>
                                <dt>{{ $label }}</dt>
                                <dd>{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif

                @if (!empty($listing->application_form))
                    <p class="label mt-5">Answers</p>
                    <dl class="detail-list">
                        @foreach ($listing->application_form as $question)
                            @php $answer = $application->answers[$question['key']] ?? null; @endphp
                            <div class="{{ in_array($question['type'], ['textarea'], true) ? 'sm:col-span-2' : '' }}">
                                <dt>{{ $question['label'] }}</dt>
                                <dd>{{ is_array($answer) ? implode(', ', $answer) : ($answer !== null && $answer !== '' ? $answer : 'No answer') }}
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                @endif

                @if ($listing->apply_mode === 'form' && $application->status !== 'withdrawn')
                    <div class="application-decisions">
                        @if ($application->status === 'pending' && !$application->reviewed_at)
                            <form method="POST" action="{{ route('poster.applications.review', [$listing, $application]) }}"
                                x-data="confirmForm" data-confirm="Mark this application as reviewed?" @submit="submit($event)">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn-ghost btn-sm">Mark reviewed</button>
                            </form>
                        @endif
                        @foreach (['accepted' => 'Accept', 'rejected' => 'Reject', 'pending' => 'Move to pending'] as $nextStatus => $label)
                            @if ($application->status !== $nextStatus)
                                <form method="POST" action="{{ route('poster.applications.update', [$listing, $application]) }}"
                                    x-data="confirmForm" data-confirm="{{ $nextStatus === 'accepted' ? 'Accept this application and issue a ticket?' : 'Change this application status? Any current ticket will be disabled.' }}"
                                    @submit="submit($event)">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="{{ $nextStatus }}">
                                    <button type="submit" class="{{ $nextStatus === 'accepted' ? 'btn-primary' : ($nextStatus === 'rejected' ? 'btn-danger' : 'btn-ghost') }} btn-sm"
                                        @disabled($nextStatus === 'accepted' && $availability['remaining'] === 0)>{{ $label }}</button>
                                </form>
                            @endif
                        @endforeach
                    </div>
                    @if ($application->hasTicket())
                        <p class="muted mt-4">Ticket {{ $application->ticket_code }} · Accepted {{ $application->accepted_at->format('M j, Y') }}</p>
                    @endif
                @endif
            </article>
        @empty
            <x-empty-state title="No applications in this tab">Applications with this status will appear here.</x-empty-state>
        @endforelse

        {{ $applications->links('pagination.brand') }}
    </div>
@endsection
