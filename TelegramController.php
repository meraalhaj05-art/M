// --- منطق حساب ايشانسي (إنشاء وتدبير) ---
    private function ichanceyMenu($user) {
        if (!$user->ichancey_username) {
            $user->update(['step' => 'create_ich_user']);
            return $this->sendMessage($user->telegram_id, "🆕 **إنشاء حساب ايشانسي جديد**\n\nأرسل اسم المستخدم الذي تريده (بالإنجليزي):");
        }
        
        $text = "👤 **حسابك المرتبط:**\n\n📝 المستخدم: `{$user->ichancey_username}`\n🔑 الباسورد: `{$user->ichancey_password}`\n\nماذا تريد أن تفعل؟";
        $keyboard = json_encode(['inline_keyboard' => [
            [['text' => "💰 شحن حساب اللعبة", 'callback_data' => "topup_game"], ['text' => "📥 سحب للمحفظة", 'callback_data' => "withdraw_game"]]
        ]]);
        return $this->sendMessage($user->telegram_id, $text, $keyboard);
    }

    // --- معالجة الضغط على أزرار (القبول / الرفض / شحن اللعبة) ---
    private function handleCallback($callback) {
        $data = $callback['data'];
        $chatId = $callback['message']['chat']['id'];
        $messageId = $callback['message']['message_id'];

        // إذا كان الأدمن يوافق على صورة شحن
        if (str_contains($data, 'approve_')) {
            $trxId = str_replace('approve_', '', $data);
            $trx = Transaction::find($trxId);
            $user = User::where('telegram_id', $trx->user_id)->first();
            
            // هنا الأدمن سيُسأل عن المبلغ الفعلي الذي وصله
            $user->update(['step' => 'admin_confirm_amount', 'pending_details' => $trxId]);
            return $this->sendMessage($this->adminChatId, "✅ تمام، كم المبلغ (بالليرات) اللي وصلك فعلياً؟");
        }

        if (str_contains($data, 'reject_')) {
            $trxId = str_replace('reject_', '', $data);
            $trx = Transaction::find($trxId);
            $trx->update(['status' => 'rejected']);
            $this->sendMessage($trx->user_id, "❌ عذراً، تم رفض طلب الشحن. تأكد من العملية وحاول مجدداً.");
            return $this->sendMessage($this->adminChatId, "⚠️ تم رفض العملية رقم $trxId");
        }
    }

    // --- تكملة معالجة النصوص (إضافة الخطوات الجديدة) ---
    private function handleSteps($user, $text) {
        // ... (الأكواد السابقة موجودة، سنضيف عليها) ...
        
        if ($user->step == 'create_ich_user') {
            $user->update(['ichancey_username' => $text, 'step' => 'create_ich_pass']);
            return $this->sendMessage($user->telegram_id, "🔐 ممتاز، الآن أرسل كلمة المرور للحساب:");
        }

        if ($user->step == 'create_ich_pass') {
            $user->update(['ichancey_password' => $text, 'step' => 'none']);
            return $this->sendMessage($user->telegram_id, "🎉 مبروك! تم ربط حسابك بنجاح.\nيمكنك الآن الشحن والسحب مباشرة.");
        }

        // خطوة الأدمن لتحديد المبلغ المقبول
        if ($user->telegram_id == $this->adminChatId && $user->step == 'admin_confirm_amount') {
            $trxId = $user->pending_details;
            $trx = Transaction::find($trxId);
            $amount = (float)$text;

            $targetUser = User::where('telegram_id', $trx->user_id)->first();
            $targetUser->increment('balance', $amount);
            $trx->update(['status' => 'approved', 'amount' => $amount]);

            $user->update(['step' => 'none']);
            $this->sendMessage($targetUser->telegram_id, "✅ تم قبول طلبك وإضافة $amount SYP لرصيدك!");
            return $this->sendMessage($this->adminChatId, "👍 تم شحن الحساب بنجاح.");
        }
    }
