private function handleIchanceyMenu($chatId) {
    $user = User::where('telegram_id', $chatId)->first();

    if (!$user->ichancey_username) {
        // إذا لم يكن لديه حساب، نبدأ عملية الإنشاء
        $user->update(['step' => 'waiting_for_ichancey_user']);
        return $this->sendMessage($chatId, "🆕 **إنشاء حساب ايشانسي جديد**\n\nيرجى إرسال اسم المستخدم الذي تريده (باللغة الإنجليزية):");
    } else {
        // إذا كان لديه حساب بالفعل، نعرض له معلوماته وأزرار الشحن والسحب
        $text = "👤 **معلومات حسابك في ايشانسي:**\n\n";
        $text .= "📝 اسم المستخدم: `{$user->ichancey_username}`\n";
        $text .= "🔑 كلمة المرور: `{$user->ichancey_password}`\n\n";
        $text .= "ماذا تريد أن تفعل الآن؟";

        $keyboard = json_encode([
            'inline_keyboard' => [
                [['text' => "💰 شحن من المحفظة للعبة", 'callback_data' => "transfer_to_game"]],
                [['text' => "📥 سحب من اللعبة للمحفظة", 'callback_data' => "transfer_from_game"]]
            ]
        ]);

        return $this->sendMessage($chatId, $text, $keyboard);
    }
}

// دالة معالجة النصوص المدخلة (الاسم والباسورد)
private function handleText($message) {
    $chatId = $message['chat']['id'];
    $text = $message['text'];
    $user = User::where('telegram_id', $chatId)->first();

    if ($user->step == 'waiting_for_ichancey_user') {
        $user->update([
            'ichancey_username' => $text,
            'step' => 'waiting_for_ichancey_pass'
        ]);
        return $this->sendMessage($chatId, "✅ تمام، الآن أرسل **كلمة المرور** التي تريدها للحساب:");
    }

    if ($user->step == 'waiting_for_ichancey_pass') {
        $user->update([
            'ichancey_password' => $text,
            'step' => 'none'
        ]);
        return $this->sendMessage($chatId, "🎉 تم إنشاء حسابك بنجاح!\n\nيمكنك الآن استخدامه في موقع ايشانسي والشحن من خلال البوت مباشرة.", Buttons::mainMenu());
    }
    
    // هنا نضع باقي الأوامر (مثل شحن، سحب، إهداء...)
}
