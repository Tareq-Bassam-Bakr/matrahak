<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Services\Geo;

/**
 * دوال جغرافية بحتة (بدون قاعدة بيانات) — سهلة الاختبار.
 */
class GeoMath
{
    /** المسافة بالكيلومتر بين نقطتين (Haversine) */
    public static function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $r * asin(min(1.0, sqrt($a)));
    }

    /**
     * هل النقطة داخل هندسة GeoJSON (Polygon أو MultiPolygon)؟
     * يدعم الثقوب (Holes) — مهم لأن محافظة ريف دمشق تحيط بمحافظة دمشق.
     * ملاحظة: GeoJSON يخزّن الإحداثيات بترتيب [lng, lat].
     */
    public static function pointInGeometry(float $lat, float $lng, ?array $geometry): bool
    {
        if (! $geometry || ! isset($geometry['type'], $geometry['coordinates'])) {
            return false;
        }

        $polygons = match ($geometry['type']) {
            'Polygon' => [$geometry['coordinates']],
            'MultiPolygon' => $geometry['coordinates'],
            default => [],
        };

        foreach ($polygons as $rings) {
            if (! isset($rings[0]) || ! self::pointInRing($lat, $lng, $rings[0])) {
                continue;
            }
            $inHole = false;
            for ($i = 1, $n = count($rings); $i < $n; $i++) {
                if (self::pointInRing($lat, $lng, $rings[$i])) {
                    $inHole = true;
                    break;
                }
            }
            if (! $inHole) {
                return true;
            }
        }

        return false;
    }

    /** Ray casting */
    public static function pointInRing(float $lat, float $lng, array $ring): bool
    {
        $inside = false;
        $n = count($ring);
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            [$xi, $yi] = [(float) $ring[$i][0], (float) $ring[$i][1]];
            [$xj, $yj] = [(float) $ring[$j][0], (float) $ring[$j][1]];
            $intersects = (($yi > $lat) !== ($yj > $lat))
                && ($lng < ($xj - $xi) * ($lat - $yi) / (($yj - $yi) ?: 1e-12) + $xi);
            if ($intersects) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }
}
