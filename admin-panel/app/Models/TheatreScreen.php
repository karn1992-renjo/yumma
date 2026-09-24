<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A single auditorium within a Theatre. `seat_map_config` is the full
 * drag-and-drop layout saved by the seat-map builder (row labels, seat
 * counts, curve control points, aisle gaps, section tags) -- built in a
 * later phase. This never tracks seat availability/booking; a seat is only
 * ever a delivery-location label the customer picks, not inventory.
 */
class TheatreScreen extends Model
{
    protected $fillable = [
        'theatre_id',
        'name',
        'seat_map_config',
        'is_active',
    ];

    protected $casts = [
        'seat_map_config' => 'array',
        'is_active' => 'boolean',
    ];

    public function theatre(): BelongsTo
    {
        return $this->belongsTo(Theatre::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'screen_id');
    }

    public function shows(): HasMany
    {
        return $this->hasMany(TheatreShow::class, 'screen_id');
    }

    public function hasSeatMap(): bool
    {
        return ! empty($this->seat_map_config);
    }

    /**
     * The seat labels this screen's map defines, grouped by row.
     *
     * The labeling scheme -- number only the non-gap slots, sequentially,
     * per row -- is the contract shared by the owner's drag-and-drop
     * builder (which implements it in JS, in
     * theatre/screen-seat-map.blade.php), the customer's seat picker, the
     * printed QR stickers and the checkout validator. It lives here so
     * every PHP caller agrees; the JS copy is the one deliberate
     * duplicate, and the two must be changed together.
     */
    public function seatRows(): array
    {
        $rows = [];

        foreach ($this->seat_map_config['rows'] ?? [] as $row) {
            $gaps = $row['gaps'] ?? [];
            $seatCount = (int) ($row['seat_count'] ?? 0);
            $rowLabel = (string) ($row['label'] ?? '');
            $seats = [];
            $seatNumber = 0;

            for ($i = 0; $i < $seatCount; $i++) {
                if (in_array($i, $gaps, true)) {
                    continue;
                }
                $seatNumber++;
                $seats[] = $rowLabel . $seatNumber;
            }

            if ($seats !== []) {
                $rows[] = [
                    'label' => $rowLabel,
                    'section' => $row['section'] ?? 'standard',
                    'seats' => $seats,
                ];
            }
        }

        return $rows;
    }

    public function seatLabels(): array
    {
        return collect($this->seatRows())->pluck('seats')->flatten()->all();
    }

    public function hasSeat(string $seatLabel): bool
    {
        $needle = strtoupper(trim($seatLabel));

        foreach ($this->seatLabels() as $label) {
            if (strtoupper($label) === $needle) {
                return true;
            }
        }

        return false;
    }
}
