<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'telegram_id')) {
                $table->string('telegram_id')->unique()->nullable();
                $table->decimal('balance', 15, 2)->default(0);
                $table->string('ichancey_username')->nullable();
                $table->string('ichancey_password')->nullable();
                $table->string('referrer_id')->nullable();
                $table->string('referral_code')->unique()->nullable();
                $table->string('step')->default('none');
                $table->string('pending_details')->nullable();
                $table->decimal('pending_amount', 15, 2)->default(0);
            }
        });
    }

    public function down() {
        Schema::dropIfExists('users');
    }
};
