use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::table('users', function (Blueprint $table) {
            $table->string('telegram_id')->unique()->nullable();
            $table->decimal('balance', 15, 2)->default(0); // رصيد البوت (ليرة سورية مثلاً)
            $table->string('ichancey_username')->nullable();
            $table->string('ichancey_password')->nullable();
            $table->string('referrer_id')->nullable(); // آيدي الشخص اللي دعاه
            $table->string('referral_code')->unique()->nullable(); // كود إحالته الخاص
            $table->string('step')->default('none'); // لمتابعة حالة المستخدم (مثلاً: ينتظر إرسال الصورة)
        });
    }
};
