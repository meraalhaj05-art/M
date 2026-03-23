<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Transaction;
use App\Models\Voucher;
use Illuminate\Support\Facades\Http;

class TelegramController extends Controller {

    // --- الإعدادات (حط بياناتك هون) ---
    
protected $botToken = "8634873247:AAE5hVbQ6MuB0yVhpMpC7VLuBzPjxupXujs";
protected $adminChatId = "8583775415";
    public function handle(Request $request) {
        $update = $request->all();

        // معالجة الأزرار (Callback)
        if (isset($update['callback_query'])) {
            return $this->handleCallback($update['callback_query']);
        }

        if (!isset($update['message'])) return;
        $message = $update['message'];
        $chatId = $message['chat']['id'];
        $text = $message['text'] ?? '';

        // تسجيل المستخدم أو جلبه
        $user = User::firstOrCreate(
            ['telegram_id' => $chatId],
            ['balance' => 0, 'referral_code' => 'REF' . $chatId, 'step' => 'none']
        );

        // معالجة الصور (إثباتات الشحن)
        if (isset($message['photo'])) {
            return $this->handlePhoto($user, $message);
        }

        // نظام الحالات (Steps)
        if ($user->step !== 'none') {
            return $this->handleSteps($user, $text);
        }

        // الأوامر الرئيسية
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
                return $this->sendMessage($chatId, "📤 اختر وسيلة السحب وأرسل التفاصيل (رقم الكاش أو عنوان المحفظة):");
            case '🎁 إهداء صديق': 
                $user->update(['step' => 'gift_id']);
                return $this->sendMessage($chatId, "🎁 أرسل آيدي (ID) الصديق الذي تريد إهداءه:");
            case '🏆 كود جائزة': 
                $user->update(['step' => 'use_code']);
                return $this->sendMessage($chatId, "🏆 أدخل كود الجائزة الخاص بك:");
            case '💰 الإحالات': return $this->referralInfo($user);
            case '💬 إرسال رسالة للدعم': return $this->sendMessage($chatId, "👨‍💻 للتواصل مع الدعم الفني: @YOUR_ID");
            case '↗️ ايشانسي': return $this->sendMessage($chatId, "الموقع الرسمي: www.ichancey.com");
            case '⚠️ شروط الاستخدام': return $this->sendMessage($chatId, "📜 هنا تكتب شروط البوت الخاصة بك...");
            default: return $this->sendWelcome($user);
        }
    }

    // --- منطق الإحالات والبدء ---
    private function processStart($user, $text) {
        $parts = explode(' ', $text);
        if (count($parts) > 1 && $user->wasRecentlyCreated) {
            $refId = str_replace('REF', '', $parts[1]);
            $user->update(['referrer_id' => $refId]);
            $this->sendMessage($refId, "🔔 مستخدم جديد دخل عبر رابطك!");
        }
        return $this->sendWelcome($user);
    }

    // --- نظام معالجة الخطوات (The Brain) ---
    private function handleSteps($user, $text) {
        // إذاعة رسالة (للأدمن فقط)
        if ($user->step == 'waiting_broadcast' && $user->telegram_id == $this->adminChatId) {
            $allUsers = User::all();
            foreach ($allUsers as $u) { $this->sendMessage($u->telegram_id, "📢 **إعلان هام:**\n\n" . $text); }
            $user->update(['step' => 'none']);
            return $this->sendMessage($this->adminChatId, "✅ تم الإرسال لـ " . $allUsers->count() . " مستخدم.");
        }

        // سحب الرصيد
        if ($user->step == 'withdraw_method') {
            $user->update(['pending_details' => $text, 'step' => 'withdraw_amount']);
            return $this->sendMessage($user->telegram_id, "💰 كم المبلغ الذي تريد سحبه؟");
        }
        if ($user->step == 'withdraw_amount') {
            $amount = (float)$text;
            if ($amount > $user->balance) {
                $user->update(['step' => 'none']);
                return $this->sendMessage($user->telegram_id, "❌ رصيدك غير كافٍ!");
            }
            Transaction::create(['user_id' => $user->telegram_id, 'type' => 'withdraw', 'amount' => $amount, 'details' => $user->pending_details, 'status' => 'pending']);
            $user->update(['step' => 'none']);
            $this->sendMessage($this->adminChatId, "📤 طلب سحب جديد بقيمة $amount من $user->telegram_id");
            return $this->sendMessage($user->telegram_id, "⏳ تم تقديم الطلب بنجاح.");
        }

        // إهداء صديق
        if ($user->step == 'gift_id') {
            $user->update(['pending_details' => $text, 'step' => 'gift_amount']);
            return $this->sendMessage($user->telegram_id, "💰 كم المبلغ؟ (الأدنى 22,000)");
        }
        if ($user->step == 'gift_amount') {
            $amount = (float)$text;
            $target = User::where('telegram_id', $user->pending_details)->first();
            if ($amount >= 22000 && $user->balance >= $amount && $target) {
                $user->decrement('balance', $amount);
                $target->increment('balance', $amount);
                $user->update(['step' => 'none']);
                $this->sendMessage($target->telegram_id, "🎁 مبروك! وصلتك هدية بقيمة $amount SYP");
                return $this->sendMessage($user->telegram_id, "✅ تم الإرسال بنجاح.");
            }
            $user->update(['step' => 'none']);
            return $this->sendMessage($user->telegram_id, "❌ فشلت العملية.");
        }

        $user->update(['step' => 'none']);
    }

    // --- لوحة التحكم والوظائف الأخرى ---
    private function adminPanel($chatId) {
        $keys = json_encode(['inline_keyboard' => [[['text' => "📢 إذاعة رسالة", 'callback_data' => "admin_broadcast"]]]]);
        return $this->sendMessage($chatId, "🛠 لوحة تحكم الأدمن:", $keys);
    }

    private function referralInfo($user) {
        $count = User::where('referrer_id', $user->telegram_id)->count();
        $link = "https://t.me/YourBotUser?start=REF" . $user->telegram_id;
        $text = "💰 **نظام الإحالات**\n👥 المدعوين: $count\n🔗 رابطك: `$link`";
        return $this->sendMessage($user->telegram_id, $text);
    }

    private function handleCallback($callback) {
        $data = $callback['data'];
        if ($data == 'admin_broadcast') {
            User::where('telegram_id', $this->adminChatId)->update(['step' => 'waiting_broadcast']);
            return $this->sendMessage($this->adminChatId, "📣 أرسل نص الرسالة للإذاعة:");
        }
    }

    private function sendMessage($chatId, $text, $keyboard = null) {
        return Http::post("https://api.telegram.org/bot{$this->botToken}/sendMessage", [
            'chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'HTML', 'reply_markup' => $keyboard
        ]);
    }

    private function sendWelcome($user) {
        $text = "🎯 **معلومات الرصيد**\n💰 رصيدك: **{$user->balance} SYP**\n🆔 آيديك: `{$user->telegram_id}`";
        return $this->sendMessage($user->telegram_id, $text, $this->mainKeyboard());
    }

    private function mainKeyboard() {
        return json_encode(['keyboard' => [
            [['text' => "⚡ حساب ايشانسي وشحنه ⚡"]],
            [['text' => "📥 شحن رصيد في البوت"], ['text' => "📤 سحب رصيد من البوت"]],
            [['text' => "🎁 إهداء صديق"], ['text' => "🏆 كود جائزة"]],
            [['text' => "💰 الإحالات"]],
            [['text' => "💬 إرسال رسالة للدعم"]],
            [['text' => "↗️ ايشانسي"], ['text' => "⚠️ شروط الاستخدام"]]
        ], 'resize_keyboard' => true]);
    }
}
