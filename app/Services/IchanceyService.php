<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IchanceyService {

    // التوكن والبيانات اللي عطيتني ياها
    protected $token = "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiI0IiwianRpIjoiY2E0YTVmYjBkOGY0NjY3ZDk1NTRiMzFmYjZlMTVkN2ZiNGE0YTMzYjQ0YmY0N2E4YjQ4MGI5YjE1YmZkY2E1ZTllZjdiNTE5IiwiaWF0IjoxNzA5ODc2NTQzLCJuYmYiOjE3MDk4NzY1NDMsImV4cCI6MTcxMjQ2ODU0Mywic3ViIjoiMzk4NDUiLCJzY29wZXMiOlsiaWNoYW5jeS1hZ2VudCJdfQ";
    
    // الرابط الأساسي (Endpoints) - ملاحظة: قد تحتاج لتعديله حسب مسار الـ API الحقيقي للموقع
    protected $baseUrl = "https://www.ichancey.com/api/v1"; 

    /**
     * إنشاء حساب لاعب جديد تلقائياً
     */
    public function createPlayer($username, $password) {
        $response = Http::withToken($this->token)->post($this->baseUrl . "/agent/create-player", [
            'username' => $username,
            'password' => $password,
            'password_confirmation' => $password,
        ]);

        return $response->json();
    }

    /**
     * شحن رصيد للاعب من حساب الكاشيرة (Deposit)
     */
    public function depositToPlayer($playerUsername, $amount) {
        $response = Http::withToken($this->token)->post($this->baseUrl . "/agent/transfer-to-player", [
            'username' => $playerUsername,
            'amount'   => $amount,
        ]);

        return $response->json();
    }

    /**
     * سحب رصيد من اللاعب لحساب الكاشيرة (Withdraw)
     */
    public function withdrawFromPlayer($playerUsername, $amount) {
        $response = Http::withToken($this->token)->post($this->baseUrl . "/agent/withdraw-from-player", [
            'username' => $playerUsername,
            'amount'   => $amount,
        ]);

        return $response->json();
    }
}
