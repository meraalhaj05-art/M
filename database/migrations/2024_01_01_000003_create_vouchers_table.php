<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->decimal('amount', 15, 2);
            $table->integer('max_uses')->default(1);
            $table->integer('used_count')->default(0);
            $table->timestamps();
        });
    }

    public function down() {
        Schema::dropIfExists('vouchers');
    }
};
