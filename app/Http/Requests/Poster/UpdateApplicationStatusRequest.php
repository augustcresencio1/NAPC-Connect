<?php

namespace App\Http\Requests\Poster;

use App\Models\Application;
use App\Models\Listing;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateApplicationStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $listing = $this->route('listing');
        $application = $this->route('application');
        $user = $this->user();
        abort_unless($user?->role === 'poster' && $user->poster_status === 'approved'
            && $listing instanceof Listing && $listing->apply_mode === 'form'
            && (int) $listing->user_id === (int) $user->id
            && $application instanceof Application && (int) $application->listing_id === (int) $listing->id, 404);

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(Application::STATUSES)],
        ];
    }
}
