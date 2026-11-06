<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Marketing / announcement templates written in admin. The text is submitted to the provider
        // for approval; once approved, its identifier (Gupshup template ID, Twilio HX…, Meta name…) is saved here.
        Schema::create('whatsapp_broadcast_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);                         // short label, e.g. "Weekend offer"
            $table->string('template_name', 80);                // provider-safe name, e.g. weekend_offer
            $table->text('body');                               // text with {{1}} {{2}} ... variables
            $table->json('variables')->nullable();              // what each {{n}} is: a {token} or fixed text
            $table->string('language', 10)->default('en');
            $table->string('provider_template_id')->nullable(); // set after the provider approves it
            $table->string('status', 12)->default('draft');     // draft | submitted | approved | rejected
            $table->string('notes', 500)->nullable();           // e.g. the provider's rejection reason
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_broadcast_templates');
    }
};
