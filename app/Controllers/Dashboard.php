<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\FirebaseModel;

class Dashboard extends BaseController
{
    /**
     * Label ketinggian (berdasarkan distance_cm dari sensor)
     * Catatan: ini sesuai aturan kamu:
     * >= 30  => AMAN
     * >= 25  => SIAGA
     * <  25  => DARURAT
     */
    private function classifyDistance(?float $distanceCm): string
    {
        if ($distanceCm === null || $distanceCm < 0) {
            return '-';
        }

        if ($distanceCm >= 30) {
            return 'AMAN';
        } elseif ($distanceCm >= 25) {
            return 'SIAGA';
        }

        return 'DARURAT';
    }

    /**
     * Label arus (flow_lpm)
     * >= 80 => DARURAT
     * >= 40 => SIAGA
     * <  40 => AMAN
     */
    private function classifyFlow(?float $flowLpm): string
    {
        if ($flowLpm === null || $flowLpm < 0) {
            return '-';
        }

        if ($flowLpm >= 80) {
            return 'DARURAT';
        } elseif ($flowLpm >= 40) {
            return 'SIAGA';
        }

        return 'AMAN';
    }

    /**
     * Kombinasi parameter -> status node (ambil yang paling parah)
     */
    private function classifyNode(?float $distanceCm, ?float $flowLpm): string
    {
        $d = $this->classifyDistance($distanceCm);
        $f = $this->classifyFlow($flowLpm);

        // ranking tingkat bahaya
        $rank = ['-' => 0, 'AMAN' => 1, 'SIAGA' => 2, 'DARURAT' => 3];

        $maxRank = max($rank[$d] ?? 0, $rank[$f] ?? 0);

        if ($maxRank === 0) return '-';

        // cari label dari rank
        foreach ($rank as $label => $r) {
            if ($r === $maxRank) return $label;
        }

        return '-';
    }

    /**
     * Ambil waktu dari data firebase (kalau ada), fallback ke waktu server
     * Support:
     * - logged_at: "2025-12-11 23:01:13" atau "2025-12-11T23:01:13"
     * - logged_epoch_ms: 1733958073000
     */
    private function pickTimestampReadable(array $dev): string
    {
        // 1) logged_at string
        if (!empty($dev['logged_at']) && is_string($dev['logged_at'])) {
            $raw = trim($dev['logged_at']);
            // normalize "2025-12-11T23:01:13" -> "2025-12-11 23:01:13"
            $raw = str_replace('T', ' ', $raw);
            return $raw;
        }

        // 2) logged_epoch_ms (milliseconds)
        if (isset($dev['logged_epoch_ms']) && is_numeric($dev['logged_epoch_ms'])) {
            $ms = (int)$dev['logged_epoch_ms'];
            if ($ms > 0) {
                $sec = (int) floor($ms / 1000);
                return date('Y-m-d H:i:s', $sec);
            }
        }

        // 3) fallback server time
        return date('Y-m-d H:i:s');
    }

    public function index()
    {
        helper('telegram'); // pastikan helper telegram sudah ada

        $cache    = \Config\Services::cache();
        $firebase = new FirebaseModel();

        // expected format:
        // $devices['NODE1']['distance_cm'], $devices['NODE1']['flow_lpm'], ...
        $devices = $firebase->getDevices();
        if (!is_array($devices)) $devices = [];

        $rank = ['-' => 0, 'AMAN' => 1, 'SIAGA' => 2, 'DARURAT' => 3];
        $worstRank = 0;

        foreach ($devices as $id => &$dev) {
            if (!is_array($dev)) $dev = [];

            // === ambil nilai dari firebase (sesuaikan key kalau beda) ===
            $distance = isset($dev['distance_cm']) && is_numeric($dev['distance_cm'])
                ? (float)$dev['distance_cm']
                : null;

            $flow = isset($dev['flow_lpm']) && is_numeric($dev['flow_lpm'])
                ? (float)$dev['flow_lpm']
                : null;

            // === label per-parameter ===
            $dev['label_distance'] = $this->classifyDistance($distance);
            $dev['label_flow']     = $this->classifyFlow($flow);

            // === label node (worst-case) ===
            $dev['label_node'] = $this->classifyNode($distance, $flow);

            // buat kompatibel sama view lama kamu kalau masih pakai label_param
            $dev['label_param'] = $dev['label_node'];

            // === timestamp tampil (ambil dari firebase kalau ada) ===
            $dev['updated_readable'] = $this->pickTimestampReadable($dev);

            // overall status (ambil yang paling parah dari semua node)
            $worstRank = max($worstRank, $rank[$dev['label_node']] ?? 0);

            // === Telegram notif kalau DARURAT (anti-spam per node) ===
            if ($dev['label_node'] === 'DARURAT') {
                $cacheKey = 'tg_alert_' . $id;

                if (!$cache->get($cacheKey)) {
                    $msg = "<b>🚨 STATUS DARURAT</b>\n"
                        . "<b>Node:</b> {$id}\n"
                        . "<b>Ketinggian (cm):</b> " . ($distance ?? '-') . "\n"
                        . "<b>Arus (L/min):</b> " . ($flow ?? '-') . "\n"
                        . "<b>Waktu:</b> " . $dev['updated_readable'];

                    sendTelegramAlert($msg);

                    // tahan 10 menit biar ga spam
                    $cache->save($cacheKey, true, 600);
                }
            }
        }
        unset($dev);

        // status keseluruhan
        $overallStatus = '-';
        foreach ($rank as $label => $r) {
            if ($r === $worstRank) {
                $overallStatus = $label;
                break;
            }
        }

        // sesuaikan ini dengan view publik kamu
        return view('dashboard/public', [
            'title'         => 'Dashboard Publik',
            'nodes'         => $devices,
            'overallStatus' => $overallStatus,
        ]);
    }
}
