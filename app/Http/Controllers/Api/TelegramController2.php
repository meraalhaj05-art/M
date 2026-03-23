<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Transaction;
use App\Models\Voucher;
use App\Services\IchanceyService;
use Illuminate\Support\Facades\Http;

class TelegramController extends Controller {

    // --- الإعدادات الأساسية (البيانات اللي عطيتني ياها) ---
    protected $botToken = "8634873247:AAE5hVbQ6MuB0yVhpMpC7VLuBzPjxupXujs"; 
    protected $adminChatId = "8583775415"; 

    public function handle(Request $request) {
        $update = $request->all();

        // 1. معالجة الأزرار (Callback Queries)
        if (isset($update['callback_query'])) {
            return $this->handleCallback($update['callback_query']);
        }

        if (!isset($update['message'])) return;
        $message = $update['message'];
        $chatId = $message['chat']['id'];
        $text = $message['text'] ?? '';

        // 2. تسجيل أو جلب المستخدم
        $user = User::firstOrCreate(
            ['telegram_id' => $chatId],
            ['balance' => 0, 'referral_code' => 'REF' . $chatId, 'step' => 'none']
        );

        // 3. معالجة الصور (إثباتات الشحن اليدوي)
        if (isset($message['photo'])) {
            return $this->handlePhoto($user, $message);
        }

        // 4. نظام الحالات (Steps) - مع الربط التلقائي
        if ($user->step !== 'none') {
            return $this->handleSteps($user, $text);
        }

        // 5. الأوامر الرئيسية (القائمة)
        if (strpos($text, '/start') === 0) {
            return $this->processStart($user, $text);
        }

        switch ($text) {
            case '/admin': 
                return ($chatId == $this->adminChatId) ? $this->adminPanel($chatId) : null;
            case '⚡ حساب ايشانسي وشحنه ⚡': return $this->ichanceyMenu($user);
            case '📥 شحن رصيد في البوت': return $this->depositMenu($chatId);
            case '📤 سحب رصيد من البوت': 
                $user->update(['step' => 'withdraw_method']);
                return $this->sendMessage($chatId, "📤 أرسل وسيلة السحب (مثلاً: سيريتل كاش ورقمك):");
            case '🎁 إهداء صديق': 
                $user->update(['step' => 'gift_id']);
                return $this->sendMessage($chatId, "🎁 أرسل آيدي (ID) الصديق:");
            case '🏆 كود جائزة': 
                $user->update(['step' => 'use_code']);
                return $this->sendMessage($chatId, "🏆 أدخل كود الجائزة:");
            case '💰 الإحالات': return $this->referralInfo($user);
            case '💬 إرسال رسالة للدعم': return $this->sendMessage($chatId, "👨‍💻 الدعم الفني: @YOUR_SUPPORT");
            default: return $this->sendWelcome($user);
        }
    }

    // --- نظام معالجة الخطوات (الربط التلقائي مع الموقع) ---
    private function handleSteps($user, $text) {
        $ichService = new IchanceyService();

        // 1. إنشاء حساب تلقائي في موقع إيشانسي
        if ($user->step == 'create_ich_user') {
            $user->update(['ichancey_username' => $text, 'step' => 'create_ich_pass']);
            return $this->sendMessage($user->telegram_id, "🔐 أرسل كلمة المرور المطلوبة للحساب:");
        }
        if ($user->step == 'create_ich_pass') {
            $result = $ichService->createPlayer($user->ichancey_username, $text);
            $user->update(['step' => 'none']);
            
            if (isset($result['success']) && $result['success']) {
                $user->update(['ichancey_password' => $text]);
                return $this->sendMessage($user->telegram_id, "✅ تم إنشاء حسابك في الموقع بنجاح!\n👤 اليوزر: `{$user->ichancey_username}`");
            }
            return $this->sendMessage($user->telegram_id, "❌ فشل إنشاء الحساب: " . ($result['message'] ?? 'خطأ تقني'));
        }

        // 2. شحن اللعبة من رصيد البوت (تلقائي)
        if ($user->step == 'topup_game_amount') {
            $amount = (float)$text;
            if ($user->balance < $amount) {
                $user->update(['step' => 'none']);
                return $this->sendMessage($user->telegram_id, "❌ رصيدك في البوت غير كافٍ.");
            }
            $result = $ichService->depositToPlayer($user->ichancey_username, $amount);
            $user->update(['step' => 'none']);

            if (isset($result['success']) && $result['success']) {
                $user->decrement('balance', $amount);
                return $this->sendMessage($user->telegram_id, "✅ تم شحن $amount ليرة لحسابك في اللعبة فوراً!");
            }
            return $this->sendMessage($user->telegram_id, "❌ فشل الشحن التلقائي، حاول لاحقاً.");
        }

        // 3. إذاعة رسالة (أدمن)
        if ($user->step == 'waiting_broadcast' && $user->telegram_id == $this->adminChatId) {
            foreach (User::all() as $u) { $this->sendMessage($u->telegram_id, "📢 **إعلان:**\n\n" . $text); }
            $user->update(['step' => 'none']);
            return $this->sendMessage($this->adminChatId, "✅ تم الإرسال للجميع.");
        }

        $user->update(['step' => 'none']);
    }

