<details class="ui-preferences">
    <summary>{{ __('Language & accessibility') }}</summary>
    <form method="POST" action="{{ route('seeker.preferences.update') }}" class="preferences-form">
        @csrf
        @method('PUT')
        <label for="ui-locale">{{ __('Language') }}</label>
        <select id="ui-locale" name="locale" class="input">
            <option value="en" @selected(app()->getLocale() === 'en')>English</option>
            <option value="fil" @selected(app()->getLocale() === 'fil')>Filipino</option>
        </select>
        <label><input type="checkbox" name="large_text" value="1" @checked($uiPreferences['large_text'] ?? false)> {{ __('Larger text') }}</label>
        <label><input type="checkbox" name="high_contrast" value="1" @checked($uiPreferences['high_contrast'] ?? false)> {{ __('High contrast') }}</label>
        <button type="submit" class="btn-primary btn-sm">{{ __('Save preferences') }}</button>
    </form>
</details>
