<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('user_id'); 
            $table->enum('type', ['deposit', 'withdraw', 'transfer_to_game', 'gift']);
            $table->decimal('amount', 15, 2);
            $table->string('method')->nullable(); 
            $table->string('details')->nullable(); 
            $table->string('proof_image')->nullable(); 
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamps();
        });
    }

    public function down() {
        Schema::dropIfExists('transactions');
    }
};