    // --- وظائف المساعدة والترحيب ---
    private function sendWelcome($user) {
        $text = "🎯 **أهلاً بك في بوت إيشانسي**\n💰 رصيدك: **{$user->balance} SYP**\n🆔 آيديك: `{$user->telegram_id}`";
        return $this->sendMessage($user->telegram_id, $text, $this->mainKeyboard());
    }

    private function mainKeyboard() {
        return json_encode(['keyboard' => [
            [['text' => "⚡ حساب ايشانسي وشحنه ⚡"]],
            [['text' => "📥 شحن رصيد في البوت"], ['text' => "📤 سحب رصيد من البوت"]],
            [['text' => "🎁 إهداء صديق"], ['text' => "🏆 كود جائزة"]],
            [['text' => "💰 الإحالات"]],
            [['text' => "💬 إرسال رسالة للدعم"]]
        ], 'resize_keyboard' => true]);
    }

    private function ichanceyMenu($user) {
        $status = $user->ichancey_username ? "✅ مربوط ({$user->ichancey_username})" : "❌ غير مربوط";
        $text = "⚙️ **إعدادات حساب إيشانسي**\nحالة الحساب: $status";
        $keys = json_encode(['inline_keyboard' => [
            [['text' => "➕ إنشاء حساب جديد", 'callback_data' => "ich_create"]],
            [['text' => "💰 شحن حساب اللعبة", 'callback_data' => "ich_topup"]]
        ]]);
        return $this->sendMessage($user->telegram_id, $text, $keys);
    }

    private function handleCallback($callback) {
        $data = $callback['data'];
        $chatId = $callback['message']['chat']['id'];
        $user = User::where('telegram_id', $chatId)->first();

        if ($data == "ich_create") {
            $user->update(['step' => 'create_ich_user']);
            return $this->sendMessage($chatId, "👤 أرسل اسم المستخدم (اليوزر) الذي تريده:");
        }
        if ($data == "ich_topup") {
            if (!$user->ichancey_username) return $this->sendMessage($chatId, "⚠️ اربط حسابك أولاً!");
            $user->update(['step' => 'topup_game_amount']);
            return $this->sendMessage($chatId, "💰 كم المبلغ الذي تريد تحويله من رصيد البوت إلى اللعبة؟");
        }
    }

    private function sendMessage($chatId, $text, $keyboard = null) {
        return Http::post("https://api.telegram.org/bot{$this->botToken}/sendMessage", [
            'chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'HTML', 'reply_markup' => $keyboard
        ]);
    }

    private function adminPanel($chatId) {
        $keys = json_encode(['inline_keyboard' => [[['text' => "📢 إذاعة رسالة", 'callback_data' => "admin_broadcast"]]]]);
        return $this->sendMessage($chatId, "🛠 لوحة تحكم الأدمن:", $keys);
    }

    private function referralInfo($user) {
        $count = User::where('referrer_id', $user->telegram_id)->count();
        $link = "https://t.me/YourBot?start=REF" . $user->telegram_id;
        return $this->sendMessage($user->telegram_id, "💰 **الإحالات**\n👥 المدعوين: $count\n🔗 رابطك: `$link` ");
    }
    
    private function processStart($user, $text) {
        $parts = explode(' ', $text);
        if (count($parts) > 1 && $user->wasRecentlyCreated) {
            $refId = str_replace('REF', '', $parts[1]);
            $user->update(['referrer_id' => $refId]);
            $this->sendMessage($refId, "🔔 مستخدم جديد دخل عبر رابطك!");
        }
        return $this->sendWelcome($user);
    }
}
