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

];
