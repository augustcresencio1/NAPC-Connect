<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>NAPC Connect ticket</title>
    <style>{!! file_get_contents(resource_path('css/seeker/ticket.css')) !!}</style>
</head>
<body>
    <div class="header">
        <p class="brand">NAPC CONNECT</p>
        <p class="eyebrow">APPLICATION ACCEPTED</p>
        <h1>Your admission ticket</h1>
        <p>Keep a copy and bring it with you.</p>
    </div>
    <div class="listing">
        <p class="eyebrow">{{ $listing->typeLabel() }}</p>
        <h2>{{ $listing->title }}</h2>
        <p class="organization">{{ $listing->user?->organization }}</p>
    </div>
    <table class="identity">
        <tr>
            <td class="person">
                <p class="eyebrow">ISSUED TO</p>
                <h2>{{ $application->user->name }}</h2>
                <p class="eyebrow code-label">TICKET CODE</p>
                <p class="ticket-code">{{ $application->ticket_code }}</p>
                <p class="accepted">Accepted {{ $application->accepted_at->format('M j, Y') }}</p>
            </td>
            <td class="qr"><img src="{{ $qrCode }}" alt="QR code containing your ticket code"></td>
        </tr>
    </table>
    @php
        $eventDetails = array_filter([
            'Venue' => $listing->details['venue'] ?? null,
            'Schedule' => $listing->details['schedule'] ?? null,
            'What to bring' => $listing->details['documents'] ?? $listing->details['what_to_bring'] ?? null,
        ], fn ($value) => filled($value));
    @endphp
    @foreach ($eventDetails as $label => $value)
        <div class="event-detail">
            <h3>{{ $label }}</h3>
            <p>{{ $value }}</p>
        </div>
    @endforeach
    <div class="footer">
        This ticket belongs to the named applicant. Check My applications for your current status before attending.
    </div>
</body>
</html>
