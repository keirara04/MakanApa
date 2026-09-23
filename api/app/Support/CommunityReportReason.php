<?php

namespace App\Support;

enum CommunityReportReason: string
{
    case Spam = 'spam';
    case Offensive = 'offensive';
    case Harassment = 'harassment';
    case Misleading = 'misleading';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Spam => 'Spam or advertising',
            self::Offensive => 'Offensive or hateful',
            self::Harassment => 'Harassment or bullying',
            self::Misleading => 'False or misleading',
            self::Other => 'Something else',
        };
    }
}
