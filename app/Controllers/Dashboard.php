<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\FirebaseModel;

class Dashboard extends BaseController
{
    private function classifyStatus(?float $distanceCm): string
    {
        if ($distanceCm === null || $distanceCm < 0) {
            return '-';
        }

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

        $cache    = \Config\Services::cache();
        $firebase = new FirebaseModel();
        $devices  = $firebase->getDevices(); // NODE1, NODE2, ...

        // rank status (semakin besar semakin parah)
        $statusRank = ['-' => 0, 'AMAN' => 1, 'SIAGA' => 2, 'DARURAT' => 3];
        $worstRank  = 0;

        foreach ($devices as $id => &$dev) {
            $dev['updated_ms_readable'] = date('Y-m-d H:i:s');

            // sesuaikan key data dari firebase kamu
            $distance = isset($dev['distance_cm']) ? (float) $dev['distance_cm'] : null;
            $label    = $this->classifyStatus($distance);
            $dev['label_param'] = $label;

            $worstRank = max($worstRank, $statusRank[$label] ?? 0);

            // KIRIM TELEGRAM kalau DARURAT (anti spam per node)
            if ($label === 'DARURAT') {
                $key = 'tg_alert_' . $id;

                if (! $cache->get($key)) {
                    // sesuaikan key "tinggi" dan "arus" sesuai struktur firebase kamu
                    $tinggi = $dev['tinggi'] ?? ($dev['water_level_cm'] ?? ($dev['distance_cm'] ?? '-'));
                    $arus   = $dev['arus'] ?? ($dev['flow_lmin'] ?? '-');

                    $msg = "<b>🚨 STATUS DARURAT</b>\n"
                        . "<b>Node:</b> {$id}\n"
                        . "<b>Ketinggian:</b> {$tinggi}\n"
                        . "<b>Arus:</b> {$arus}\n"
                        . "<b>Waktu:</b> " . date('Y-m-d H:i:s');

                    sendTelegramAlert($msg);

                    // tahan 10 menit biar ga spam
                    $cache->save($key, true, 600);
                }
            }
        }
        unset($dev);

        $overallStatus = array_search($worstRank, $statusRank, true);
        if ($overallStatus === false) $overallStatus = '-';

        return view('dashboard/public', [
            'title'         => 'Dashboard Publik',
            'nodes'         => $devices,
            'overallStatus' => $overallStatus,
        ]);
    }
}
