<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Transaction;
use App\Models\Voucher;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;

class TelegramController extends Controller {

    // --- الإعدادات الأساسية (عدلها من هون) ---
    protected $botToken = "7154942055:AAH3k_X7N0S8D8C8D8C8D8C8D8C8D8C"; // توكن البوت
    protected $adminChatId = "5593775415"; // آيدي التلجرام تبعك (الأدمن)

    public function handle(Request $request) {
        $update = $request->all();

        // 1. معالجة أزرار القبول/الرفض (Callback)
        if (isset($update['callback_query'])) {
            return $this->handleCallback($update['callback_query']);
        }

        if (!isset($update['message'])) return;
        $message = $update['message'];
        $chatId = $message['chat']['id'];

        // 2. فحص الاشتراك الإجباري (إلا للأدمن)
        if ($chatId != $this->adminChatId && !$this->checkSubscription($chatId)) {
            $btn = json_encode(['inline_keyboard' => [[['text' => "📢 اشترك هنا", 'url' => 'https://t.me/YOUR_CHANNEL']]]]);
            return $this->sendMessage($chatId, "⚠️ **عذراً!** يجب عليك الاشتراك في القناة أولاً لاستخدام البوت.", $btn);
        }

        // 3. البحث عن المستخدم أو تسجيله
        $user = User::firstOrCreate(
            ['telegram_id' => $chatId],
            ['balance' => 0, 'referral_code' => 'REF' . $chatId, 'step' => 'none']
        );

        // 4. معالجة الصور (إثباتات الشحن)
        if (isset($message['photo'])) {
            return $this->handlePhoto($user, $message);
        }

        // 5. معالجة النصوص والأوامر
        if (isset($message['text'])) {
            $text = $message['text'];

            // نظام الحالات (Steps)
            if ($user->step !== 'none') {
                return $this->handleSteps($user, $text);
            }

            // الأوامر البرمجية
            switch ($text) {
                case '/start': return $this->sendWelcome($user);
                case '/admin': return ($chatId == $this->adminChatId) ? $this->adminPanel($chatId) : null;
                case '⚡ حساب ايشانسي وشحنه ⚡': return $this->ichanceyMenu($user);
                case '📥 شحن رصيد في البوت': return $this->depositMethods($chatId);
                case '📤 سحب رصيد من البوت': return $this->withdrawMenu($chatId);
                case '🎁 إهداء صديق': 
                    $user->update(['step' => 'gift_id']);
                    return $this->sendMessage($chatId, "🎁 أرسل آيدي (ID) الصديق:");
                case '🏆 كود جائزة': 
                    $user->update(['step' => 'use_code']);
                    return $this->sendMessage($chatId, "🏆 أدخل كود الجائزة:");
                case '💰 الإحالات': return $this->referralInfo($user);
                case '💬 إرسال رسالة للدعم': return $this->sendMessage($chatId, "👨‍💻 للدعم الفني: @YOUR_SUPPORT_ID");
                case '↗️ ايشانسي': return $this->sendMessage($chatId, "الموقع الرسمي: www.ichancey.com");
                default: return $this->sendWelcome($user);
            }
        }
    }

    // --- الدوال الأساسية للبوت ---

