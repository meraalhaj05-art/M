namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Telegram\Buttons;
use App\Models\User;
use App\Models\Transaction;
use Illuminate\Support\Facades\Http;

class TelegramController extends Controller {
    protected $botToken = "YOUR_BOT_TOKEN_HERE"; // حط التوكن تبعك هون

    public function handle(Request $request) {
        $update = $request->all();
        if (!isset($update['message'])) return;

        $message = $update['message'];
        $chatId = $message['chat']['id'];
        $text = $message['text'] ?? '';

        // 1. التحقق من الاشتراك الإجباري (وهمي حالياً سنربطه بجدول الإعدادات لاحقاً)
        if (!$this->checkSubscription($chatId)) {
            return $this->sendMessage($chatId, "⚠️ عذراً عزيزي، يجب عليك الاشتراك في القنوات أولاً لتتمكن من استخدام البوت.");
        }

        // 2. معالجة الأوامر
        switch ($text) {
            case '/start':
                $this->handleStart($chatId, $message);
                break;
            case '⚡ حساب ايشانسي وشحنه ⚡':
                $this->handleIchanceyMenu($chatId);
                break;
            case '📥 شحن رصيد في البوت':
                $this->askDepositMethod($chatId);
                break;
            // سنضيف باقي الحالات هنا...
        }
    }

    private function sendMessage($chatId, $text, $keyboard = null) {
        $data = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'HTML'];
        if ($keyboard) $data['reply_markup'] = $keyboard;
        
        return Http::post("https://api.telegram.org/bot{$this->botToken}/sendMessage", $data);
    }
}
