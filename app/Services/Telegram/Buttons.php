namespace App\Services\Telegram;

class Buttons {
    public static function mainMenu() {
        return json_encode([
            'keyboard' => [
                [['text' => "⚡ حساب ايشانسي وشحنه ⚡"]],
                [['text' => "📥 شحن رصيد في البوت"], ['text' => "📤 سحب رصيد من البوت"]],
                [['text' => "🎁 إهداء صديق"], ['text' => "🏆 كود جائزة"]],
                [['text' => "💰 الإحالات"]],
                [['text' => "💬 إرسال رسالة للدعم"]],
                [['text' => "↗️ ايشانسي"]], // سنبرمج الرابط في الـ Controller
                [['text' => "⚠️ شروط الاستخدام"]]
            ],
            'resize_keyboard' => true
        ]);
    }

    public static function adminAction($transactionId) {
        return json_encode([
            'inline_keyboard' => [
                [
                    ['text' => "✅ قبول", 'callback_data' => "approve_{$transactionId}"],
                    ['text' => "❌ رفض", 'callback_data' => "reject_{$transactionId}"]
                ]
            ]
        ]);
    }
}
