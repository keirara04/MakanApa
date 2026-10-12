<?php

namespace Tests\Unit\Support;

use App\Services\Brain\TuneService;
use App\Support\Region;
use PHPUnit\Framework\TestCase;

class RegionTest extends TestCase
{
    public function test_points_resolve_to_their_region(): void
    {
        $this->assertSame(Region::Malaysia, Region::at(2.9284, 101.7802)); // Bangi
        $this->assertSame(Region::Malaysia, Region::at(1.5535, 110.3593)); // Kuching
        $this->assertSame(Region::Korea, Region::at(37.5894, 127.0276)); // Korea University
        $this->assertSame(Region::UnitedStates, Region::at(40.7982, -77.8599)); // Penn State
        $this->assertSame(Region::Other, Region::at(51.5072, -0.1276)); // London
        $this->assertSame(Region::Other, Region::at(1.2903, 103.852)); // Singapore
        $this->assertSame(Region::Malaysia, Region::at(1.4927, 103.7414)); // Johor Bahru
        $this->assertSame(Region::Other, Region::at(7.0086, 100.4747)); // Hat Yai
        $this->assertSame(Region::Other, Region::at(43.6532, -79.3832)); // Toronto
        $this->assertSame(Region::Other, Region::at(45.5019, -73.5674)); // Montreal
        $this->assertSame(Region::Other, Region::at(49.2827, -123.1207)); // Vancouver
        $this->assertSame(Region::UnitedStates, Region::at(42.8864, -78.8784)); // Buffalo
        $this->assertSame(Region::UnitedStates, Region::at(47.6062, -122.3321)); // Seattle
    }

    public function test_price_labels_follow_the_region(): void
    {
        $this->assertSame('≈ RM20/person', Region::Malaysia->approximateSpend(2));
        $this->assertSame('≈ ₩18,000/person', Region::Korea->approximateSpend(2));
        $this->assertSame('≈ $40+/person', Region::UnitedStates->approximateSpend(3));
        $this->assertSame('Budget', Region::Other->approximateSpend(1));
        $this->assertNull(Region::Korea->approximateSpend(null));
        $this->assertSame('under $15', Region::UnitedStates->priceBand(0));
    }

    public function test_us_timezone_follows_longitude(): void
    {
        $this->assertSame('America/New_York', Region::UnitedStates->timezone(-77.86));
        $this->assertSame('America/Chicago', Region::UnitedStates->timezone(-89.4));
        $this->assertSame('America/Los_Angeles', Region::UnitedStates->timezone(-122.4));
    }

    public function test_elsewhere_gets_a_whole_hour_zone_from_longitude(): void
    {
        $this->assertSame('Etc/GMT+0', Region::timezoneAt(51.5072, -0.1276));
        $this->assertSame('Etc/GMT-10', Region::timezoneAt(-37.8136, 144.9631)); // Melbourne, UTC+10
        $this->assertNotFalse(timezone_open(Region::timezoneAt(51.5072, -0.1276)));
    }

    public function test_brain_distances_read_in_miles_for_the_us(): void
    {
        $this->assertSame('500 m', Region::Malaysia->distance(480));
        $this->assertSame('1.2 km', Region::Korea->distance(1234));
        $this->assertSame('0.3 mi', Region::UnitedStates->distance(480));
        $this->assertSame('1.6 mi', Region::UnitedStates->radius(2.5));
        $this->assertSame('Closer: only 0.3 mi away', TuneService::lead(['closer'], ['distanceKm' => 0.48, 'region' => 'US']));
        $this->assertSame('Closer: only 500 m away', TuneService::lead(['closer'], ['distanceKm' => 0.48])); // facts from before regions
    }
}
