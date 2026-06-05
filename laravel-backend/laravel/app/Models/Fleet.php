<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Fleet extends Model
{
    protected $fillable = ['company_id', 'name', 'status'];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function geofences()
    {
        return $this->hasMany(Geofence::class);
    }

    public function shipments()
    {
        return $this->hasMany(Shipment::class);
    }
}
