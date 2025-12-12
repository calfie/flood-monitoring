<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\FirebaseModel;

class Dashboard extends BaseController
{
    // ====== ATUR RULE SESUAI KAMU ======
    private function classifyDistance(?float $distanceCm): string
    {
        if ($distanceCm === null || $distanceCm < 0) return '-';

        // contoh rule kamu:
        if ($distanceCm >= 30) return 'AMAN';
        if ($distanceCm >= 25) return 'SIAGA';
        return 'DARURAT';
    }

    private function classifyFlow(?float $flowLpm): string
    {
        if ($flowLpm === null || $flowLpm < 0) return '-';

        // contoh rule kamu:
        if ($flowLpm >= 80) return 'DARURAT';
        if ($flowLpm >= 40) return 'SIAGA';
        return 'AMAN';
    }

    // gabung jarak + arus → ambil yang paling parah
    private function classifyNode(?float $distanceCm, ?float $flowLpm): string
    {
        $d = $this->classifyDistance($distanceCm);
        $f = $this->classifyFlow($flowLpm);

        $rank = ['-' => 0, 'AMAN' => 1, 'SIAGA' => 2, 'DARURAT' => 3];

        $maxRank = max($rank[$d] ?? 0, $rank[$f] ?? 0);
        if ($maxRank === 0) return '-';

        // cari label dari rank
        return array_search($maxRank, $rank, true) ?: '-';
    }

    public function index()
    {
        helper('telegram');

        $cache    = \Config\Services::cache();
        $firebase = new FirebaseModel();
        $devices  = $firebase->getDevices(); // NODE1, NODE2, ...

        // untuk status keseluruhan
        $statusRank = ['-' => 0, 'AMAN' => 1, 'SIAGA' => 2, 'DARURAT' => 3];
        $worstRank  = 0;

        foreach ($devices as $nodeId => &$dev) {

            // ====== ambil nilai sesuai key firebase kamu ======
            $distance = isset($dev['distance_cm']) ? (float) $dev['distance_cm'] : null;
            $flow     = isset($dev['flow_lpm'])    ? (float) $dev['flow_lpm']    : null;

            // ====== timestamp data dari firebase (WAJIB buat anti-duplikat) ======
            // pastikan ini bener-bener berubah tiap data baru masuk
            $loggedAt = $dev['logged_at'] ?? null; // contoh: "2025-12-12 15:10:05"
            if (!$loggedAt) {
                // fallback kalau belum ada (tidak ideal, tapi biar aman)
                $loggedAt = date('Y-m-d H:i:s');
            }

            // ====== label param utk ditampilkan ======
            $label = $this->classifyNode($distance, $flow);
            $dev['label_param'] = $label;

            // ====== buat tampilan waktu di card: pakai loggedAt ======
            $dev['updated_ms_readable'] = $loggedAt;

            // update status overall
            $worstRank = max($worstRank, $statusRank[$label] ?? 0);

            // ====== TELEGRAM: hanya kalau DARURAT + hanya kalau data baru ======
            if ($label === 'DARURAT') {
                // kunci unik: node + waktu data
                // jadi kalau refresh 100x, tetap 1x kirim selama logged_at sama
                $dedupeKey = 'tg_sent_' . $nodeId . '_' . md5((string) $loggedAt);

                if (!$cache->get($dedupeKey)) {
                    $msg = "<b>🚨 STATUS DARURAT</b>\n"
                        . "<b>Node:</b> {$nodeId}\n"
                        . "<b>Ketinggian:</b> " . ($dev['distance_cm'] ?? '-') . " cm\n"
                        . "<b>Arus:</b> " . ($dev['flow_lpm'] ?? '-') . " L/min\n"
                        . "<b>Waktu:</b> {$loggedAt}";

                    sendTelegramAlert($msg);

                    // simpan penanda. TTL bebas, yang penting cukup lama utk mencegah duplikat.
                    // 7 hari aman (kalau logged_at unik, TTL berapapun ga masalah).
                    $cache->save($dedupeKey, 1, 60 * 60 * 24 * 7);
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
