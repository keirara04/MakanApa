<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketingEvent extends Model
{
    public const LANDING_VIEW = 'landing_view';

    public const TESTFLIGHT_CLICK = 'testflight_click';

    /** Share funnel: the app's "Send to geng" tap → a recipient views the page → opens the app / gets it. */
    public const SHARE_STARTED = 'share_started';

    public const SHARE_VIEW = 'share_view';

    public const SHARE_OPEN_APP = 'share_open_app';

    public const SHARE_GET_APP = 'share_get_app';

    /** Where a download button can live on the site. Anything else is stored as null. */
    public const SOURCES = ['nav', 'hero', 'qr', 'final', 'faq'];

    public const UPDATED_AT = null;

    protected $fillable = ['event', 'source', 'restaurant_id'];
}
