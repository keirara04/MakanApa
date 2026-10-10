<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketingEvent extends Model
{
    public const LANDING_VIEW = 'landing_view';

    /** A download-button tap. Beta-era rows (TestFlight links) are stored as 'testflight_click'. */
    public const APP_STORE_CLICK = 'app_store_click';

    /** Share funnel: the app's "Send to geng" tap → a recipient views the page → opens the app / gets it. */
    /** "Open MakanApa" tapped (Android visitors, or "use it in your browser"). */
    public const WEB_APP_CLICK = 'web_app_click';

    public const SHARE_STARTED = 'share_started';

    public const SHARE_VIEW = 'share_view';

    public const SHARE_OPEN_APP = 'share_open_app';

    public const SHARE_GET_APP = 'share_get_app';

    /** Where a download button can live on the site. Anything else is stored as null. */
    public const SOURCES = ['nav', 'hero', 'qr', 'final', 'faq', 'missing', 'ambassadors', 'spin', 'reviews'];

    public const UPDATED_AT = null;

    protected $fillable = ['event', 'source', 'restaurant_id'];
}
