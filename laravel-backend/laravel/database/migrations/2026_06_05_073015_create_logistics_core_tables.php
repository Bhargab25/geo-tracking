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
        // Companies Table
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('api_token', 64)->unique();
            $table->timestamps();
        });

        // Fleets Table
        Schema::create('fleets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('status')->default('active'); // active, suspended, maintenance
            $table->timestamps();
        });

        // Geofences Table (With PostGIS Polygon Geometry)
        Schema::create('geofences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fleet_id')->constrained()->onDelete('cascade');
            $table->string('zone_name');
            $table->enum('risk_level', ['low', 'medium', 'high'])->default('low');
            $table->timestamps();
        });

        // Add PostGIS Spatial Polygon Column to Geofences
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('ALTER TABLE geofences ADD COLUMN boundary TEXT;');
        } else {
            DB::statement('ALTER TABLE geofences ADD COLUMN boundary GEOMETRY(Polygon, 4326);');
        }

        // Shipments Table (With PostGIS Point Geometry)
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fleet_id')->constrained()->onDelete('cascade');
            $table->string('tracking_number')->unique();
            $table->string('status')->default('transit'); // transit, delivered, breached, idle
            $table->timestamp('last_moved_at')->useCurrent();
            $table->timestamps();
        });

        // Add PostGIS Spatial Point Column to Shipments for their live location
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('ALTER TABLE shipments ADD COLUMN current_location TEXT;');
        } else {
            DB::statement('ALTER TABLE shipments ADD COLUMN current_location GEOMETRY(Point, 4326);');
        }

        // Historical Telemetry Logs (for auditing and plotting routes)
        Schema::create('telemetry_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->onDelete('cascade');
            $table->timestamp('recorded_at');
            $table->float('speed_kmh')->default(0);
            $table->timestamps();
        });

        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('ALTER TABLE telemetry_logs ADD COLUMN coordinates TEXT;');
        } else {
            DB::statement('ALTER TABLE telemetry_logs ADD COLUMN coordinates GEOMETRY(Point, 4326);');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telemetry_logs');
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('geofences');
        Schema::dropIfExists('fleets');
        Schema::dropIfExists('companies');
    }
};