    private function sendWelcome($user) {
        $text = "🎯 **معلومات الرصيد**\n\nالرصيد الحالي: **{$user->balance} SYP**\nأيدي حسابك: `{$user->telegram_id}`";
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

    private function handleSteps($user, $text) {
        // إنشاء حساب إيشانسي
        if ($user->step == 'create_ich_user') {
            $user->update(['ichancey_username' => $text, 'step' => 'create_ich_pass']);
            return $this->sendMessage($user->telegram_id, "🔐 أرسل كلمة المرور:");
        }
        if ($user->step == 'create_ich_pass') {
            $user->update(['ichancey_password' => $text, 'step' => 'none']);
            return $this->sendMessage($user->telegram_id, "✅ تم ربط حساب إيشانسي بنجاح.");
        }

        // إهداء صديق
        if ($user->step == 'gift_id') {
            $user->update(['pending_details' => $text, 'step' => 'gift_amount']);
            return $this->sendMessage($user->telegram_id, "💰 كم المبلغ؟ (الأدنى 22,000)");
        }
        if ($user->step == 'gift_amount') {
            $amount = (float)$text;
            if ($amount >= 22000 && $user->balance >= $amount) {
                $target = User::where('telegram_id', $user->pending_details)->first();
                if ($target) {
                    $user->decrement('balance', $amount);
                    $target->increment('balance', $amount);
                    $user->update(['step' => 'none']);
                    $this->sendMessage($target->telegram_id, "🎁 وصلتك هدية بقيمة $amount SYP!");
                    return $this->sendMessage($user->telegram_id, "✅ تم الإرسال.");
                }
            }
            $user->update(['step' => 'none']);
            return $this->sendMessage($user->telegram_id, "❌ فشل الإهداء (رصيد غير كافٍ أو آيدي خطأ).");
        }

        // استخدام كود جائزة
        if ($user->step == 'use_code') {
            $v = Voucher::where('code', $text)->first();
            if ($v && $v->used_count < $v->max_uses) {
                $user->increment('balance', $v->amount);
                $v->increment('used_count');
                $user->update(['step' => 'none']);
                return $this->sendMessage($user->telegram_id, "🎉 ربحت {$v->amount} SYP!");
            }
            $user->update(['step' => 'none']);
            return $this->sendMessage($user->telegram_id, "❌ الكود منتهي أو خاطئ.");
        }
    }

    private function handlePhoto($user, $message) {
        $fileId = end($message['photo'])['file_id'];
        $trx = Transaction::create(['user_id' => $user->telegram_id, 'type' => 'deposit', 'amount' => 0, 'proof_image' => $fileId, 'status' => 'pending']);
        
        $caption = "🔔 **طلب شحن جديد!**\n🆔: `{$user->telegram_id}`\nراجع الصورة ثم حدد الإجراء:";
        $keyboard = json_encode(['inline_keyboard' => [[
            ['text' => "✅ قبول", 'callback_data' => "approve_{$trx->id}"],
            ['text' => "❌ رفض", 'callback_data' => "reject_{$trx->id}"]
        ]]]);
        return $this->sendPhotoToAdmin($fileId, $caption, $keyboard);
    }

    private function handleCallback($callback) {
        $data = $callback['data'];
        $trxId = explode('_', $data)[1];
        $trx = Transaction::find($trxId);
        $target = User::where('telegram_id', $trx->user_id)->first();

        if (str_contains($data, 'approve')) {
            // ملاحظة: هنا الأدمن يحدد المبلغ يدوياً بطلب آخر أو نثبت مبلغ
            $target->increment('balance', 50000); // مثال: شحن 50 ألف
            $trx->update(['status' => 'approved']);
            $this->sendMessage($target->telegram_id, "✅ تم قبول طلب الشحن.");
        } else {
            $trx->update(['status' => 'rejected']);
            $this->sendMessage($target->telegram_id, "❌ تم رفض طلب الشحن.");
        }
        return Http::post("https://api.telegram.org/bot{$this->botToken}/answerCallbackQuery", ['callback_query_id' => $callback['id'], 'text' => "تم التنفيذ"]);
    }

    // --- وظائف مساعدة ---
    private function sendMessage($chatId, $text, $keyboard = null) {
        return Http::post("https://api.telegram.org/bot{$this->botToken}/sendMessage", ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'HTML', 'reply_markup' => $keyboard]);
    }

    private function sendPhotoToAdmin($fileId, $caption, $keyboard) {
        return Http::post("https://api.telegram.org/bot{$this->botToken}/sendPhoto", ['chat_id' => $this->adminChatId, 'photo' => $fileId, 'caption' => $caption, 'reply_markup' => $keyboard]);
    }

    private function checkSubscription($chatId) {
        // يمكنك تفعيل الفحص الحقيقي هنا بـ getChatMember
        return true; 
    }
}
