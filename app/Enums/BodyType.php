<?php

namespace App\Enums;

enum BodyType: string
{
    case None = 'none';
    case Raw = 'raw';
    case Json = 'json';
    case FormData = 'form_data';
    case UrlEncoded = 'urlencoded';
}
