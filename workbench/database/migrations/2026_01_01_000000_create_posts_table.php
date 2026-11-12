<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('posts', static function (Blueprint $table): void {
            $table->id();
            $table->json('title')->nullable();
            $table->json('body')->nullable();
            $table->timestamps();
        });
    }
};
