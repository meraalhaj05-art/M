// داخل الكلاس TelegramController

private $adminChatId = "YOUR_ADMIN_ID"; // حط الـ ID تبعك هون

public function handle(Request $request) {
    $update = $request->all();

    // إذا كانت الرسالة نصية
    if (isset($update['message']['text'])) {
        $this->handleText($update['message']);
    } 
    // إذا كانت الرسالة صورة (Proof of Payment)
    elseif (isset($update['message']['photo'])) {
        $this->handlePhoto($update['message']);
    }
    // إذا ضغط الأدمن على أزرار القبول والرفض
    elseif (isset($update['callback_query'])) {
        $this->handleCallback($update['callback_query']);
    }
}

private function handlePhoto($message) {
    $chatId = $message['chat']['id'];
    $user = User::where('telegram_id', $chatId)->first();

    // التأكد أن المستخدم في مرحلة "إرسال صورة التحويل"
    if ($user->step == 'waiting_for_proof') {
        $photo = end($message['photo']); // أخذ أعلى دقة للصورة
        $fileId = $photo['file_id'];

        // إنشاء عملية شحن "معلقة" في قاعدة البيانات
        $transaction = Transaction::create([
            'user_id' => $chatId,
            'type' => 'deposit',
            'amount' => $user->pending_amount, // المبلغ الذي ادعى أنه حوله
            'proof_image' => $fileId,
            'status' => 'pending'
        ]);

        // إرسال الصورة لك (للأدمن) مع الأزرار
        $caption = "🔔 طلب شحن جديد!\n👤 المستخدم: " . ($message['from']['first_name'] ?? 'مجهول') . "\n🆔 آيدي: $chatId\n💰 المبلغ: " . $user->pending_amount . " SYP";
        
        $this->sendPhotoToAdmin($fileId, $caption, Buttons::adminAction($transaction->id));

        // تأكيد للمستخدم
        $this->sendMessage($chatId, "✅ تم استلام صورتك! يرجى الانتظار حتى يقوم الإدمن بمراجعة العملية.");
        $user->update(['step' => 'none']);
    }
}

private function handleCallback($callback) {
    $data = $callback['data']; // مثل approve_15 أو reject_15
    $adminId = $callback['from']['id'];
    
    // التحقق أنك أنت الأدمن
    if ($adminId != $this->adminChatId) return;

    list($action, $trxId) = explode('_', $data);
    $transaction = Transaction::find($trxId);

    if ($action == 'approve') {
        // إضافة الرصيد للمستخدم
        $user = User::where('telegram_id', $transaction->user_id)->first();
        $user->increment('balance', $transaction->amount);
        $transaction->update(['status' => 'approved']);

        $this->sendMessage($user->telegram_id, "🎉 مبروك! تم قبول طلب الشحن وإضافة " . $transaction->amount . " ليرة لرصيدك.");
        $this->sendMessage($this->adminChatId, "✅ تم قبول العملية رقم $trxId بنجاح.");
    } else {
        $transaction->update(['status' => 'rejected']);
        $this->sendMessage($transaction->user_id, "❌ عذراً، تم رفض طلب الشحن الخاص بك. يرجى التواصل مع الدعم.");
        $this->sendMessage($this->adminChatId, "❌ تم رفض العملية رقم $trxId.");
    }
}
