<?php

return [

    'disk' => env('GALLERY_DISK', 'local'),

    'max_upload_kb' => env('GALLERY_MAX_UPLOAD_KB', 20480),

    'max_edge_full' => 2560,
    'max_edge_thumb' => 480,
    'jpeg_quality' => 82,

    'signed_url_ttl_hours' => 6,

];
