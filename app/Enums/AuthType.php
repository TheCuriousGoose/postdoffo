<?php

namespace App\Enums;

enum AuthType: string
{
    case None = 'none';
    case Bearer = 'bearer';
    case Basic = 'basic';
    case ApiKey = 'apikey';
}
