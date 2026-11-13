<?php

namespace App\Http\Controllers\Seeker;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Validation\Rule;

class PreferenceController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'locale' => ['required', Rule::in(['en', 'fil'])],
            'large_text' => ['sometimes', 'boolean'],
            'high_contrast' => ['sometimes', 'boolean'],
        ]);
        $user = $request->user();
        $user->ui_preferences = [
            'locale' => $data['locale'],
            'large_text' => $request->boolean('large_text'),
            'high_contrast' => $request->boolean('high_contrast'),
        ];
        $user->save();
        App::setLocale($data['locale']);

        return back()->with('status', __('Preferences saved.'));
    }
}
