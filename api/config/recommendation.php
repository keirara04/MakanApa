<?php

return [
    // Adds a `debug` key to the solo recommendation response (craving resolution details,
    // candidate counts, the winning pick's score breakdown). Dev/staging only — never enable
    // in production, since it echoes internal scoring/query details back to the client.
    'debug' => env('RECOMMENDATION_DEBUG', false),
];
