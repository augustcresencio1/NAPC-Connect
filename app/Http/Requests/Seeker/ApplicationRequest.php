<?php

namespace App\Http\Requests\Seeker;

use App\Models\Listing;
use App\Services\RecommendationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $listing = $this->route('listing');
        abort_unless($this->user()?->role === 'seeker' && $listing instanceof Listing
            && $listing->status === 'published'
            && app(RecommendationService::class)->visibleTo($this->user())->whereKey($listing->id)->exists(), 404);

        return true;
    }

    /** Rules are built from the questions the organization created. */
    public function rules(): array
    {
        $rules = ['answers' => ['nullable', 'array']];

        foreach ($this->questions() as $q) {
            $field = "answers.{$q['key']}";
            $required = ! empty($q['required']);
            $options = $q['options'] ?? [];

            switch ($q['type']) {
                case 'textarea':
                    $rules[$field] = [$required ? 'required' : 'nullable', 'string', 'max:3000'];
                    break;

                case 'number':
                    $rules[$field] = [$required ? 'required' : 'nullable', 'numeric', 'between:-1000000000,1000000000'];
                    break;

                case 'date':
                    $rules[$field] = [$required ? 'required' : 'nullable', 'date'];
                    break;

                case 'select':
                case 'radio':
                    $rules[$field] = [$required ? 'required' : 'nullable', Rule::in($options)];
                    break;

                case 'checkbox':
                    $rules[$field] = $required ? ['required', 'array', 'min:1'] : ['nullable', 'array'];
                    $rules["{$field}.*"] = [Rule::in($options)];
                    break;

                default: // text
                    $rules[$field] = [$required ? 'required' : 'nullable', 'string', 'max:500'];
            }
        }

        return $rules;
    }

    /** Error messages show the question itself, not "answers.q_ab12cd". */
    public function attributes(): array
    {
        return collect($this->questions())
            ->mapWithKeys(fn ($q) => ["answers.{$q['key']}" => '“'.$q['label'].'”'])
            ->all();
    }

    /** Answers ready to save, keyed by question key. Unknown keys are dropped. */
    public function answers(): array
    {
        $input = (array) $this->validated('answers', []);
        $clean = [];

        foreach ($this->questions() as $q) {
            $value = $input[$q['key']] ?? null;

            if (is_array($value)) {
                $value = array_values(array_unique(array_filter($value, fn ($v) => trim((string) $v) !== '')));
                $value = $value ?: null;
            } else {
                $value = trim((string) $value);
                $value = $value === '' ? null : $value;
            }

            $clean[$q['key']] = $value;
        }

        return $clean;
    }

    private function questions(): array
    {
        $listing = $this->route('listing');

        return $listing instanceof Listing ? ($listing->application_form ?? []) : [];
    }
}
