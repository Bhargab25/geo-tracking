<?php

namespace Tests\Feature;

use App\Models\Geofence;
use App\Models\Shipment;
use App\Models\Fleet;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShipmentGeofenceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test parses spatial coordinates correctly.
     */
    public function test_spatial_coordinate_parsing()
    {
        // Create sample company and fleet using Eloquent models
        $company = Company::create([
            'name' => 'Test Company',
            'api_token' => Str::random(40),
        ]);

        $fleet = Fleet::create([
            'company_id' => $company->id,
            'name' => 'Test Fleet',
            'status' => 'active',
        ]);

        // 1. POINT EWKB representation of (-0.1300, 51.5000)
        // Hex: 0101000020E6100000a4703d0ad7a3c0bf0000000000c04940
        $pointHex = '0101000020E6100000a4703d0ad7a3c0bf0000000000c04940';

        $shipment = new Shipment();
        $shipment->fleet_id = $fleet->id;
        $shipment->tracking_number = 'TRK-TEST-123';
        $shipment->current_location = $pointHex;
        $shipment->save();

        // Retrieve from database
        $dbShipment = Shipment::find($shipment->id);
        $this->assertEquals(-0.1300, $dbShipment->longitude);
        $this->assertEquals(51.5000, $dbShipment->latitude);

        // 2. POLYGON EWKB representation
        // Boundary with 5 points: ((-0.15 51.51), (-0.11 51.51), (-0.11 51.49), (-0.15 51.49), (-0.15 51.51))
        // Header: 0103000020E61000000100000005000000
        $polygonHex = '0103000020E61000000100000005000000' .
                      '333333333333c3bfe17a14ae47c14940' .
                      '295c8fc2f528bcbfe17a14ae47c14940' .
                      '295c8fc2f528bcbf1f85eb51b8be4940' .
                      '333333333333c3bf1f85eb51b8be4940' .
                      '333333333333c3bfe17a14ae47c14940';

        $geofence = new Geofence();
        $geofence->fleet_id = $fleet->id;
        $geofence->zone_name = 'Central London Hub';
        $geofence->risk_level = 'high';
        $geofence->boundary = $polygonHex;
        $geofence->save();

        // Retrieve from database
        $dbGeofence = Geofence::find($geofence->id);
        $coords = $dbGeofence->coordinates;

        $this->assertCount(5, $coords);
        // Coordinate format is [[lat, lng], ...]
        $this->assertEquals([51.51, -0.15], $coords[0]);
        $this->assertEquals([51.51, -0.11], $coords[1]);
        $this->assertEquals([51.49, -0.11], $coords[2]);
        $this->assertEquals([51.49, -0.15], $coords[3]);
        $this->assertEquals([51.51, -0.15], $coords[4]);
    }
}
