<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expedition_requests', function (Blueprint $table) {
            $table->string('pr_number', 100)->nullable()->after('request_number')->index();
            $table->string('po_number', 100)->nullable()->after('pr_number')->index();
        });
    }

    public function down(): void
    {
        Schema::table('expedition_requests', fn (Blueprint $table) => $table->dropColumn(['pr_number', 'po_number']));
    }
};
