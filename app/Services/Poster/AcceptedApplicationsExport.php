<?php

namespace App\Services\Poster;

use App\Models\Listing;
use App\Support\Locations;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AcceptedApplicationsExport
{
    public function download(Listing $listing): StreamedResponse
    {
        return response()->streamDownload(function () use ($listing): void {
            $stream = fopen('php://output', 'wb');
            fwrite($stream, "\xEF\xBB\xBF");
            $questions = $listing->application_form ?? [];
            $headers = ['Name', 'Email', 'Birthdate', 'Age', 'Region', 'Province', 'City or municipality',
                'Year level', 'School type', 'School name', 'Course', 'Sectors', 'Monthly household income (PHP)'];
            foreach ($questions as $question) {
                $headers[] = $question['label'];
            }
            $headers = [...$headers, 'Ticket code', 'Accepted at (ISO 8601)'];
            $this->writeRow($stream, $headers);

            $listing->applications()->where('status', 'accepted')->with('user.seekerProfile')
                ->chunkById(200, function ($applications) use ($stream, $questions): void {
                    foreach ($applications as $application) {
                        $profile = $application->user->seekerProfile;
                        $row = [
                            $application->user->name, $application->user->email,
                            $profile?->birthdate?->toDateString(), $profile?->age(),
                            Locations::region($profile?->region) ?: $profile?->region,
                            Locations::province($profile?->province) ?: $profile?->province,
                            Locations::city($profile?->city) ?: $profile?->city,
                            $profile?->year_level === 'none' ? 'Not currently in school' : config('napc.year_levels.'.$profile?->year_level, $profile?->year_level),
                            config('napc.school_types.'.$profile?->school_type, $profile?->school_type),
                            $profile?->school_name, $profile?->course,
                            collect($profile?->sectors ?? [])->map(fn (string $sector): string => config('napc.sectors.'.$sector, $sector))->implode('; '),
                            $profile?->income_monthly,
                        ];
                        foreach ($questions as $question) {
                            $answer = $application->answers[$question['key']] ?? '';
                            $row[] = is_array($answer) ? implode('; ', $answer) : $answer;
                        }
                        $this->writeRow($stream, [...$row, $application->ticket_code, $application->accepted_at?->toIso8601String()]);
                    }
                });
            fclose($stream);
        }, 'listing-'.$listing->id.'-accepted.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** @param resource $stream
     * @param  array<int, mixed>  $values
     */
    private function writeRow($stream, array $values): void
    {
        $cells = array_map(function (mixed $value): string {
            $text = (string) ($value ?? '');
            if (preg_match('/^[\s\x{FEFF}]*[=+\-@\t\r\n]/u', $text)) {
                return "'".$text;
            }

            return $text;
        }, $values);
        fputcsv($stream, $cells, ',', '"', '', "\r\n");
    }
}
