<?php

namespace App\Support;

enum TuneDirection: string
{
    case Closer = 'closer';
    case Cheaper = 'cheaper';
    case Safer = 'safer';
    case Adventurous = 'adventurous';
}
