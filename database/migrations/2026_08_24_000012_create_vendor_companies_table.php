<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_companies', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('abbreviation', 30)->unique();
            $table->enum('category', ['REGULAR', 'NON_REGULAR'])->index();
            $table->string('remarks')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_companies');
    }
};
