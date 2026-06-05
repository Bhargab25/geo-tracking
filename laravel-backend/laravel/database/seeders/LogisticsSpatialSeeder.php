<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LogisticsSpatialSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a sample company
        $companyId = DB::table('companies')->insertGetId([
            'name' => 'Global Logistics Corp',
            'api_token' => Str::random(40),
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // 2. Create Fleet
        $fleetId = DB::table('fleets')->insertGetId([
            'company_id' => $companyId,
            'name' => 'High-Value Security Fleet Alpha',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // 3. Create a Secure Geofence Box around an area (e.g., Central London Hub)
        // Polygon Coordinates: (Lng Lat) forming a square loop
        DB::statement("
            INSERT INTO geofences (fleet_id, zone_name, risk_level, boundary, created_at, updated_at)
            VALUES (
                ?, 
                'Primary Distribution Center Vault', 
                'high', 
                ST_GeomFromText('POLYGON((-0.1500 51.5100, -0.1100 51.5100, -0.1100 51.4900, -0.1500 51.4900, -0.1500 51.5100))', 4326),
                NOW(),
                NOW()
            );
        ", [$fleetId]);

        // 4. Create a active Shipment sitting safely inside that Polygon
        DB::statement("
            INSERT INTO shipments (fleet_id, tracking_number, status, current_location, last_moved_at, created_at, updated_at)
            VALUES (
                ?, 
                'TRK-SECURE-9981', 
                'transit', 
                ST_GeomFromText('POINT(-0.1300 51.5000)', 4326), 
                NOW(),
                NOW(),
                NOW()
            );
        ", [$fleetId]);
    }
}
