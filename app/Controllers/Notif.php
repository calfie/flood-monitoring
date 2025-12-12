<?php
namespace App\Controllers;

use App\Controllers\BaseController;
use App\Libraries\Telegram;

class Notif extends BaseController
{
    public function test()
    {
        $telegram = new Telegram();
        $message = "🚨 Test Notifikasi dari CI4\nWaktu: " . date('Y-m-d H:i:s');
        $resp = $telegram->sendMessage($message, ['parse_mode' => 'HTML']);

        return $this->response->setJSON($resp);
    }

    public function sensorAlert()
    {
        $ketinggian = $this->request->getPost('ketinggian') ?? 0;
        if ($ketinggian > 50) {
            $telegram = new Telegram();
            $text = "⚠️ *Peringatan Banjir*\nKetinggian air: {$ketinggian} cm";
            $telegram->sendMessage($text, ['parse_mode' => 'MarkdownV2']);
        }
        return "OK";
    }
}
