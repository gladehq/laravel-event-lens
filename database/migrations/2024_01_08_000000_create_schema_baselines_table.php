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

        Schema::connection($connection)->create('event_lens_schema_baselines', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('event_class')->unique();
            $table->string('fingerprint');
            $table->json('schema');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $connection = config('event-lens.database_connection');

        Schema::connection($connection)->dropIfExists('event_lens_schema_baselines');
    }
};
