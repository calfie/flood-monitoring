<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\FirebaseModel;
use DateTimeImmutable;
use DateTimeZone;
use DateInterval;

class Admin extends BaseController
{
    // ================== LOGIN ==================

    public function login()
    {
        if (session()->get('admin_logged_in')) {
            return redirect()->to(base_url('admin'));
        }

        return view('admin/login', [
            'title' => 'Admin Login',
        ]);
    }

    public function doLogin()
    {
        $username = $this->request->getPost('username');
        $password = $this->request->getPost('password');

        if (!$username || !$password) {
            return redirect()->back()
                ->with('error', 'Username dan password wajib diisi')
                ->withInput();
        }

        $firebase = new FirebaseModel();
        $user     = $firebase->findUserByUsername($username);

        // ambil dari /users di Firebase (password plain dulu)
        if (!$user || ($user['password'] ?? '') !== $password) {
            return redirect()->back()
                ->with('error', 'Username atau password salah')
                ->withInput();
        }

        session()->set([
            'admin_logged_in' => true,
            'admin_username'  => $user['username'],
            'admin_role'      => $user['role'] ?? '',
            'admin_id'        => $user['id'] ?? '',
        ]);

        return redirect()->to(base_url('admin'));
    }

    public function logout()
    {
        session()->destroy();
        return redirect()->to(base_url('admin/login'));
    }

    // =============== HELPER STATUS BANJIR (pakai arus + jarak) ===============

    private function classifyDistance(?float $distanceCm): string
    {
        if ($distanceCm === null || $distanceCm < 0) {
            return '-';
        }

        if ($distanceCm >= 30) {
            return 'AMAN';
        } elseif ($distanceCm >= 25) { // 50–99
            return 'SIAGA';
        } else { // 0–49
            return 'DARURAT';
        }
    }

    private function classifyFlow(?float $flowLpm): string
    {
        if ($flowLpm === null || $flowLpm < 0) {
            return '-';
        }

        if ($flowLpm >= 80) {
            return 'DARURAT';
        } elseif ($flowLpm >= 40) { // 20–39
            return 'SIAGA';
        }

        return 'AMAN'; // < 20
    }

    /**
     * Kombinasi jarak + arus → label node
     */
    private function classifyNode(?float $distanceCm, ?float $flowLpm): string
    {
        $d = $this->classifyDistance($distanceCm);
        $f = $this->classifyFlow($flowLpm);

        $rank = ['AMAN' => 1, 'SIAGA' => 2, 'DARURAT' => 3];

        $dRank = $rank[$d] ?? 0;
        $fRank = $rank[$f] ?? 0;

        $maxRank = max($dRank, $fRank);

        if ($maxRank === 0) {
            return '-';
        }

        return array_search($maxRank, $rank, true);
    }

    // ================== DASHBOARD ADMIN ==================

    public function index()
    {
        if (!session()->get('admin_logged_in')) {
            return redirect()->to(base_url('admin/login'));
        }

        $firebase = new FirebaseModel();
        $devices  = $firebase->getDevices();   // NODE1, NODE2, ...

        $statusRank  = ['AMAN' => 1, 'SIAGA' => 2, 'DARURAT' => 3];
        $worstRank   = 0;
        $hasDevice   = !empty($devices);

        $tz   = new DateTimeZone('Asia/Jakarta');
        $now  = new DateTimeImmutable('now', $tz);
        $nowTs = $now->getTimestamp();
        $onlineThresholdSec = 30; // 30 detik

        foreach ($devices as $id => &$dev) {

            // gunakan logged_at dari Firebase untuk waktu update & status online
            $loggedAtStr = $dev['logged_at'] ?? null;
            $dev['updated_ms_readable'] = '-';
            $dev['status'] = 'offline';

            if ($loggedAtStr) {
                $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $loggedAtStr, $tz);
                if ($dt !== false) {
                    $dev['updated_ms_readable'] = $dt->format('Y-m-d H:i:s');

                    $diff = $nowTs - $dt->getTimestamp();
                    if ($diff >= 0 && $diff <= $onlineThresholdSec) {
                        $dev['status'] = 'online';
                    }
                }
            }

            $distance = isset($dev['distance_cm']) ? (float) $dev['distance_cm'] : null;
            $flow     = isset($dev['flow_lpm'])    ? (float) $dev['flow_lpm']    : null;

            $label = $this->classifyNode($distance, $flow);
            $dev['label_param'] = $label;

            if (isset($statusRank[$label]) && $statusRank[$label] > $worstRank) {
                $worstRank = $statusRank[$label];
            }
        }
        unset($dev);

