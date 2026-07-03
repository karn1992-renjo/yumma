<?php
// app/Models/DeliveryArea.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryArea extends Model
{
    use HasFactory, SoftDeletes;
    
    protected $table = 'delivery_areas';
    
    protected $fillable = [
        'name',
        'description',
        'area_type',
        'latitude',
        'longitude',
        'radius_km',
        'polygon_coordinates',
        'max_daily_bookings',
        'is_active',
    ];
    
    protected $casts = [
        'is_active' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
        'radius_km' => 'float',
        'max_daily_bookings' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
    
    protected $attributes = [
        'area_type' => 'circle',
        'max_daily_bookings' => 0,
        'is_active' => true,
    ];
    
    public function setIsActiveAttribute($value)
    {
        $this->attributes['is_active'] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
    
    public function getIsActiveAttribute($value)
    {
        return (bool) $value;
    }
    
    public function setPolygonCoordinatesAttribute($value)
    {
        if (empty($value)) {
            $this->attributes['polygon_coordinates'] = null;
        } elseif (is_array($value)) {
            $this->attributes['polygon_coordinates'] = json_encode($value);
        } elseif (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded) && count($decoded) >= 3) {
                $this->attributes['polygon_coordinates'] = $value;
            } else {
                $this->attributes['polygon_coordinates'] = null;
            }
        } else {
            $this->attributes['polygon_coordinates'] = null;
        }
    }
    
    public function getPolygonCoordinatesAttribute($value)
    {
        if (empty($value)) {
            return null;
        }
        
        $decoded = json_decode($value, true);
        if (is_array($decoded) && count($decoded) >= 3) {
            return $decoded;
        }
        
        return null;
    }
    
    public function containsPoint($latitude, $longitude)
    {
        if ($this->area_type === 'circle') {
            return $this->isWithinCircle($latitude, $longitude);
        }
        return $this->isWithinPolygon($latitude, $longitude);
    }
    
    private function isWithinCircle($latitude, $longitude)
    {
        if (!$this->latitude || !$this->longitude || !$this->radius_km) {
            return false;
        }
        
        $distance = $this->haversineDistance(
            (float)$this->latitude, 
            (float)$this->longitude,
            (float)$latitude, 
            (float)$longitude
        );
        
        return $distance <= (float)$this->radius_km;
    }
    
    private function haversineDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371;
        
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        
        return $earthRadius * $c;
    }
    
    private function isWithinPolygon($latitude, $longitude)
    {
        $polygon = $this->polygon_coordinates;
        
        if (!$polygon || !is_array($polygon) || count($polygon) < 3) {
            return false;
        }
        
        $intersections = 0;
        $verticesCount = count($polygon);
        
        for ($i = 0, $j = $verticesCount - 1; $i < $verticesCount; $j = $i++) {
            $vertex1 = $polygon[$i];
            $vertex2 = $polygon[$j];
            
            if (($vertex1['lat'] > $latitude) != ($vertex2['lat'] > $latitude) &&
                ($longitude < ($vertex2['lng'] - $vertex1['lng']) * 
                ($latitude - $vertex1['lat']) / ($vertex2['lat'] - $vertex1['lat']) + $vertex1['lng'])) {
                $intersections++;
            }
        }
        
        return ($intersections % 2) == 1;
    }
    
    public function getPolygonArea()
    {
        $polygon = $this->polygon_coordinates;
        if (!$polygon || count($polygon) < 3) {
            return 0;
        }
        
        $area = 0;
        $n = count($polygon);
        
        for ($i = 0; $i < $n; $i++) {
            $j = ($i + 1) % $n;
            $area += $polygon[$i]['lat'] * $polygon[$j]['lng'];
            $area -= $polygon[$j]['lat'] * $polygon[$i]['lng'];
        }
        
        $area = abs($area) / 2;
        return $area * 12321;
    }
    
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
    
    public function scopeCircle($query)
    {
        return $query->where('area_type', 'circle');
    }
    
    public function scopePolygon($query)
    {
        return $query->where('area_type', 'polygon');
    }
}