@props(['listing'])

@if (!empty($listing->match_reasons))
    <div class="match-reasons">
        <p class="label">{{ __('Why this matches you') }}</p>
        <ul>
            @foreach ($listing->match_reasons as $reason)
                <li>{{ $reason }}</li>
            @endforeach
        </ul>
    </div>
@endif
