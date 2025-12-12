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

    /**
     * Ambil waktu dari Firebase:
     * - Prioritas 1: logged_at (string "YYYY-mm-dd HH:ii:ss")
     * - Fallback: logged_epoch_ms (angka epoch ms) -> convert ke WIB
     */
    private function getLoggedAt(array $dev): string
    {
        if (!empty($dev['logged_at'])) {
            return (string) $dev['logged_at'];
        }

        if (!empty($dev['logged_epoch_ms']) && is_numeric($dev['logged_epoch_ms'])) {
            $ms = (int) $dev['logged_epoch_ms'];
            $dt = (new \DateTimeImmutable('@' . (int) ($ms / 1000)))
                ->setTimezone(new \DateTimeZone('Asia/Jakarta'));
            return $dt->format('Y-m-d H:i:s');
        }

        return '-';
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
            // === AMBIL DATA UTAMA DARI FIREBASE ===
            $distance = isset($dev['distance_cm']) ? (float) $dev['distance_cm'] : null;
            $arus     = $dev['flow_lpm'] ?? ($dev['arus'] ?? '-'); // fleksibel
            $loggedAt = $this->getLoggedAt($dev);

            // === LABEL STATUS ===
            $label = $this->classifyStatus($distance);
            $dev['label_param'] = $label;

            // === WAKTU TAMPILAN (PAKAI FIREBASE, BUKAN SERVER) ===
            $dev['updated_ms_readable'] = $loggedAt;

            // worst overall status
            $worstRank = max($worstRank, $statusRank[$label] ?? 0);

            // === TELEGRAM ALERT SAAT DARURAT (anti spam per node) ===
            if ($label === 'DARURAT') {
                $key = 'tg_alert_' . $id;

                if (!$cache->get($key)) {
                    // yang dikirim: samain dengan yang tampil di web
                    $tinggi = ($distance !== null) ? $distance . " cm" : "-";
                    $arusTxt = (is_numeric($arus)) ? $arus . " L/min" : (string) $arus;

                    $msg = "<b>Flood Monitoring - enyoderamil</b>\n"
                        . "🚨 <b>STATUS DARURAT</b>\n"
                        . "<b>Node:</b> {$id}\n"
                        . "<b>Ketinggian:</b> {$tinggi}\n"
                        . "<b>Arus:</b> {$arusTxt}\n"
                        . "<b>Waktu:</b> {$loggedAt}";

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
