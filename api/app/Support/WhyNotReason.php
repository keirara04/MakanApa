<?php

namespace App\Support;

/** Answers to "kenapa tak nak?" after a reroll. `NotFeelingIt` may carry an optional detail. */
enum WhyNotReason: string
{
    case TooFar = 'too_far';
    case TooPricey = 'too_pricey';
    case NotFeelingIt = 'not_feeling_it';
    case AteRecently = 'ate_recently';

    /** Second-layer answers — only valid with NotFeelingIt. */
    public const DETAILS = ['too_heavy', 'too_similar', 'dont_like_cuisine', 'just_not_today'];
}
