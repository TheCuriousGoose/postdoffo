<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Marketing pages
    |--------------------------------------------------------------------------
    |
    | The public marketing site — homepage pitch, "vs" comparison pages, the
    | Postman import guide, the self-hosting pitch, and the blog — only makes
    | sense for the hosted instance at postdoffo.nl. A self-hosted deployment
    | is someone's private team tool: it has no signups to pitch and nothing
    | that should be indexed by search engines.
    |
    | Defaults to enabled here so this repo's own hosted deployment keeps
    | working without extra configuration. .env.example ships it disabled, so
    | anyone who clones the repo and runs `cp .env.example .env` gets a plain
    | product with no marketing funnel attached, unless they opt back in.
    |
    */

    'enabled' => env('MARKETING_PAGES_ENABLED', true),

];
