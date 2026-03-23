<?php
namespace App\Services;
use Illuminate\Support\Facades\Http;

class IchanceyService {
    protected $baseUrl = "https://www.ichancey.com/api"; // أو الرابط المخصص للـ API
    protected $username = "YOUR_CASHIER_USER"; 
    protected $password = "YOUR_CASHIER_PASS";

    public function login() {
        // منطق تسجيل الدخول لجلب الـ Token الخاص بالكاشيرة
        $response = Http::post($this->baseUrl . "/login", [
            'username' => $this->username,
            'password' => $this->password
        ]);
        return $response->json()['token'] ?? null;
    }

    public function transferToUser($targetUser, $amount) {
        $token = $this->login();
        if (!$token) return false;

        // إرسال الرصيد من حساب الكاشيرة لحساب اللاعب
        $response = Http::withToken($token)->post($this->baseUrl . "/transfer", [
            'player_id' => $targetUser,
            'amount' => $amount
        ]);
        return $response->successful();
    }
}
