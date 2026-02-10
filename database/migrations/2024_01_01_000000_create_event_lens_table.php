<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $connection = config('event-lens.database_connection');

        Schema::connection($connection)->create('event_lens_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->uuid('correlation_id')->index();
            $table->uuid('parent_event_id')->nullable()->index();
            
            $table->string('event_name');
            $table->string('listener_name');
            
            $table->json('payload')->nullable();
            $table->json('side_effects')->nullable(); // db_count, mail_count
            $table->json('model_changes')->nullable(); // dirty states
            
            $table->double('execution_time_ms');
            $table->timestamp('happened_at');
            $table->timestamps();

            $table->index(['event_name', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = config('event-lens.database_connection');
        Schema::connection($connection)->dropIfExists('event_lens_events');
    }
};
