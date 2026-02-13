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
            $table->boolean('is_storm')->default(false)->after('tags');
            $table->index('is_storm');
        });
    }

    public function down(): void
    {
        $connection = config('event-lens.database_connection');

        Schema::connection($connection)->table('event_lens_events', function (Blueprint $table) {
            $table->dropIndex(['is_storm']);
            $table->dropColumn('is_storm');
        });
    }
};
