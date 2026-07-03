<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Services\BranchManagementService;
use App\Models\Cuisine;

class Restaurant extends Model
{
    protected $table = 'restaurants';
    
    protected $fillable = [
        'owner_id', 
        'branch_id',
        'name', 
        'slug', 
        'description', 
        'address', 
        'city', 
        'state',
        'pincode', 
        'latitude', 
        'longitude', 
        'delivery_radius', 
        'phone', 
        'email', 
        'fssai_license_number',
        'cuisine',
        'is_open', 
        'is_pure_veg', 
        'min_order_amount', 
        'delivery_fee',
        'commission_rate',
        'commission_calculation_type',
        'delivery_time', 
        'restaurant_type',
        'dining_charge',
        'dining_settings',
        'rating', 
        'total_ratings', 
        'banner_image',
        'logo_image', 
        'cover_image', 
        'is_featured', 
        'is_verified', 
        'ad_expiry',
        // Day-wise timing fields
        'open_time',
        'close_time',
        'weekly_timings',
        'timezone',
        'auto_accept_orders',
        'auto_print_new_orders',
        'order_lead_time',
        'same_day_delivery',
        'offline_reason'
    ];
    
    protected $casts = [
        'cuisine' => 'array',
        'weekly_timings' => 'array',
        'offline_reason' => 'array',
        'dining_settings' => 'array',
        'is_open' => 'boolean',
        'is_pure_veg' => 'boolean',
        'is_featured' => 'boolean',
        'is_verified' => 'boolean',
        'dining_charge' => 'decimal:2',
        'auto_accept_orders' => 'boolean',
        'auto_print_new_orders' => 'boolean',
        'same_day_delivery' => 'boolean',
        'ad_expiry' => 'datetime',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'delivery_radius' => 'decimal:2',
        'order_lead_time' => 'integer',
    ];
    
    protected $dates = [
        'created_at',
        'updated_at',
        'ad_expiry'
    ];
    
    protected $appends = [
        'rating_percentage',
        'formatted_address',
        'is_open_now',
        'cuisine_names',
        'cuisine_text',
    ];

    public function getCuisineNamesAttribute(): array
    {
        return self::resolveCuisineNames($this->attributes['cuisine'] ?? []);
    }

    public function getCuisineTextAttribute(): string
    {
        return implode(', ', $this->cuisine_names);
    }

