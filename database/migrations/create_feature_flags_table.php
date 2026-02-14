<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_flags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            $table->boolean('enabled')->default(false);
            $table->string('tenant_id')->nullable()->index();
            $table->text('description')->nullable();
            $table->json('metadata')->nullable(); // For percentage rollouts, user segments, etc.
            $table->timestamps();

            // Unique constraint: one flag per name per tenant
            $table->unique(['name', 'tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_flags');
    }
};