@props(['application'])

<div class="application-tracker">
    <h4 class="label">{{ __('Application timeline') }}</h4>
    <ol class="application-timeline">
        @foreach ($application->timelineEntries() as $entry)
            <li>
                <span>{{ __(['submitted' => 'Submitted', 'pending' => 'Moved to pending', 'reviewed' => 'Reviewed', 'accepted' => 'Accepted', 'rejected' => 'Not selected', 'withdrawn' => 'Withdrawn'][$entry['event']] ?? $entry['event']) }}</span>
                <time datetime="{{ $entry['at'] }}">{{ \Carbon\Carbon::parse($entry['at'])->translatedFormat('M j, Y · g:i A') }}</time>
            </li>
        @endforeach
    </ol>
</div>