    public static function resolveCuisineNames($cuisine): array
    {
        if (is_string($cuisine)) {
            $decoded = json_decode($cuisine, true);
            $cuisine = is_array($decoded) ? $decoded : explode(',', $cuisine);
        }

        $values = collect($cuisine ?? [])
            ->map(function ($value) {
                if (is_array($value)) {
                    return $value['name'] ?? $value['title'] ?? $value['cuisine_name'] ?? $value['id'] ?? null;
                }

                return $value;
            })
            ->filter(fn ($value) => $value !== null && trim((string) $value) !== '')
            ->map(fn ($value) => trim((string) $value))
            ->values();

        if ($values->isEmpty()) {
            return [];
        }

        $ids = $values
            ->filter(fn ($value) => ctype_digit((string) $value))
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values();

        $namesById = $ids->isNotEmpty()
            ? Cuisine::whereIn('id', $ids)->pluck('name', 'id')
            : collect();

        return $values
            ->map(function ($value) use ($namesById) {
                if (ctype_digit((string) $value)) {
                    return $namesById->get((int) $value);
                }

                return $value;
            })
            ->filter(fn ($value) => filled($value))
            ->unique()
            ->values()
            ->all();
    }
    
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($restaurant) {
            if (empty($restaurant->slug)) {
                $restaurant->slug = Str::slug($restaurant->name) . '-' . uniqid();
            }

            if (empty($restaurant->branch_id)) {
                $branch = app(BranchManagementService::class)->resolveBranchForRestaurant($restaurant);
                if ($branch) {
                    $restaurant->branch_id = $branch->id;
                }
            }
        });
        
        static::updating(function ($restaurant) {
            if (empty($restaurant->branch_id) && ($restaurant->isDirty('city') || $restaurant->isDirty('address') || $restaurant->isDirty('pincode'))) {
                $branch = app(BranchManagementService::class)->resolveBranchForRestaurant($restaurant);
                if ($branch) {
                    $restaurant->branch_id = $branch->id;
                }
            }

            // Auto-update is_open based on weekly timings when timings change
            if ($restaurant->isDirty('weekly_timings') || $restaurant->isDirty('timezone')) {
                $restaurant->is_open = $restaurant->shouldBeOpenNow();
            }
        });
    }
    
    // ==================== Relationships ====================
    
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
    
    public function categories()
    {
        return $this->hasMany(Category::class);
    }
    
    public function menuItems()
    {
        return $this->hasMany(MenuItem::class);
    }

    public function amountForOne(): ?float
    {
        $price = $this->menuItems()
            ->where('is_available', true)
            ->where(function ($query) {
                $query->whereNull('approval_status')
                    ->orWhere('approval_status', 'approved');
            })
            ->selectRaw('MIN(COALESCE(NULLIF(discounted_price, 0), price)) as lowest_price')
            ->value('lowest_price');

        return $price !== null ? round((float) $price, 2) : null;
    }
    
    public function orders()
    {
        return $this->hasMany(Order::class);
    }
    
    public function promos()
    {
        return $this->hasMany(PromoCode::class);
    }
    
    public function diningBookings()
    {
        return $this->hasMany(DiningBooking::class);
    }
    
    public function printerSettings()
    {
        return $this->hasMany(PrinterSetting::class);
    }

    public function staff()
    {
        return $this->hasMany(RestaurantStaff::class);
    }

    public static function validServiceTypes(): array
    {
        return [
            'delivery',
            'dining',
            'takeaway',
            'both',
            'delivery_takeaway',
            'dining_takeaway',
            'all',
        ];
    }

    public function acceptsService(string $service): bool
    {
        $type = strtolower((string) ($this->restaurant_type ?? 'delivery'));

        return match ($service) {
            'delivery' => in_array($type, ['delivery', 'food', 'both', 'delivery_takeaway', 'all'], true),
            'dining' => in_array($type, ['dining', 'dine', 'both', 'dining_takeaway', 'all'], true),
            'takeaway' => in_array($type, ['takeaway', 'delivery_takeaway', 'dining_takeaway', 'all'], true),
            default => false,
        };
    }
    
    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function locationChangeRequests()
    {
        return $this->hasMany(RestaurantLocationChangeRequest::class);
    }
    
    public function favorites()
    {
        return $this->belongsToMany(User::class, 'user_favorites')->withTimestamps();
    }
    
    // ==================== Default Timings ====================
    
    /**
     * Get default weekly timings structure
     */
    public static function getDefaultWeeklyTimings(): array
    {
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $timings = [];
        
        foreach ($days as $day) {
            $timings[$day] = [
                'is_open' => true,
                'open_time' => '09:00',
                'close_time' => '22:00',
                'break_start' => null,
                'break_end' => null,
            ];
        }
        
        return $timings;
    }
    
    /**
     * Get default single day timing structure
     */
    public static function getDefaultDayTiming(): array
    {
        return [
            'is_open' => true,
            'open_time' => '09:00',
            'close_time' => '22:00',
            'break_start' => null,
            'break_end' => null,
        ];
    }
    
    // ==================== Timing & Status Methods ====================
    
    /**
     * Get weekly timings with defaults if not set
     */
    public function getWeeklyTimingsAttribute($value): array
    {
        $timings = $value ? (is_array($value) ? $value : json_decode($value, true)) : null;
        
        if (is_array($timings) && count($timings) > 0) {
            return $timings;
        }
        
        // Migrate from old open_time/close_time format
        if ($this->open_time && $this->close_time) {
            $defaultTimings = self::getDefaultWeeklyTimings();
            foreach ($defaultTimings as $day => $timing) {
                $defaultTimings[$day]['open_time'] = $this->open_time;
                $defaultTimings[$day]['close_time'] = $this->close_time;
            }
            return $defaultTimings;
        }
        
        return self::getDefaultWeeklyTimings();
    }
    
    /**
     * Set weekly timings with validation
     */
    public function setWeeklyTimingsAttribute($value)
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }
        
        if (is_array($value)) {
            // Validate and sanitize timings
            foreach ($value as $day => &$timing) {
                if (!isset($timing['is_open'])) {
                    $timing['is_open'] = true;
                }
                if (!isset($timing['open_time'])) {
                    $timing['open_time'] = '09:00';
                }
                if (!isset($timing['close_time'])) {
                    $timing['close_time'] = '22:00';
                }
            }
            $this->attributes['weekly_timings'] = json_encode($value);
        } else {
            $this->attributes['weekly_timings'] = null;
        }
    }
    
    /**
     * Get timings for a specific day
     */
    public function getTimingsForDay(string $day): array
    {
        $day = strtolower($day);
        $weeklyTimings = $this->weekly_timings;
        
        if (isset($weeklyTimings[$day])) {
            return $weeklyTimings[$day];
        }
        
        return self::getDefaultDayTiming();
    }
    
    /**
     * Update timings for a specific day
     */
    public function updateDayTimings(string $day, array $timings): bool
    {
        $day = strtolower($day);
        $weeklyTimings = $this->weekly_timings;
        $weeklyTimings[$day] = array_merge(self::getDefaultDayTiming(), $timings);
        
        return $this->update(['weekly_timings' => $weeklyTimings]);
    }
    
    /**
     * Check if restaurant is open at a specific date and time
     */
    public function isOpenAt(Carbon $datetime = null): bool
    {
        if (!$this->is_open) {
            return false;
        }
        
        $datetime = $datetime ?: Carbon::now($this->timezone ?? 'Asia/Kolkata');

        return $this->isScheduledOpenAt($datetime);
    }
    
    /**
     * Check if restaurant is open now (alias for isOpenAt)
     */
    public function isOpenNow(): bool
    {
        return $this->isOpenAt();
    }
    
    /**
     * Alias for isOpenNow
     */
    public function getIsOpenNowAttribute(): bool
    {
        return $this->isOpenNow();
    }
    
    /**
     * Check if restaurant should be open based on schedule (without manual override)
     */
    public function shouldBeOpenNow(): bool
    {
        $now = Carbon::now($this->timezone ?? 'Asia/Kolkata');
        $day = strtolower($now->format('l'));
        $currentTime = $now->format('H:i');
        
        $weeklyTimings = $this->weekly_timings;
        
        return $this->isScheduledOpenAt($now);
    }
    
    /**
     * Check if restaurant can accept orders at given time (considering lead time)
     */
    public function canAcceptOrderAt(Carbon $datetime = null): bool
    {
        if (!$this->is_open || !$this->isOpenAt($datetime)) {
            return false;
        }
        
        $datetime = $datetime ?: Carbon::now($this->timezone ?? 'Asia/Kolkata');
        $leadTime = $this->order_lead_time ?? 30; // minutes
        
        // Check if order can be prepared before closing
        $preparationDeadline = $datetime->copy()->addMinutes($leadTime);
        $day = strtolower($datetime->format('l'));
        $currentMinutes = $this->timeToMinutes($datetime->format('H:i'));
        
        $weeklyTimings = $this->weekly_timings;

        if (!isset($weeklyTimings[$day]) || !$weeklyTimings[$day]['is_open']) {
            $previousDay = strtolower($datetime->copy()->subDay()->format('l'));
            $previousTiming = $weeklyTimings[$previousDay] ?? null;
            if ($previousTiming && ($previousTiming['is_open'] ?? false)) {
                $previousOpenMinutes = $this->timeToMinutes($previousTiming['open_time']);
                $previousCloseMinutes = $this->timeToMinutes($previousTiming['close_time']);
                if ($previousCloseMinutes <= $previousOpenMinutes && $currentMinutes <= $previousCloseMinutes) {
                    $day = $previousDay;
                }
            }
        }

        if (!isset($weeklyTimings[$day]) || !$weeklyTimings[$day]['is_open']) {
            return false;
        }
        
        $openTime = $weeklyTimings[$day]['open_time'];
        $closeTime = $weeklyTimings[$day]['close_time'];
        $openTimeMinutes = $this->timeToMinutes($openTime);
        $closeTimeMinutes = $this->timeToMinutes($closeTime);
        $closeDateTime = Carbon::parse(
            $datetime->format('Y-m-d') . ' ' . $closeTime,
            $this->timezone ?? 'Asia/Kolkata'
        );

        if ($closeTimeMinutes <= $openTimeMinutes && $this->timeToMinutes($datetime->format('H:i')) >= $openTimeMinutes) {
            $closeDateTime->addDay();
        }
        
        return $preparationDeadline->lte($closeDateTime);
    }
    
    /**
     * Get next opening time
     */
    public function getNextOpeningTime(): ?Carbon
    {
        $now = Carbon::now($this->timezone ?? 'Asia/Kolkata');
        $weeklyTimings = $this->weekly_timings;
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        
        for ($i = 0; $i <= 7; $i++) {
            $checkDate = $now->copy()->addDays($i);
            $day = strtolower($checkDate->format('l'));
            
            if (isset($weeklyTimings[$day]) && $weeklyTimings[$day]['is_open']) {
                $openTime = $weeklyTimings[$day]['open_time'];
                $openDateTime = Carbon::parse(
                    $checkDate->format('Y-m-d') . ' ' . $openTime,
                    $this->timezone ?? 'Asia/Kolkata'
                );
                
                if ($openDateTime > $now || $i > 0) {
                    return $openDateTime;
                }
            }
        }
        
        return null;
    }

    public function getNextOpeningLabel(): ?string
    {
        $nextOpen = $this->getNextOpeningTime();
        if (! $nextOpen) {
            return null;
        }

        $now = Carbon::now($this->timezone ?? 'Asia/Kolkata');
        $prefix = $nextOpen->isSameDay($now)
            ? 'Opens today'
            : ($nextOpen->isSameDay($now->copy()->addDay()) ? 'Opens tomorrow' : 'Opens '.$nextOpen->format('D, d M'));

        return $prefix.' at '.$nextOpen->format('g:i A');
    }
    
    /**
     * Get next closing time
     */
    public function getNextClosingTime(): ?Carbon
    {
        $now = Carbon::now($this->timezone ?? 'Asia/Kolkata');
        $weeklyTimings = $this->weekly_timings;
        
        // Check if currently open
        if ($this->isOpenNow()) {
            $currentDay = strtolower($now->format('l'));
            if (isset($weeklyTimings[$currentDay])) {
                $closeTime = $weeklyTimings[$currentDay]['close_time'];
                return Carbon::parse(
                    $now->format('Y-m-d') . ' ' . $closeTime,
                    $this->timezone ?? 'Asia/Kolkata'
                );
            }
        }
        
        // Get next opening then closing
        $nextOpen = $this->getNextOpeningTime();
        if ($nextOpen) {
            $nextOpenDay = strtolower($nextOpen->format('l'));
            if (isset($weeklyTimings[$nextOpenDay])) {
                $closeTime = $weeklyTimings[$nextOpenDay]['close_time'];
                return Carbon::parse(
                    $nextOpen->format('Y-m-d') . ' ' . $closeTime,
                    $this->timezone ?? 'Asia/Kolkata'
                );
            }
        }
        
        return null;
    }
    
    /**
     * Auto sync restaurant status based on schedule
     */
    public function autoSyncStatus(): bool
    {
        $shouldBeOpen = $this->shouldBeOpenNow();
        
        if ($this->is_open != $shouldBeOpen) {
            $this->update(['is_open' => $shouldBeOpen]);
            return true;
        }
        
        return false;
    }
    
    // ==================== Helper Methods ====================
    
    /**
     * Convert time string to minutes
     */
    protected function timeToMinutes(string $time): int
    {
        $parts = explode(':', $time);
        return (int)$parts[0] * 60 + (int)($parts[1] ?? 0);
    }

    protected function isScheduledOpenAt(Carbon $datetime): bool
    {
        $day = strtolower($datetime->format('l'));
        $currentTime = $datetime->format('H:i');
        $currentMinutes = $this->timeToMinutes($currentTime);
        $weeklyTimings = $this->weekly_timings;

        $todayTiming = $weeklyTimings[$day] ?? null;
        if ($todayTiming && ($todayTiming['is_open'] ?? false)) {
            $openMinutes = $this->timeToMinutes($todayTiming['open_time']);
            $closeMinutes = $this->timeToMinutes($todayTiming['close_time']);
            $isTodayWindow = $closeMinutes > $openMinutes
                ? $this->minutesWithinWindow($currentMinutes, $openMinutes, $closeMinutes)
                : $currentMinutes >= $openMinutes;

            if ($isTodayWindow) {
                return ! $this->isWithinBreak($currentTime, $todayTiming);
            }
        }

        $previousDay = strtolower($datetime->copy()->subDay()->format('l'));
        $previousTiming = $weeklyTimings[$previousDay] ?? null;
        if ($previousTiming && ($previousTiming['is_open'] ?? false)) {
            $previousOpenMinutes = $this->timeToMinutes($previousTiming['open_time']);
            $previousCloseMinutes = $this->timeToMinutes($previousTiming['close_time']);
            if ($previousCloseMinutes <= $previousOpenMinutes && $currentMinutes <= $previousCloseMinutes) {
                return ! $this->isWithinBreak($currentTime, $previousTiming);
            }
        }

        return false;
    }

    protected function timeWithinWindow(string $currentTime, string $startTime, string $endTime): bool
    {
        return $this->minutesWithinWindow(
            $this->timeToMinutes($currentTime),
            $this->timeToMinutes($startTime),
            $this->timeToMinutes($endTime)
        );
    }

    protected function minutesWithinWindow(int $currentMinutes, int $startMinutes, int $endMinutes): bool
    {
        if ($startMinutes === $endMinutes) {
            return true;
        }

        if ($endMinutes > $startMinutes) {
            return $currentMinutes >= $startMinutes && $currentMinutes <= $endMinutes;
        }

        return $currentMinutes >= $startMinutes || $currentMinutes <= $endMinutes;
    }

    protected function isWithinBreak(string $currentTime, array $dayTiming): bool
    {
        if (empty($dayTiming['break_start']) || empty($dayTiming['break_end'])) {
            return false;
        }

        return $this->timeWithinWindow($currentTime, $dayTiming['break_start'], $dayTiming['break_end']);
    }
    
    /**
     * Get formatted address
     */
    public function getFormattedAddressAttribute(): string
    {
        $parts = array_filter([$this->address, $this->city, $this->state, $this->pincode]);
        return implode(', ', $parts);
    }
    
    /**
     * Get rating percentage (e.g., 4.5 -> 90%)
     */
    public function getRatingPercentageAttribute(): float
    {
        if ($this->rating <= 0) {
            return 0;
        }
        return ($this->rating / 5) * 100;
    }
    
    /**
     * Calculate average rating with review count
     */
    public function calculateAverageRating(): void
    {
        $avg = $this->reviews()->avg('rating') ?? 0;
        $count = $this->reviews()->count();
        
        $this->update([
            'rating' => round($avg, 1),
            'total_ratings' => $count
        ]);
    }
    
    /**
     * Get current day's schedule summary
     */
    public function getTodaySchedule(): array
    {
        $now = Carbon::now($this->timezone ?? 'Asia/Kolkata');
        $day = strtolower($now->format('l'));
        
        return $this->getTimingsForDay($day);
    }
    
    /**
     * Get full week schedule
     */
    public function getFullWeekSchedule(): array
    {
        $schedule = [];
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $dayNames = [
            'monday' => 'Monday',
            'tuesday' => 'Tuesday',
            'wednesday' => 'Wednesday',
            'thursday' => 'Thursday',
            'friday' => 'Friday',
            'saturday' => 'Saturday',
            'sunday' => 'Sunday'
        ];
        
        foreach ($days as $day) {
            $timings = $this->getTimingsForDay($day);
            $schedule[$day] = [
                'day_name' => $dayNames[$day],
                'is_open' => $timings['is_open'],
                'open_time' => $timings['open_time'],
                'close_time' => $timings['close_time'],
                'break_start' => $timings['break_start'],
                'break_end' => $timings['break_end'],
                'open_time_formatted' => $this->formatTime12Hour($timings['open_time']),
                'close_time_formatted' => $this->formatTime12Hour($timings['close_time']),
            ];
        }
        
        return $schedule;
    }
    
    /**
     * Format time to 12-hour format
     */
    public function formatTime12Hour(?string $time): string
    {
        if (!$time) {
            return '--:-- --';
        }
        
        $parts = explode(':', $time);
        $hour = (int)$parts[0];
        $minute = $parts[1] ?? '00';
        $period = $hour >= 12 ? 'PM' : 'AM';
        $hour12 = $hour % 12;
        $hour12 = $hour12 ?: 12;
        
        return sprintf('%d:%s %s', $hour12, $minute, $period);
    }
    
    // ==================== Scope Methods ====================
    
    /**
     * Scope a query to only include open restaurants
     */
    public function scopeOpen($query)
    {
        return $query->where('is_open', true);
    }
    
    /**
     * Scope a query to only include verified restaurants
     */
    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }
    
    /**
     * Scope a query to only include featured restaurants
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }
    
    /**
     * Scope a query to only include pure veg restaurants
     */
    public function scopePureVeg($query)
    {
        return $query->where('is_pure_veg', true);
    }
    
    /**
     * Scope a query to search restaurants
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where(function($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('city', 'like', "%{$search}%")
              ->orWhere('cuisine', 'like', "%{$search}%");
        });
    }
    
    /**
     * Scope a query to filter by city
     */
    public function scopeInCity($query, string $city)
    {
        return $query->where('city', 'like', "%{$city}%");
    }
    
    /**
     * Scope a query to filter by cuisine
     */
    public function scopeWithCuisine($query, string $cuisine)
    {
        return $query->where('cuisine', 'like', "%{$cuisine}%");
    }
    
    /**
     * Scope a query to filter by minimum rating
     */
    public function scopeMinRating($query, float $rating)
    {
        return $query->where('rating', '>=', $rating);
    }
    
    /**
     * Scope a query to get restaurants near a location
     */
    public function scopeNearby($query, float $latitude, float $longitude, float $radius = 10)
    {
        $haversine = "(6371 * acos(cos(radians($latitude)) 
            * cos(radians(latitude)) 
            * cos(radians(longitude) - radians($longitude)) 
            + sin(radians($latitude)) 
            * sin(radians(latitude))))";
        
        return $query->select('*')
            ->selectRaw("{$haversine} AS distance")
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereRaw("{$haversine} <= ?", [$radius])
            ->whereRaw("{$haversine} <= COALESCE(NULLIF(delivery_radius, 0), ?)", [$radius])
            ->orderBy('distance');
    }
    
    // ==================== Statistics Methods ====================
    
    /**
     * Get order statistics
     */
    public function getOrderStatistics(): array
    {
        return [
            'total_orders' => $this->orders()->count(),
            'completed_orders' => $this->orders()->where('status', 'delivered')->count(),
            'cancelled_orders' => $this->orders()->where('status', 'cancelled')->count(),
            'pending_orders' => $this->orders()->whereIn('status', ['pending', 'confirmed'])->count(),
            'total_revenue' => $this->orders()->where('status', 'delivered')->sum('total'),
            'average_order_value' => $this->orders()->where('status', 'delivered')->avg('total') ?? 0,
        ];
    }
    
    /**
     * Get today's order summary
     */
    public function getTodayOrderSummary(): array
    {
        $today = Carbon::today($this->timezone ?? 'Asia/Kolkata');
        
        return [
            'orders_count' => $this->orders()->whereDate('created_at', $today)->count(),
            'revenue' => $this->orders()->whereDate('created_at', $today)->where('status', 'delivered')->sum('total'),
            'pending' => $this->orders()->whereDate('created_at', $today)->whereIn('status', ['pending', 'confirmed'])->count(),
        ];
    }
    
    /**
     * Get default printer setting
     */
    public function getDefaultPrinter()
    {
        return $this->printerSettings()->where('is_default', true)->first();
    }
    
    /**
     * Check if restaurant has active promotion
     */
    public function hasActivePromo(): bool
    {
        return $this->promos()
            ->where('is_active', true)
            ->where(function($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->exists();
    }
    
    /**
     * Get active promos
     */
    public function getActivePromos()
    {
        return $this->promos()
            ->where('is_active', true)
            ->where(function($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->get();
    }

    // Add this method to App\Models\Restaurant.php

    /**
     * Check if restaurant is open for a specific day
     */
    public function isOpenNowForDay(string $day): bool
    {
        $day = strtolower($day);
        $now = Carbon::now($this->timezone ?? 'Asia/Kolkata');
        $currentDay = strtolower($now->format('l'));
        
        if ($currentDay !== $day) {
            return false;
        }

        return $this->isOpenAt($now);
    }
}
