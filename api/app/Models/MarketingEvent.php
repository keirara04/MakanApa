<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketingEvent extends Model
{
    public const LANDING_VIEW = 'landing_view';

    public const TESTFLIGHT_CLICK = 'testflight_click';

    /** Where a download button can live on the site. Anything else is stored as null. */
    public const SOURCES = ['nav', 'hero', 'qr', 'final', 'faq'];

    public const UPDATED_AT = null;

    protected $fillable = ['event', 'source'];
}
