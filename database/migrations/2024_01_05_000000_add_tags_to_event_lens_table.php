<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = config('event-lens.database_connection');

        Schema::connection($connection)->table('event_lens_events', function (Blueprint $table) {
            $table->json('tags')->nullable()->after('model_id');
        });
    }

    public function down(): void
    {
        $connection = config('event-lens.database_connection');

        Schema::connection($connection)->table('event_lens_events', function (Blueprint $table) {
            $table->dropColumn('tags');
        });
    }
};
