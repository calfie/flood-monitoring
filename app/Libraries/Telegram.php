<?php
namespace App\Libraries;

class Telegram
{
    private $botToken;
    private $chatId;
    private $baseUrl;

    public function __construct()
    {
        $this->botToken = env('telegram.bot_token');
        $this->chatId   = env('telegram.chat_id');
        $this->baseUrl  = "https://api.telegram.org/bot{$this->botToken}";
    }

    public function sendMessage(string $text, array $options = [])
    {
        $payload = array_merge([
            'chat_id' => $this->chatId,
            'text'    => $text,
            // 'parse_mode' => 'HTML' atau 'MarkdownV2'
        ], $options);

        $ch = curl_init("{$this->baseUrl}/sendMessage");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // optional timeout
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $res = curl_exec($ch);
        $err = curl_error($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);

        // kembalikan array decode supaya gampang dipakai
        if ($res === false) {
            return ['ok' => false, 'error' => $err];
        }

        $json = json_decode($res, true);
        return $json ?? ['ok' => false, 'raw' => $res];
    }
}
