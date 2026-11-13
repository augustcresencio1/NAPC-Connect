@props(['listing', 'checked' => []])

@if ($documents = $listing->documentItems())
    <section class="card-lg document-checklist">
        <h2 class="h3">{{ __('Document checklist') }}</h2>
        <p class="muted">{{ __('Tick the documents you have ready, then save your progress.') }}</p>
        <form method="POST" action="{{ route('seeker.checklist.update', $listing) }}">
            @csrf
            @method('PUT')
            <fieldset>
                <legend class="sr-only">{{ __('Documents to bring') }}</legend>
                @foreach ($documents as $key => $document)
                    <label class="document-check-item">
                        <input type="checkbox" name="checked[]" value="{{ $key }}" @checked(in_array($key, $checked, true))>
                        <span>{{ $document }}</span>
                    </label>
                @endforeach
            </fieldset>
            @error('checked')<p class="field-error">{{ $message }}</p>@enderror
            @error('checked.*')<p class="field-error">{{ $message }}</p>@enderror
            <button type="submit" class="btn-primary btn-sm">{{ __('Save checklist') }}</button>
        </form>
    </section>
@endif
