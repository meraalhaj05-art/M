<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Transaction;
use App\Models\Voucher;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramController extends Controller {

    protected $botToken = "7154942055:AAH3k_X7N0S8D8C8D8C8D8C8D8C8D8C"; // استبدله بالتوكن الحقيقي
    protected $adminChatId = "5593775415"; // الـ ID الخاص بك كأدمن

    public function handle(Request $request) {
        $update = $request->all();

        // معالجة الضغط على أزرار القبول والرفض (Inline Buttons)
        if (isset($update['callback_query'])) {
            return $this->handleCallback($update['callback_query']);
        }

        if (!isset($update['message'])) return;
        $message = $update['message'];
        $chatId = $message['chat']['id'];

        // البحث عن المستخدم أو إنشاؤه
        $user = User::firstOrCreate(
            ['telegram_id' => $chatId],
            ['balance' => 0, 'referral_code' => 'REF' . $chatId, 'step' => 'none']
        );

        // إذا أرسل صورة (إثبات دفع)
        if (isset($message['photo'])) {
            return $this->handlePhoto($user, $message);
        }

        // إذا أرسل نص
        if (isset($message['text'])) {
            $text = $message['text'];

            // نظام الحالات (Steps) - الذاكرة
            if ($user->step !== 'none') {
                return $this->handleSteps($user, $text);
            }

            // الأوامر الرئيسية
            switch ($text) {
                case '/start':
                    return $this->sendWelcome($user);
                case '⚡ حساب ايشانسي وشحنه ⚡':
                    return $this->ichanceyMenu($user);
                case '📥 شحن رصيد في البوت':
                    return $this->depositMenu($chatId);
                case '📤 سحب رصيد من البوت':
                    return $this->withdrawRequest($user);
                case '🎁 إهداء صديق':
                    $user->update(['step' => 'gift_id']);
                    return $this->sendMessage($chatId, "🎁 أرسل آيدي الصديق الذي تريد إهداءه:");
                case '🏆 كود جائزة':
                    $user->update(['step' => 'use_voucher']);
                    return $this->sendMessage($chatId, "🏆 أدخل كود الجائزة الخاص بك:");
                case '💰 الإحالات':
                    return $this->referralMenu($user);
                case '💬 إرسال رسالة للدعم':
                    return $this->sendMessage($chatId, "👨‍💻 للتواصل مع الدعم الفني: \n [اضغط هنا](https://t.me/YOUR_SUPPORT_ID)"); // عدل الرابط
                case '↗️ ايشانسي':
                    return $this->sendMessage($chatId, "للانتقال للموقع: www.ichancey.com");
                default:
                    return $this->sendWelcome($user);
            }
        }
    }

    // --- منطق الأزرار الرئيسية ---
    private function sendWelcome($user) {
        $text = "🎯 **أهلاً بك في بوت ايشانسي!**\n\n💰 رصيدك الحالي: **{$user->balance} SYP**\n🆔 آيدي حسابك: `{$user->telegram_id}`";
        return $this->sendMessage($user->telegram_id, $text, $this->mainKeyboard());
    }

    private function mainKeyboard() {
        return json_encode([
            'keyboard' => [
                [['text' => "⚡ حساب ايشانسي وشحنه ⚡"]],
                [['text' => "📥 شحن رصيد في البوت"], ['text' => "📤 سحب رصيد من البوت"]],
                [['text' => "🎁 إهداء صديق"], ['text' => "🏆 كود جائزة"]],
                [['text' => "💰 الإحالات"]],
                [['text' => "💬 إرسال رسالة للدعم"]],
                [['text' => "↗️ ايشانسي"], ['text' => "⚠️ شروط الاستخدام"]]
            ],
            'resize_keyboard' => true
        ]);
    }

    // --- نظام معالجة الخطوات (Steps) ---
    private function handleSteps($user, $text) {
        switch ($user->step) {
            case 'gift_id':
                $user->update(['pending_details' => $text, 'step' => 'gift_amount']);
                return $this->sendMessage($user->telegram_id, "💰 كم المبلغ؟ (الحد الأدنى 22,000)");
            
            case 'gift_amount':
                if ((float)$text < 22000 || $user->balance < (float)$text) {
                    $user->update(['step' => 'none']);
                    return $this->sendMessage($user->telegram_id, "❌ فشل! الرصيد غير كافٍ أو المبلغ أقل من 22,000.");
                }
                $target = User::where('telegram_id', $user->pending_details)->first();
                if ($target) {
                    $user->decrement('balance', (float)$text);
                    $target->increment('balance', (float)$text);
                    $user->update(['step' => 'none']);
                    $this->sendMessage($target->telegram_id, "🎁 وصلتك هدية بقيمة $text SYP!");
                    return $this->sendMessage($user->telegram_id, "✅ تم الإهداء بنجاح.");
                }
                break;

            case 'use_voucher':
                $voucher = Voucher::where('code', $text)->first();
                if ($voucher && $voucher->used_count < $voucher->max_uses) {
                    $user->increment('balance', $voucher->amount);
                    $voucher->increment('used_count');
                    $user->update(['step' => 'none']);
                    return $this->sendMessage($user->telegram_id, "🎉 مبروك! حصلت على {$voucher->amount} SYP.");
                }
                $user->update(['step' => 'none']);
                return $this->sendMessage($user->telegram_id, "❌ الكود خاطئ أو منتهي الصلاحية.");
        }
    }

    // --- نظام الصور (الشحن) ---
    private function handlePhoto($user, $message) {
        $fileId = end($message['photo'])['file_id'];
        $trx = Transaction::create([
            'user_id' => $user->telegram_id,
            'type' => 'deposit',
            'amount' => 0, // يتم تحديده عند القبول
            'proof_image' => $fileId,
            'status' => 'pending'
        ]);

        $caption = "🔔 **طلب شحن جديد!**\n🆔 الآيدي: `{$user->telegram_id}`\nيرجى مراجعة الصورة والقبول.";
        $this->sendPhotoToAdmin($fileId, $caption, $trx->id);
        return $this->sendMessage($user->telegram_id, "⏳ تم إرسال الصورة للأدمن، يرجى الانتظار.");
    }

    // --- إرسال للأدمن للقبول/الرفض ---
    private function sendPhotoToAdmin($fileId, $caption, $trxId) {
        $url = "https://api.telegram.org/bot{$this->botToken}/sendPhoto";
        $keyboard = json_encode(['inline_keyboard' => [[
            ['text' => "✅ قبول", 'callback_data' => "approve_{$trxId}"],
            ['text' => "❌ رفض", 'callback_data' => "reject_{$trxId}"]
        ]]]);
        
        return Http::post($url, ['chat_id' => $this->adminChatId, 'photo' => $fileId, 'caption' => $caption, 'reply_markup' => $keyboard]);
    }

    // --- دالة الإرسال العامة ---
    private function sendMessage($chatId, $text, $keyboard = null) {
        return Http::post("https://api.telegram.org/bot{$this->botToken}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'reply_markup' => $keyboard
        ]);
    }
}
