<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Form-data uploads
    |--------------------------------------------------------------------------
    |
    | Ceiling for a single file attached to a request's multipart/form-data
    | body, in kilobytes. Raising it past PHP's own upload_max_filesize /
    | post_max_size has no effect — those cut the upload off before it ever
    | reaches validation — so move all three together.
    |
    */

    'max_upload_kilobytes' => (int) env('REQUEST_MAX_UPLOAD_KILOBYTES', 25 * 1024),

    /*
    |--------------------------------------------------------------------------
    | Private and internal targets
    |--------------------------------------------------------------------------
    |
    | Requests are fired by this server, so without a check the app is an
    | authenticated proxy into whatever network it runs in — a user could point
    | one at a cloud metadata endpoint or an internal admin panel and read the
    | response. On by default: it costs a hosted deployment nothing, since
    | .test/.local/.localhost hosts are sent from the user's own browser and
    | never touch this guard.
    |
    | Turn it off only where reaching private addresses from the server is the
    | point — a self-hosted install sitting inside the network it tests.
    |
    */

    'block_private_hosts' => (bool) env('REQUEST_BLOCK_PRIVATE_HOSTS', true),

    /*
    |--------------------------------------------------------------------------
    | Execution rate limit
    |--------------------------------------------------------------------------
    |
    | Requests fired per minute per user, across executing, sending and previewing.
    | Stops one account from turning the server into a traffic generator.
    |
    */

    'rate_limit_per_minute' => (int) env('REQUEST_RATE_LIMIT_PER_MINUTE', 120),

];
