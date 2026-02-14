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

        if (! Schema::connection($connection)->hasTable('event_lens_events')) {
            Schema::connection($connection)->create('event_lens_events', function (Blueprint $table) {
                $table->id();
                $table->uuid('event_id')->unique();
                $table->uuid('correlation_id')->index();
                $table->uuid('parent_event_id')->nullable()->index();

                $table->string('event_name');
                $table->string('listener_name');

                $table->json('payload')->nullable();
                $table->json('side_effects')->nullable();
                $table->json('model_changes')->nullable();
                $table->string('exception', 2048)->nullable();
                $table->nullableMorphs('model');
                $table->json('tags')->nullable();

                $table->boolean('is_storm')->default(false)->index();
                $table->boolean('is_sla_breach')->default(false)->index();
                $table->boolean('has_drift')->default(false);
                $table->json('drift_details')->nullable();
                $table->boolean('is_nplus1')->default(false)->index();

                $table->double('execution_time_ms');
                $table->timestamp('happened_at');
                $table->timestamps();

                $table->index(['event_name', 'created_at']);
                $table->index('happened_at');
                $table->index('execution_time_ms');
            });
        }

        if (! Schema::connection($connection)->hasTable('event_lens_schema_baselines')) {
            Schema::connection($connection)->create('event_lens_schema_baselines', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('event_class')->unique();
                $table->string('fingerprint');
                $table->json('schema');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Intentionally empty. When incremental migrations coexist, they
        // handle their own rollback. On a fresh install where only this
        // consolidated migration created the tables, use:
        //   php artisan migrate:fresh
    }
};
