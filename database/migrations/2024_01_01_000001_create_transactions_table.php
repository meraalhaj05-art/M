Schema::create('transactions', function (Blueprint $table) {
    $table->id();
    $table->string('user_id'); // telegram_id
    $table->enum('type', ['deposit', 'withdraw', 'transfer_to_game', 'gift']);
    $table->decimal('amount', 15, 2);
    $table->string('method')->nullable(); // Syritel, Sham, USDT
    $table->string('details')->nullable(); // رقم العملية أو عنوان المحفظة
    $table->string('proof_image')->nullable(); // رابط الصورة المرسلة للأدمن
    $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
    $table->timestamps();
});
