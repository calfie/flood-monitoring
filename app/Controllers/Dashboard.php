<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\FirebaseModel;

class Dashboard extends BaseController
{
    // Klasifikasi status berdasarkan ketinggian air
    private function classifyStatus(?float $distanceCm): string
    {
        if ($distanceCm === null || $distanceCm < 0) {
            return '-';
        }

        // RULE
        if ($distanceCm > 100) {
            return 'AMAN';
        } elseif ($distanceCm >= 50) {
            return 'SIAGA';
        }

        return 'DARURAT';
    }

    public function index()
    {
        helper('telegram');
        sendTelegramAlert('🔥 TEST TELEGRAM DARI SERVER');

        $cache = \Config\Services::cache();
        $firebase = new FirebaseModel();
        $devices  = $firebase->getDevices();   // NODE1, NODE2, ...

        // Untuk hitung status keseluruhan (ambil yang paling “parah”)
        $statusRank = ['AMAN' => 1, 'SIAGA' => 2, 'WASPADA' => 3];
        $worstRank  = 0;

        foreach ($devices as $id => &$dev) {
            // waktu tampilan (sementara dari server web)
            $dev['updated_ms_readable'] = date('Y-m-d H:i:s');

            // baca ketinggian & tentukan label parameter
            $distance = isset($dev['distance_cm']) ? (float) $dev['distance_cm'] : null;
            $label    = $this->classifyStatus($distance);
            $dev['label_param'] = $label;

            if (isset($statusRank[$label]) && $statusRank[$label] > $worstRank) {
                $worstRank = $statusRank[$label];
            }
            if ($dev['label'] === 'DARURAT') {

                $cache = \Config\Services::cache();
                $nodeId = $id; // NODE1, NODE2
                $key = 'tg_alert_' . $nodeId;

                if (!$cache->get($key)) {

                    $msg = "<b>🚨 STATUS DARURAT</b>\n"
                        . "<b>Node:</b> {$nodeId}\n"
                        . "<b>Ketinggian:</b> {$dev['tinggi']} cm\n"
                        . "<b>Arus:</b> {$dev['arus']} L/min\n"
                        . "<b>Waktu:</b> " . date('Y-m-d H:i:s');

                    sendTelegramAlert($msg);

                    // tahan 10 menit biar ga spam
                    $cache->save($key, true, 600);
                }
            }
        }
        unset($dev);

        $overallStatus = '-';
        if ($worstRank > 0) {
            $overallStatus = array_search($worstRank, $statusRank, true);
        }

        return view('dashboard/public', [
            'title'         => 'Dashboard Publik',
            'nodes'         => $devices,
            'overallStatus' => $overallStatus,
        ]);
    }
}
