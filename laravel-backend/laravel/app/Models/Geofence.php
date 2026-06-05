<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Traits\ParsesSpatialAttributes;

class Geofence extends Model
{
    use ParsesSpatialAttributes;

    protected $fillable = ['fleet_id', 'zone_name', 'risk_level'];

    // Convert polygon binary representation into a clean coordinate array
    public function getCoordinatesAttribute(): array
    {
        $val = $this->boundary;
        if (!$val) {
            return [];
        }

        // Try parsing directly from EWKB hex in memory (fast, avoids N+1 database queries)
        if (is_string($val)) {
            $parsed = self::parsePolygonEwkb($val);
            if ($parsed !== null) {
                return $parsed;
            }
        }

        // Fallback: If DB driver is SQLite, we can't run ST_AsText
        if (DB::connection()->getDriverName() === 'sqlite') {
            return [];
        }

        // ST_AsText converts geometry to a string like: POLYGON((-0.15 51.51, -0.11 51.51, ...))
        $geoText = DB::selectOne("SELECT ST_AsText(boundary) as text FROM geofences WHERE id = ?", [$this->id]);
        
        if (!$geoText || !$geoText->text) {
            return [];
        }

        // Parse the polygon text format into arrays
        preg_match('/\(\((.*)\)\)/', $geoText->text, $matches);
        if (empty($matches[1])) {
            return [];
        }

        $points = explode(',', $matches[1]);
        return array_map(function ($point) {
            $parts = preg_split('/\s+/', trim($point));
            if (count($parts) >= 2) {
                $lng = $parts[0];
                $lat = $parts[1];
                return [(float) $lat, (float) $lng]; // Leaflet maps read [Lat, Lng]
            }
            return [0.0, 0.0];
        }, $points);
    }

    public function fleet()
    {
        return $this->belongsTo(Fleet::class);
    }
}
