<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Traits\ParsesSpatialAttributes;

class Shipment extends Model
{
    use ParsesSpatialAttributes;

    protected $fillable = ['fleet_id', 'tracking_number', 'status', 'last_moved_at'];

    // Unpack Point Geometry (X, Y) into explicit float variables
    public function getLatitudeAttribute(): ?float
    {
        $val = $this->current_location;
        if (!$val) {
            return null;
        }

        // Try parsing directly from EWKB hex in memory (fast, avoids N+1 database queries)
        if (is_string($val)) {
            $parsed = self::parsePointEwkb($val);
            if ($parsed !== null) {
                return $parsed['y'];
            }
        }

        // Fallback: If DB driver is SQLite, we can't run ST_Y
        if (DB::connection()->getDriverName() === 'sqlite') {
            return null;
        }

        $coords = DB::selectOne("SELECT ST_Y(current_location) as lat FROM shipments WHERE id = ?", [$this->id]);
        return $coords ? (float) $coords->lat : null;
    }

    public function getLongitudeAttribute(): ?float
    {
        $val = $this->current_location;
        if (!$val) {
            return null;
        }

        // Try parsing directly from EWKB hex in memory (fast, avoids N+1 database queries)
        if (is_string($val)) {
            $parsed = self::parsePointEwkb($val);
            if ($parsed !== null) {
                return $parsed['x'];
            }
        }

        // Fallback: If DB driver is SQLite, we can't run ST_X
        if (DB::connection()->getDriverName() === 'sqlite') {
            return null;
        }

        $coords = DB::selectOne("SELECT ST_X(current_location) as lng FROM shipments WHERE id = ?", [$this->id]);
        return $coords ? (float) $coords->lng : null;
    }

    public function fleet()
    {
        return $this->belongsTo(Fleet::class);
    }
}
