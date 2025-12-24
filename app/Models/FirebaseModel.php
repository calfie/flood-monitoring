<?php

namespace App\Models;


class FirebaseModel
{
    protected string $firebaseBaseUrl = 'https://enyoderamil-default-rtdb.asia-southeast1.firebasedatabase.app';

    /**
     * Helper GET JSON sederhana ke Firebase
     */
    protected function getJson(string $path)
    {
        $url = rtrim($this->firebaseBaseUrl, '/') . '/' . ltrim($path, '/') . '.json';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        if ($response === false || $response === null) {
            return null;
        }

        return json_decode($response, true);
    }

    /**
     * Helper PATCH JSON ke Firebase
     */
    protected function patchJson(string $path, array $payload): bool
    {
        $url = rtrim($this->firebaseBaseUrl, '/') . '/' . ltrim($path, '/') . '.json';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => 'PATCH',
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 5,
        ]);

        $response = curl_exec($ch);
        $err      = curl_error($ch);
        curl_close($ch);

        return $response !== false && $err === '';
    }

    /**
     * Helper DELETE path di Firebase
     */
    protected function deletePath(string $path): bool
    {
        $url = rtrim($this->firebaseBaseUrl, '/') . '/' . ltrim($path, '/') . '.json';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => 'DELETE',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
        ]);

        $response = curl_exec($ch);
        $err      = curl_error($ch);
        curl_close($ch);

        return $response !== false && $err === '';
    }

    // ==================== DEVICES (realtime) ====================

    public function getDevices(): array
    {
        $data = $this->getJson('devices');
        return is_array($data) ? $data : [];
    }

    public function getDevice(string $nodeId): ?array
    {
        $devices = $this->getDevices();
        return $devices[$nodeId] ?? null;
    }

    /**
     * (opsional) simpan mode buzzer di node, kalau mau ditampilkan di web
     */
    public function setBuzzerControlFlag(string $nodeId, string $mode): bool
    {
        return $this->patchJson("devices/{$nodeId}", [
            'buzzer_control' => $mode, // "on" / "off" / "auto"
        ]);
    }

    /**
     * Set perintah remote buzzer:
     *   /command/node   = "NODE1"
     *   /command/action = "ON" / "OFF"
     * Ini yang dibaca ESP32 central.
     */
    public function setBuzzerCommand(string $nodeId, string $mode): bool
    {
        $mode = strtolower($mode);
        if (!in_array($mode, ['on', 'off'], true)) {
            return false;
        }

        $action = strtoupper($mode); // "ON" / "OFF"

        // tulis sekali patch ke path /command
        return $this->patchJson('command', [
            'node'   => $nodeId,   // misal "NODE1"
            'action' => $action,   // "ON" atau "OFF"
        ]);
    }

    // ==================== HISTORY (untuk grafik & log) ====================

    public function getHistory(string $nodeId): array
    {
        $data = $this->getJson("history/{$nodeId}");
        if (!is_array($data)) {
            return [];
        }

        ksort($data); // sort berdasarkan key push

        $rows = [];
        foreach ($data as $key => $row) {
            if (!is_array($row)) {
                continue;
            }
            // simpan key push Firebase untuk dipakai hapus
            $row['key'] = $key;
            $rows[]     = $row;
        }

        return $rows;
    }

    /**
     * Hapus satu entri history berdasarkan node dan key
     * path: /history/{NODE_ID}/{key}
     */
    public function deleteHistoryEntry(string $nodeId, string $key): bool
    {
        if ($nodeId === '' || $key === '') {
            return false;
        }

        return $this->deletePath("history/{$nodeId}/{$key}");
    }

    // ==================== USERS (admin login & kelola admin) ====================

    public function getUsers(): array
    {
        $users = $this->getJson('users');
        if (!is_array($users)) {
            return [];
        }

        $out = [];
        foreach ($users as $id => $user) {
            if (!is_array($user)) {
                continue;
            }
            $user['id'] = $id;   // simpan ID_x
            $out[$id]   = $user;
        }

        return $out;
    }

    public function findUserByUsername(string $username): ?array
    {
        $users = $this->getJson('users');
        if (!is_array($users)) {
            return null;
        }

        foreach ($users as $id => $user) {
            if (!is_array($user)) {
                continue;
            }

            if (($user['username'] ?? '') === $username) {
                $user['id'] = $id;
                return $user;
            }
        }

        return null;
    }

    public function createUser(string $username, string $password, string $role = 'admin'): ?array
    {
        $users   = $this->getJson('users');
        $nextNum = 1;

        if (is_array($users)) {
            foreach ($users as $id => $_row) {
                if (preg_match('/^ID_(\d+)$/', $id, $m)) {
                    $n = (int) $m[1];
                    if ($n >= $nextNum) {
                        $nextNum = $n + 1;
                    }
                }
            }
        }

        $newId   = 'ID_' . $nextNum;
        $payload = [
            'username' => $username,
            'password' => $password, // untuk tugas boleh plain text dulu
            'role'     => $role,
        ];

        if (!$this->patchJson("users/{$newId}", $payload)) {
            return null;
        }

        $payload['id'] = $newId;
        return $payload;
    }

    public function updateUser(string $id, array $data): bool
    {
        $allowed = ['username', 'password', 'role'];
        $payload = [];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $data[$field];
            }
        }

        if (empty($payload)) {
            return false;
        }

        return $this->patchJson("users/{$id}", $payload);
    }

    public function deleteUser(string $id): bool
    {
        return $this->deletePath("users/{$id}");
    }
}
