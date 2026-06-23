<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the "categories" table (food types: Pizza, Burgers, Drinks, ...).
     */
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');                 // category name
            $table->string('image')->nullable();    // path to an icon/image
            $table->timestamps();
        });
    }

    /**
     * Drop the table when rolling back.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
