<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('banners', function (Blueprint $table) {
            // redirect_type='app_screen' -- a named in-app destination that
            // isn't tied to any specific restaurant/category/menu-item row
            // (Offers, Search, Gold membership, ...). See
            // BannerController::normalizeRedirectTarget() and the customer
            // app's _handleBannerTap().
            if (! Schema::hasColumn('banners', 'redirect_screen')) {
                $table->string('redirect_screen', 32)->nullable()->after('redirect_menu_item_id');
            }
        });
    }

    public function down()
    {
        Schema::table('banners', function (Blueprint $table) {
            if (Schema::hasColumn('banners', 'redirect_screen')) {
                $table->dropColumn('redirect_screen');
            }
        });
    }
};
