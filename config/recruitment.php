<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Curriculum uploads
    |--------------------------------------------------------------------------
    */

    'cv_max_size_kb' => 10240,

    'cv_accepted_mime_types' => [
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'image/jpeg',
        'image/png',
        'image/webp',
    ],

    /*
    |--------------------------------------------------------------------------
    | PDF rendering
    |--------------------------------------------------------------------------
    |
    | pdftoppm (poppler-utils) turns a PDF page into an image so the candidate
    | photo can be cropped. Without it, the rest of the CV reading still works.
    |
    */

    'pdftoppm_binary' => env('PDFTOPPM_BINARY', 'pdftoppm'),

];
