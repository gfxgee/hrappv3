<?php

namespace App\Enum;

enum Sex : string
{
    case MALE  = 'male';
    case FEMALE = 'female';
    case GAY = 'gay';
    case LESBIAN = 'lesbian';
    case OTHER = 'other';
}
