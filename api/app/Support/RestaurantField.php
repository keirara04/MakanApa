<?php

namespace App\Support;

/**
 * Single authoritative list of restaurant columns that can carry a
 * `restaurant_field_overrides` row — referenced by submission validation, the
 * `changed_fields` allow-list, and Admin\RestaurantSubmissionController's materialization
 * helper, so it's never duplicated/drifted across those three call sites.
 *
 * Menu items and photos are collections, not single-value fields, and are handled by their
 * own tables (`restaurant_menu_items`, `restaurant_photos`) — not part of this list.
 */
final class RestaurantField
{
    public const OVERRIDABLE = [
        'name', 'address', 'food_category', 'price_level', 'latitude', 'longitude',
        'opening_hours', 'phone', 'instagram_handle', 'tiktok_handle', 'website_url',
    ];
}
