<?php

namespace App\Models;

use CodeIgniter\Model;

class AppSettingModel extends Model
{
    protected $table            = 'app_settings';
    protected $primaryKey       = 'setting_key';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $allowedFields    = ['setting_key', 'setting_value', 'updated_at'];

    public const DEFAULTS = [
        'lgu_name'           => 'LGU-MAHINOG',
        'municipality_label' => 'Mahinog, Camiguin',
        'mayor_name'         => 'REY LAWRENCE K. TAN',
        'mayor_title'        => 'Municipal Mayor',
        'treasurer_name'     => 'MARY LUSSEL S. PACTO',
        'treasurer_title'    => 'Disbursing Officer',
        'last_backup_at'     => '',
    ];

    public function getAllSettings(): array
    {
        $settings = self::DEFAULTS;
        if (!$this->db->tableExists($this->table)) {
            return $settings;
        }
        foreach ($this->findAll() as $row) {
            $settings[$row['setting_key']] = (string) ($row['setting_value'] ?? '');
        }
        return $settings;
    }

    public function setMany(array $pairs): void
    {
        $now = date('Y-m-d H:i:s');
        foreach ($pairs as $key => $value) {
            $exists = $this->where('setting_key', $key)->countAllResults() > 0;
            if ($exists) {
                $this->update($key, ['setting_value' => (string) $value, 'updated_at' => $now]);
            } else {
                $this->insert(['setting_key' => $key, 'setting_value' => (string) $value, 'updated_at' => $now]);
            }
        }
    }
}
