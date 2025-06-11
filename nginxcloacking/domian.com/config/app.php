<?php
return [
    'modules' => [
        'ampstealth' => [
            'class' => \modules\AmpStealthModule::class,
        ],
    ],
    'bootstrap' => ['ampstealth'],
];
