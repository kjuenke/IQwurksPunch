<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

class SettingsService
{
    public function get(string $key): ?string
    {
        $db = Database::connection();

        $stmt = $db->prepare(
            "SELECT setting_value 
             FROM settings 
             WHERE setting_key = :key"
        );

        $stmt->execute([
            'key' => $key
        ]);

        $result = $stmt->fetchColumn();

        return $result !== false ? $result : null;
    }


    public function set(string $key, string $value): void
    {
        $db = Database::connection();

        $stmt = $db->prepare(
            "
            INSERT INTO settings
            (setting_key, setting_value)
            VALUES (:key, :value)
            ON CONFLICT(setting_key)
            DO UPDATE SET setting_value=:value
            "
        );

        $stmt->execute([
            'key' => $key,
            'value' => $value
        ]);
    }
}
