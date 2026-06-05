<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConsumeTelemetryStream extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telemetry:consume';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Daemon that continuously processes raw GPS data from Redis';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("🛰️  Listening for telemetry on Redis 'telemetry_stream'...");

        while (true) {
            try {
                // 5 second timeout to prevent socket hanging
                $result = Redis::blpop('telemetry_stream', 5);

                if ($result) {
                    $rawString = $result[1];
                    $payload = json_decode($rawString, true);

                    // BULLETPROOFING: Check if JSON decode actually worked
                    if (is_array($payload) && isset($payload['shipment_id'])) {
                        $this->processSpatialRules($payload);
                    } else {
                        $this->error("⚠️ Skiping invalid or malformed payload: " . $rawString);
                    }
                }
            } catch (\RedisException $e) {
                $this->error("Redis Connection Dropped: " . $e->getMessage() . " - Retrying in 2s...");
                sleep(2);
            } catch (\Throwable $e) { 
                // Changed from \Exception to \Throwable to catch PHP TypeErrors
                $this->error("❌ Telemetry Processing Failed: " . $e->getMessage());
                Log::error("Telemetry Processing Failed: " . $e->getMessage());
            }
        }
    }


    private function processSpatialRules(array $data)
    {
        $shipmentId = $data['shipment_id'];
        $lat = $data['latitude'];
        $lng = $data['longitude']; // PostGIS expects Longitude (X) first, then Latitude (Y)
        $speed = $data['speed_kmh'];
        $timestamp = $data['timestamp'];

        // 1. Append to Historical Telemetry Logs
        DB::statement("
            INSERT INTO telemetry_logs (shipment_id, recorded_at, speed_kmh, coordinates, created_at, updated_at)
            VALUES (?, ?, ?, ST_SetSRID(ST_MakePoint(?, ?), 4326), NOW(), NOW())
        ", [$shipmentId, $timestamp, $speed, $lng, $lat]);

        // 2. Update Live Shipment Location
        DB::statement("
            UPDATE shipments 
            SET current_location = ST_SetSRID(ST_MakePoint(?, ?), 4326), 
                last_moved_at = NOW(),
                updated_at = NOW()
            WHERE id = ?
        ", [$lng, $lat, $shipmentId]);

        // 3. The Core Geofence Check (Is the point inside the Polygon?)
        $breachCheck = DB::selectOne("
            SELECT 
                s.status,
                g.zone_name,
                ST_Contains(g.boundary, s.current_location) AS is_inside
            FROM shipments s
            JOIN geofences g ON g.fleet_id = s.fleet_id
            WHERE s.id = ?
        ", [$shipmentId]);

        // 4. Trigger Escalation Matrix if breached
        if ($breachCheck && !$breachCheck->is_inside) {
            $this->error("🚨 BREACH DETECTED: Shipment {$shipmentId} left {$breachCheck->zone_name}!");

            // Mark shipment as breached in the database
            if ($breachCheck->status !== 'breached') {
                DB::table('shipments')->where('id', $shipmentId)->update(['status' => 'breached']);

                // TODO: Fire off Notification Event (Email / WhatsApp)
                // event(new \App\Events\GeofenceBreached($shipmentId));
            }
        } else {
            $this->info("✅ Shipment {$shipmentId} processed successfully (Secure).");
        }
    }
}
