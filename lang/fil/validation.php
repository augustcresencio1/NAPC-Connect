<?php

return [
    'required' => 'Kailangan ang :attribute.',
    'string' => 'Dapat teksto ang :attribute.',
    'integer' => 'Dapat buong bilang ang :attribute.',
    'numeric' => 'Dapat numero ang :attribute.',
    'array' => 'Dapat listahan ang :attribute.',
    'boolean' => 'Hindi wasto ang napiling :attribute.',
    'date' => 'Dapat wastong petsa ang :attribute.',
    'before_or_equal' => 'Dapat :date o mas maaga ang :attribute.',
    'after_or_equal' => 'Dapat :date o mas huli ang :attribute.',
    'in' => 'Hindi wasto ang napiling :attribute.',
    'exists' => 'Hindi wasto ang napiling :attribute.',
    'distinct' => 'May nauulit na halaga sa :attribute.',
    'max' => ['array' => 'Hanggang :max lamang ang maaaring piliin sa :attribute.', 'numeric' => 'Hindi maaaring lumampas sa :max ang :attribute.', 'string' => 'Hanggang :max karakter lamang ang :attribute.'],
    'min' => ['array' => 'Pumili ng hindi bababa sa :min sa :attribute.', 'numeric' => 'Dapat hindi bababa sa :min ang :attribute.', 'string' => 'Dapat may hindi bababa sa :min karakter ang :attribute.'],
    'between' => ['numeric' => 'Dapat nasa pagitan ng :min at :max ang :attribute.'],
    'attributes' => [
        'locale' => 'wika', 'large_text' => 'mas malaking teksto', 'high_contrast' => 'mas malinaw na mga kulay',
        'checked' => 'mga dokumento', 'checked.*' => 'dokumento', 'birthdate' => 'petsa ng kapanganakan',
        'income_monthly' => 'buwanang kita', 'year_level' => 'antas sa pag-aaral', 'school_type' => 'uri ng paaralan',
        'school_name' => 'pangalan ng paaralan', 'course' => 'kurso', 'region' => 'rehiyon', 'province' => 'lalawigan',
        'city' => 'lungsod o munisipalidad', 'sectors' => 'mga sektor',
    ],
];
