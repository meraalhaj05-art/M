<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class IchanceyService {

    // التوكن الذهبي تبعك (مفتاح المغارة)
    protected $token = "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiI0IiwianRpIjoiY2E0YTVmYjBkOGY0NjY3ZDk1NTRiMzFmYjZlMTVkN2ZiNGE0YTMzYjQ0YmY0N2E4YjQ4MGI5YjE1YmZkY2E1ZTllZjdiNTE5IiwiaWF0IjoxNzA5ODc2NTQzLCJuYmYiOjE3MDk4NzY1NDMsImV4cCI6MTcxMjQ2ODU0Mywic3ViIjoiMzk4NDUiLCJzY29wZXMiOlsiaWNoYW5jeS1hZ2VudCJdfQ";
    
    // روابط الـ API المخفية لموقع إيشانسي (بناءً على الهندسة العكسية)
    protected $baseUrl = "https://www.ichancey.com/api/v2"; 

    /**
     * إنشاء لاعب جديد (تلقائي)
     */
    public function createPlayer($username, $password) {
        $response = Http::withToken($this->token)
            ->withHeaders(['Accept' => 'application/json'])
            ->post($this->baseUrl . "/agent/players", [
                'username' => $username,
                'password' => $password,
                'password_confirmation' => $password,
                'currency' => 'SYP' // العملة الافتراضية
            ]);

        return $response->json();
    }

    /**
     * شحن رصيد (تلقائي) - من الكاشيرة للاعب
     */
    public function depositToPlayer($playerUsername, $amount) {
        $response = Http::withToken($this->token)
            ->withHeaders(['Accept' => 'application/json'])
            ->post($this->baseUrl . "/agent/transactions/deposit", [
                'player_username' => $playerUsername,
                'amount' => (int)$amount,
            ]);

        return $response->json();
    }

    /**
     * سحب رصيد (تلقائي) - من اللاعب للكاشيرة
     */
    public function withdrawFromPlayer($playerUsername, $amount) {
        $response = Http::withToken($this->token)
            ->withHeaders(['Accept' => 'application/json'])
            ->post($this->baseUrl . "/agent/transactions/withdraw", [
                'player_username' => $playerUsername,
                'amount' => (int)$amount,
            ]);

        return $response->json();
    }
}
