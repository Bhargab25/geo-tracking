<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            // 1. Spatial GiST Index on Geofence Bound Polygon
            DB::statement('CREATE INDEX geofences_boundary_gist ON geofences USING GIST (boundary);');

            // 2. Spatial GiST Index on Live Shipment Locations
            DB::statement('CREATE INDEX shipments_location_gist ON shipments USING GIST (current_location);');

            // 3. Spatial GiST Index on Telemetry Audit Logs
            DB::statement('CREATE INDEX telemetry_coords_gist ON telemetry_logs USING GIST (coordinates);');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS geofences_boundary_gist;');
            DB::statement('DROP INDEX IF EXISTS shipments_location_gist;');
            DB::statement('DROP INDEX IF EXISTS telemetry_coords_gist;');
        }
    }
};
