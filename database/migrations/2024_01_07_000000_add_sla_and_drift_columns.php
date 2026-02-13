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
            $table->boolean('is_sla_breach')->default(false)->after('is_storm');
            $table->boolean('has_drift')->default(false)->after('is_sla_breach');
            $table->json('drift_details')->nullable()->after('has_drift');
            $table->index('is_sla_breach');
        });
    }

    public function down(): void
    {
        $connection = config('event-lens.database_connection');

        Schema::connection($connection)->table('event_lens_events', function (Blueprint $table) {
            $table->dropIndex(['is_sla_breach']);
            $table->dropColumn(['is_sla_breach', 'has_drift', 'drift_details']);
        });
    }
};
