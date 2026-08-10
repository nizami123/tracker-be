<?php
defined('BASEPATH') OR exit('No direct script access allowed');

if (!function_exists('distance_meters')) {
    /**
     * Haversine distance in meters between two lat/lng points.
     * This is the SERVER-SIDE source of truth for attendance location
     * validation (spec section 6/7). The Android app also computes this
     * client-side for instant UI feedback, but that result is never
     * trusted — this function is what actually decides accept/reject.
     */
    function distance_meters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000; // meters

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
