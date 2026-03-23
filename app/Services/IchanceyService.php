<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class IchanceyService {

    protected $token = "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9..."; // حط توكن الكاشيرة هون
    protected $baseUrl = "https://www.ichancey.com/api/v1"; // الرابط الحقيقي للـ API

    // 1. إنشاء حساب تلقائي
    public function createAccount($username, $password) {
        $response = Http::withToken($this->token)->post($this->baseUrl . "/agent/create-player", [
            'username' => $username,
            'password' => $password,
            'password_confirmation' => $password,
        ]);
        return $response->json();
    }

    // 2. شحن رصيد تلقائي (من الكاشيرة للاعب)
    public function deposit($playerUsername, $amount) {
        $response = Http::withToken($this->token)->post($this->baseUrl . "/agent/transfer-to-player", [
            'username' => $playerUsername,
            'amount'   => $amount,
        ]);
        return $response->successful();
    }

    // 3. سحب رصيد تلقائي (من اللاعب للكاشيرة)
    public function withdraw($playerUsername, $amount) {
        $response = Http::withToken($this->token)->post($this->baseUrl . "/agent/withdraw-from-player", [
            'username' => $playerUsername,
            'amount'   => $amount,
        ]);
        return $response->successful();
    }
}