        $overallStatus = '-';
        if ($hasDevice && $worstRank > 0) {
            $overallStatus = array_search($worstRank, $statusRank, true);
        }

        return view('admin/dashboard', [
            'title'         => 'Dashboard Admin',
            'nodes'         => $devices,
            'overallStatus' => $overallStatus,
            'username'      => session()->get('admin_username'),
        ]);
    }

    // ================== LOG DATA SENSOR ==================

    public function logSensor()
    {
        if (!session()->get('admin_logged_in')) {
            return redirect()->to(base_url('admin/login'));
        }

        $firebase = new FirebaseModel();
        $devices  = $firebase->getDevices();         // /devices
        $nodeIds  = array_keys($devices ?: []);      // contoh: ['NODE1', 'NODE2']

        return view('admin/log_sensor', [
            'title'    => 'Log Data Sensor',
            'username' => session()->get('admin_username') ?? 'admin',
            'nodeIds'  => $nodeIds,
        ]);
    }

    /**
     * AJAX: /admin/load-sensor?node=NODE1&range=today|7d|30d
     * return JSON array untuk tabel log_sensor.
     */
    public function loadSensor()
    {
        if (!session()->get('admin_logged_in')) {
            return $this->response->setStatusCode(401)->setJSON(['error' => 'Unauthorized']);
        }

        $node  = $this->request->getGet('node');
        $range = $this->request->getGet('range') ?: '30d';

        if (!$node) {
            return $this->response->setJSON([]);
        }

        $firebase = new FirebaseModel();

        // ambil history node terkait
        $rows = $firebase->getHistory($node);   // array history/NODE1/...

        // ambil devices buat status online/offline
        $devices = $firebase->getDevices();

        $tz      = new DateTimeZone('Asia/Jakarta');
        $now     = new DateTimeImmutable('now', $tz);
        $nowTs   = $now->getTimestamp();
        $onlineThresholdSec = 30;

        // status perangkat sekarang
        $deviceStatus = 'offline';

        if (isset($devices[$node])) {
            $dev = $devices[$node];
            $loggedAtStr = $dev['logged_at'] ?? null;

            if ($loggedAtStr) {
                $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $loggedAtStr, $tz);
                if ($dt !== false) {
                    $diff = $nowTs - $dt->getTimestamp();
                    if ($diff >= 0 && $diff <= $onlineThresholdSec) {
                        $deviceStatus = 'online';
                    }
                }
            }
        }

        // tentukan batas waktu range
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

            if ($loggedAtStr) {
                $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $loggedAtStr, $tz);
                if ($dt !== false) {
                    $ts = $dt->getTimestamp();
                }
            }

            if (!$dt && !empty($row['time_node'])) {
                $fakeStr = $now->format('Y-m-d') . ' ' . $row['time_node'];
                $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $fakeStr, $tz);
                if ($dt !== false) {
                    $ts = $dt->getTimestamp();
                }
            }

            if ($ts !== null && $startTs !== null) {
                if ($ts < $startTs || $ts > $endTs) {
                    continue;
                }
            }

            $dateStr = '-';
            $timeStr = '-';

            if ($dt) {
                $dateStr = $dt->format('d/m/Y');
                $timeStr = $dt->format('H.i.s');
            } elseif (!empty($row['time_node'])) {
                $timeStr = $row['time_node'];
            }

            $distance = isset($row['distance_cm']) ? (float) $row['distance_cm'] : null;
            $flow     = isset($row['flow_lpm'])    ? (float) $row['flow_lpm']    : null;
            $label    = $this->classifyNode($distance, $flow);

            $out[] = [
                'ts'       => $ts ?? 0,
                'key'      => $row['key'] ?? '',
                'date'     => $dateStr,
                'time'     => $timeStr,
                'node'     => $node,
                'distance' => isset($row['distance_cm']) ? (string) $row['distance_cm'] : '-',
                'flow'     => isset($row['flow_lpm'])    ? (string) $row['flow_lpm']    : '-',
                'buzzer'   => isset($row['buzzer'])      ? (string) $row['buzzer']      : '-',
                'status'   => $deviceStatus,
                'label'    => $label,
            ];
        }

        usort($out, function ($a, $b) {
            return ($b['ts'] <=> $a['ts']);
        });

        $outClean = array_map(function ($row) {
            unset($row['ts']);
            return $row;
        }, $out);

        return $this->response->setJSON($outClean);
    }

    // ================== LOG QoS ==================

    public function logQos()
    {
        if (!session()->get('admin_logged_in')) {
            return redirect()->to(base_url('admin/login'));
        }

        $firebase = new FirebaseModel();
        $devices  = $firebase->getDevices();
        $nodeIds  = array_keys($devices ?: []);

        return view('admin/log_qos', [
            'title'    => 'Log QoS Jaringan',
            'username' => session()->get('admin_username') ?? 'admin',
            'nodeIds'  => $nodeIds,
        ]);
    }

    /**
     * AJAX: /admin/load-qos?node=NODE1&range=today|7d|30d
     * return JSON array untuk tabel log_qos.
     */
    public function loadQos()
    {
        if (!session()->get('admin_logged_in')) {
            return $this->response->setStatusCode(401)->setJSON(['error' => 'Unauthorized']);
        }

        $node  = $this->request->getGet('node');
        $range = $this->request->getGet('range') ?: '30d';

        if (!$node) {
            return $this->response->setJSON([]);
        }

        $firebase = new FirebaseModel();

        $rows    = $firebase->getHistory($node); // sumber QoS dari history
        $devices = $firebase->getDevices();

        $tz      = new DateTimeZone('Asia/Jakarta');
        $now     = new DateTimeImmutable('now', $tz);
        $nowTs   = $now->getTimestamp();
        $onlineThresholdSec = 30;

        // status perangkat sekarang
        $deviceStatus = 'offline';
        if (isset($devices[$node])) {
            $dev = $devices[$node];
            $loggedAtStr = $dev['logged_at'] ?? null;

            if ($loggedAtStr) {
                $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $loggedAtStr, $tz);
                if ($dt !== false) {
                    $diff = $nowTs - $dt->getTimestamp();
                    if ($diff >= 0 && $diff <= $onlineThresholdSec) {
                        $deviceStatus = 'online';
                    }
                }
            }
        }

        // tentukan batas waktu range
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

            // pakai logged_at dari history
            $loggedAtStr = $row['logged_at'] ?? null;
            $dt = null;
            $ts = null;

            if ($loggedAtStr) {
                $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $loggedAtStr, $tz);
                if ($dt !== false) {
                    $ts = $dt->getTimestamp();
                }
            }

            // kalau log lama nggak punya logged_at, boleh pakai time_node sebagai jam hari ini
            if (!$dt && !empty($row['time_node'])) {
                $fakeStr = $now->format('Y-m-d') . ' ' . $row['time_node'];
                $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $fakeStr, $tz);
                if ($dt !== false) {
                    $ts = $dt->getTimestamp();
                }
            }

            if ($ts !== null && $startTs !== null) {
                if ($ts < $startTs || $ts > $endTs) {
                    continue;
                }
            }

            $dateStr = '-';
            $timeStr = '-';

            if ($dt) {
                $dateStr = $dt->format('d/m/Y');
                $timeStr = $dt->format('H.i.s');
            } elseif (!empty($row['time_node'])) {
                $timeStr = $row['time_node'];
            }

            $delay  = isset($row['delay_ms']) ? (float)$row['delay_ms'] : 0.0;
            $ploss  = isset($row['packet_loss_percent']) ? (float)$row['packet_loss_percent'] : 0.0;
            $thput  = isset($row['throughput_bps']) ? (float)$row['throughput_bps'] : 0.0;
            $jitter = isset($row['jitter_ms']) ? (float)$row['jitter_ms'] : 0.0;

            $out[] = [
                'ts'        => $ts ?? 0,
                'key'       => $row['key'] ?? '',
                'date'      => $dateStr,
                'time'      => $timeStr,
                'node'      => $node,
                'delay_ms'  => $delay,
                'ploss'     => $ploss,
                'thput'     => $thput,
                'jitter_ms' => $jitter,
                'status'    => $deviceStatus,
            ];
        }

        usort($out, function ($a, $b) {
            return ($b['ts'] <=> $a['ts']);
        });

        $outClean = array_map(function ($row) {
            unset($row['ts']);
            return $row;
        }, $out);

        return $this->response->setJSON($outClean);
    }
    public function logSignal()
    {
        if (!session()->get('admin_logged_in')) {
            return redirect()->to(base_url('admin/login'));
        }

        $firebase = new FirebaseModel();
        $devices  = $firebase->getDevices();
        $nodeIds  = array_keys($devices ?: []);

        return view('admin/log_signal', [
            'title'    => 'Log RSSI & SNR',
            'username' => session()->get('admin_username') ?? 'admin',
            'nodeIds'  => $nodeIds,
        ]);
    }

    // ================== HAPUS LOG (1 & BANYAK) ==================

    /**
     * Hapus satu log history (dipanggil dari tombol "Hapus" per baris)
     * POST /admin/delete-log
     * body: node=NODE1&key=FIREBASE_PUSH_KEY
     */
    public function deleteLog()
    {
        if (!session()->get('admin_logged_in')) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON([
                    'status'  => 'error',
                    'message' => 'Unauthorized',
                ]);
        }

        $node = (string)$this->request->getPost('node');
        $key  = (string)$this->request->getPost('key');

        if ($node === '' || $key === '') {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'status'  => 'error',
                    'message' => 'Node atau key kosong',
                ]);
        }

        $firebase = new FirebaseModel();
        $ok       = $firebase->deleteHistoryEntry($node, $key);

        if (!$ok) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON([
                    'status'  => 'error',
                    'message' => 'Gagal menghapus data di Firebase',
                ]);
        }

        return $this->response->setJSON([
            'status'  => 'ok',
            'message' => 'Log berhasil dihapus',
        ]);
    }
    public function loadSignal()
    {
        if (!session()->get('admin_logged_in')) {
            return $this->response->setStatusCode(401)->setJSON(['error' => 'Unauthorized']);
        }

        $node  = $this->request->getGet('node');
        $range = $this->request->getGet('range') ?: '30d';

        if (!$node) {
            return $this->response->setJSON([]);
        }

        $firebase = new FirebaseModel();
        $rows     = $firebase->getHistory($node);

        $tz    = new DateTimeZone('Asia/Jakarta');
        $now   = new DateTimeImmutable('now', $tz);
        $nowTs = $now->getTimestamp();

        // tentukan batas waktu range
        $startTs = null;
        $endTs   = $nowTs;

        if ($range === 'today') {
            $startTs = $now->setTime(0, 0, 0)->getTimestamp();
        } elseif ($range === '7d') {
            $startTs = $now->sub(new DateInterval('P7D'))->getTimestamp();
        } else { // default 30d
            $startTs = $now->sub(new DateInterval('P30D'))->getTimestamp();
        }

        $out = [];

        foreach ($rows as $row) {
            if (!is_array($row)) continue;

            $loggedAtStr = $row['logged_at'] ?? null;
            $dt = null;
            $ts = null;

            // 1) pakai logged_at (recommended)
            if ($loggedAtStr) {
                $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $loggedAtStr, $tz);
                if ($dt !== false) $ts = $dt->getTimestamp();
            }

            // 2) fallback kalau log lama pakai time_node (anggap hari ini)
            if (!$dt && !empty($row['time_node'])) {
                $fakeStr = $now->format('Y-m-d') . ' ' . $row['time_node'];
                $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $fakeStr, $tz);
                if ($dt !== false) $ts = $dt->getTimestamp();
            }

            // filter range
            if ($ts !== null && $startTs !== null) {
                if ($ts < $startTs || $ts > $endTs) continue;
            }

            $dateStr = '-';
            $timeStr = '-';

            if ($dt) {
                $dateStr = $dt->format('d/m/Y');
                $timeStr = $dt->format('H.i.s');
            } elseif (!empty($row['time_node'])) {
                $timeStr = $row['time_node'];
            }

            // ambil sinyal dari history
            $rssiVal = isset($row['rssi_dbm']) ? (float)$row['rssi_dbm'] : null;
            $snrVal  = isset($row['snr_db'])   ? (float)$row['snr_db']   : null;

            $labelRssi   = $this->classifyRssi($rssiVal);
            $labelSnr    = $this->classifySnr($snrVal);
            $labelSignal = $this->combineSignalLabel($labelRssi, $labelSnr);

            $out[] = [
                'ts'           => $ts ?? 0,
                'key'          => $row['key'] ?? '',
                'date'         => $dateStr,
                'time'         => $timeStr,
                'node'         => $node,
                'rssi'         => isset($row['rssi_dbm']) ? (string)$row['rssi_dbm'] : '-',
                'snr'          => isset($row['snr_db'])   ? (string)$row['snr_db']   : '-',
                'label_rssi'   => $labelRssi,
                'label_snr'    => $labelSnr,
                'label_signal' => $labelSignal,
            ];
        }

        usort($out, fn($a, $b) => ($b['ts'] <=> $a['ts']));

        // buang ts sebelum dikirim ke client
        $outClean = array_map(function ($r) {
            unset($r['ts']);
            return $r;
        }, $out);

        return $this->response->setJSON($outClean);
    }

    /**
     * Hapus banyak log sekaligus
     * POST /admin/delete-logs
     * body: node=NODE1&keys[]=KEY1&keys[]=KEY2&...
     */
    public function deleteLogs()
    {
        if (!session()->get('admin_logged_in')) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON([
                    'status'  => 'error',
                    'message' => 'Unauthorized',
                ]);
        }

        $node = (string)$this->request->getPost('node');
        $keys = $this->request->getPost('keys'); // harus array

        if ($node === '' || !is_array($keys) || empty($keys)) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'status'  => 'error',
                    'message' => 'Node atau daftar key tidak valid',
                ]);
        }

        $firebase = new FirebaseModel();
        $deleted  = 0;
        $failed   = [];

        foreach ($keys as $key) {
            $key = (string)$key;
            if ($key === '') {
                continue;
            }

            $ok = $firebase->deleteHistoryEntry($node, $key);
            if ($ok) {
                $deleted++;
            } else {
                $failed[] = $key;
            }
        }

        return $this->response->setJSON([
            'status'  => 'ok',
            'deleted' => $deleted,
            'failed'  => $failed,
        ]);
    }

    // ================== KELOLA ADMIN ==================

    public function users()
    {
        if (!session()->get('admin_logged_in')) {
            return redirect()->to(base_url('admin/login'));
        }

        $firebase = new FirebaseModel();
        $users    = $firebase->getUsers();   // /users dari Firebase

        return view('admin/users', [
            'title'    => 'Kelola Admin',
            'username' => session()->get('admin_username') ?? 'admin',
            'users'    => $users,
        ]);
    }

    public function createUser()
    {
        if (!session()->get('admin_logged_in')) {
            return redirect()->to(base_url('admin/login'));
        }

        $username = trim((string) $this->request->getPost('username'));
        $password = trim((string) $this->request->getPost('password'));
        $role     = trim((string) $this->request->getPost('role')) ?: 'admin';

        if ($username === '' || $password === '') {
            return redirect()->back()
                ->with('error', 'Username dan password tidak boleh kosong')
                ->withInput();
        }

        $firebase = new FirebaseModel();

        // cek duplikat username
        if ($firebase->findUserByUsername($username)) {
            return redirect()->back()
                ->with('error', 'Username sudah digunakan')
                ->withInput();
        }

        $result = $firebase->createUser($username, $password, $role);

        if ($result === null) {
            return redirect()->back()
                ->with('error', 'Gagal menambah admin')
                ->withInput();
        }

        return redirect()->to(base_url('admin/users'))
            ->with('success', 'Admin baru berhasil ditambahkan');
    }

    public function deleteUser(string $id)
    {
        if (!session()->get('admin_logged_in')) {
            return redirect()->to(base_url('admin/login'));
        }

        $currentId = session()->get('admin_id');

        // biar nggak ngehapus akun sendiri
        if ($id === $currentId) {
            return redirect()->to(base_url('admin/users'))
                ->with('error', 'Tidak dapat menghapus akun yang sedang digunakan');
        }

        $firebase = new FirebaseModel();
        $ok       = $firebase->deleteUser($id);

        if (!$ok) {
            return redirect()->to(base_url('admin/users'))
                ->with('error', 'Gagal menghapus admin');
        }

        return redirect()->to(base_url('admin/users'))
            ->with('success', 'Admin berhasil dihapus');
    }
}
