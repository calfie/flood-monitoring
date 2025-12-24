<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\FirebaseModel;
use DateTimeImmutable;
use DateTimeZone;
use DateInterval;

class Api extends BaseController
{
    /**
     * API untuk dashboard publik & admin: ambil devices realtime
     */
    public function devices()
    {
        $firebase = new FirebaseModel();
        $devices  = $firebase->getDevices();

        return $this->response->setJSON($devices);
    }

    /**
     * API history: /api/history/NODE1?range=today|7d|30d
     * Dipakai grafik di dashboard admin & publik.
     * Filter waktu berdasarkan field logged_at di /history/NODE_X.
     */
    public function history(string $nodeId)
    {
        $range    = $this->request->getGet('range') ?: '30d'; // today / 7d / 30d
        $firebase = new FirebaseModel();
        $rows     = $firebase->getHistory($nodeId);

        $tz    = new DateTimeZone('Asia/Jakarta');
        $now   = new DateTimeImmutable('now', $tz);
        $nowTs = $now->getTimestamp();

        // batas waktu
        $startTs = null;
        $endTs   = $nowTs;

        if ($range === 'today') {
            $startOfDay = $now->setTime(0, 0, 0);
            $startTs    = $startOfDay->getTimestamp();
        } elseif ($range === '7d') {
            $startTs = $now->sub(new DateInterval('P7D'))->getTimestamp();
        } elseif ($range === '30d') {
            $startTs = $now->sub(new DateInterval('P30D'))->getTimestamp();
        }

        $out = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $loggedAtStr = $row['logged_at'] ?? null;
            $dt = null;
            $ts = null;

            // format normal: "2025-11-23 00:49:38"
            if ($loggedAtStr) {
                $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $loggedAtStr, $tz);
                if ($dt !== false) {
                    $ts = $dt->getTimestamp();
                }
            }

            // backup: kalau cuma punya time_node "HH:MM:SS"
            if (!$dt && !empty($row['time_node'])) {
                $fakeStr = $now->format('Y-m-d') . ' ' . $row['time_node'];
                $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $fakeStr, $tz);
                if ($dt !== false) {
                    $ts = $dt->getTimestamp();
                }
            }

            // kalau pakai filter range & kita punya timestamp, cek apakah masuk range
            if ($ts !== null && $startTs !== null) {
                if ($ts < $startTs || $ts > $endTs) {
                    continue;
                }
            }

            // kalau ts null dan ada filter range, skip saja (biar tidak nyampur data lama aneh)
            if ($ts === null && $startTs !== null) {
                continue;
            }

            // simpan ts sementara buat sorting
            $row['_ts'] = $ts ?? 0;
            $out[] = $row;
        }

        // sort berdasar waktu dari lama ke terbaru
        usort($out, function ($a, $b) {
            return ($a['_ts'] <=> $b['_ts']);
        });

        $maxPoints = 300;
        if (count($out) > $maxPoints) {
            $out = array_slice($out, -$maxPoints);
        }

        $clean = array_map(function ($row) {
            unset($row['_ts']);
            return $row;
        }, $out);

        return $this->response->setJSON($clean);
    }

    /**
     * API kontrol buzzer dari admin
     * JS ngirim: POST /api/buzzer/NODE1  body JSON: { "state": "on" | "off" }
     * Lalu di-forward ke /command di Firebase.
     */
    public function buzzer(string $nodeId)
    {
        // baca JSON body
        $json  = $this->request->getJSON(true) ?? [];
        $state = $json['state'] ?? null; // "on" / "off"

        if (!in_array($state, ['on', 'off'], true)) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON(['error' => 'State harus "on" atau "off"']);
        }

        $firebase = new FirebaseModel();
        $ok       = $firebase->setBuzzerCommand($nodeId, $state);

        if (!$ok) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => 'Gagal mengirim perintah buzzer ke Firebase']);
        }

        // (opsional) update flag kontrol di node, kalau mau
        $firebase->setBuzzerControlFlag($nodeId, $state);

        return $this->response->setJSON([
            'status' => 'ok',
            'node'   => $nodeId,
            'state'  => $state,
        ]);
    }
}
