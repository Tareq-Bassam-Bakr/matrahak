<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Services\Geo;

use App\Models\GeofenceZone;
use Illuminate\Support\Collection;

/**
 * GeoFencing: يحدد المناطق المراقَبة التي يقع فيها البلاغ.
 */
class GeofenceService
{
    /** @return Collection<int, GeofenceZone> */
    public function zonesContaining(float $lat, float $lng): Collection
    {
        return GeofenceZone::query()->active()->get()->filter(function (GeofenceZone $zone) use ($lat, $lng) {
            if ($zone->boundary) {
                return GeoMath::pointInGeometry($lat, $lng, $zone->boundary);
            }
            if ($zone->center_lat !== null && $zone->center_lng !== null && $zone->radius_meters) {
                return GeoMath::distanceKm($lat, $lng, $zone->center_lat, $zone->center_lng) * 1000 <= $zone->radius_meters;
            }

            return false;
        })->values();
    }
}
