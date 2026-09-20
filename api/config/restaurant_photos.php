<?php

return [
    // Where an uploaded photo lives while its submission is unmoderated — never web-reachable.
    'pending_disk' => env('RESTAURANT_PHOTOS_PENDING_DISK', 'local'),

    // Where an approved photo is promoted to — publicly reachable, already symlinked.
    'public_disk' => env('RESTAURANT_PHOTOS_PUBLIC_DISK', 'public'),

    // Every upload is decoded/re-encoded through GD before storage, regardless of client
    // pre-processing — strips EXIF as a side effect of re-encoding, never trusts the client alone.
    'max_dimension_px' => 1600,
    'max_size_kb' => 5120,
    'max_per_submission' => 5,
];
