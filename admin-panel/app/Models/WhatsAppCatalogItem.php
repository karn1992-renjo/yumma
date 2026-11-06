<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppCatalogItem extends Model
{
    protected $table = 'whatsapp_catalog_items';

    protected $fillable = ['menu_item_id', 'restaurant_id', 'retailer_id', 'content_hash', 'status', 'error', 'synced_at'];

    protected $casts = ['synced_at' => 'datetime'];

    /** "mi_123" is how a menu item is named inside the WhatsApp catalog and the customer's cart. */
    public static function retailerId(int $menuItemId): string
    {
        return 'mi_' . $menuItemId;
    }

    public static function menuItemIdFrom(string $retailerId): ?int
    {
        return preg_match('/^mi_(\d+)$/', $retailerId, $m) ? (int) $m[1] : null;
    }
}
