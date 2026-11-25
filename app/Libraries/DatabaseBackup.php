<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

class DatabaseBackup
{
    public const HEADER_MARKER = '-- PayTrack Backup v1';

    public const TABLES = [
        'users',
        'offices',
        'employees',
        'deductions',
        'payroll_records',
        'payroll_periods',
        'app_settings',
    ];

    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    public function exportSql(): string
    {
        $driver = $this->db->DBDriver;
        $lines = [
            self::HEADER_MARKER,
            '-- Generated: ' . date('Y-m-d H:i:s'),
            '-- Driver: ' . $driver,
            '',
            $driver === 'SQLite3' ? 'PRAGMA foreign_keys = OFF;' : 'SET FOREIGN_KEY_CHECKS = 0;',
            '',
        ];

        // Delete in reverse order on restore, insert in dependency order
        foreach (array_reverse(self::TABLES) as $table) {
            if ($this->db->tableExists($table)) {
                $full = $this->db->prefixTable($table);
                $lines[] = "DELETE FROM {$full};";
            }
        }
        $lines[] = '';

        foreach (self::TABLES as $table) {
            if (!$this->db->tableExists($table)) {
                continue;
            }
            $rows = $this->db->table($table)->get()->getResultArray();
            if ($rows === []) {
                continue;
            }

            $full = $this->db->prefixTable($table);
            $cols = array_keys($rows[0]);
            $colList = implode(', ', array_map(fn ($c) => $this->db->escapeIdentifiers($c), $cols));

            $lines[] = "-- Table: {$table} (" . count($rows) . " rows)";
            foreach ($rows as $row) {
                $vals = [];
                foreach ($cols as $col) {
                    $v = $row[$col];
                    $vals[] = $v === null ? 'NULL' : $this->db->escape($v);
                }
                $lines[] = "INSERT INTO {$full} ({$colList}) VALUES (" . implode(', ', $vals) . ");";
            }
            $lines[] = '';
        }

        $lines[] = $driver === 'SQLite3' ? 'PRAGMA foreign_keys = ON;' : 'SET FOREIGN_KEY_CHECKS = 1;';
        $lines[] = '';

        return implode("\n", $lines);
    }

    public function saveBackupFile(string $prefix = 'paytrack_backup', int $keep = 10): string
    {
        $dir = rtrim(WRITEPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'backups';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $guard = $dir . DIRECTORY_SEPARATOR . 'index.html';
        if (!is_file($guard)) {
            file_put_contents($guard, "<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><p>Directory access is forbidden.</p></body></html>\n");
        }

        $filename = $prefix . '_' . date('Y-m-d_His') . '.sql';
        $path = $dir . DIRECTORY_SEPARATOR . $filename;
        file_put_contents($path, $this->exportSql());

        // Rotate old files for this prefix
        $files = glob($dir . DIRECTORY_SEPARATOR . $prefix . '_*.sql') ?: [];
        rsort($files);
        foreach (array_slice($files, $keep) as $old) {
            @unlink($old);
        }

        return $path;
    }

    public function restoreSql(string $sql, bool $createSafetyBackup = true): array
    {
        $sql = ltrim($sql, "\xEF\xBB\xBF");
        if (strpos(trim($sql), self::HEADER_MARKER) !== 0) {
            throw new RuntimeException('Invalid backup file: missing PayTrack backup header.');
        }

        $safetyPath = $createSafetyBackup ? $this->saveBackupFile('pre_restore', 5) : null;
        $statements = $this->splitStatements($sql);
        if ($statements === []) {
            throw new RuntimeException('Backup file contains no SQL statements.');
        }

        $driver = $this->db->DBDriver;
        $this->db->transBegin();
        try {
            foreach ($statements as $stmt) {
                $upper = strtoupper(ltrim($stmt));
                // Skip cross-driver FK pragma/set statements so MySQL backups can be inspected safely
                if ($driver === 'SQLite3' && strpos($upper, 'SET FOREIGN_KEY_CHECKS') === 0) {
                    continue;
                }
                if ($driver !== 'SQLite3' && strpos($upper, 'PRAGMA FOREIGN_KEYS') === 0) {
                    continue;
                }
                $this->db->query($stmt);
            }
            if ($this->db->transStatus() === false) {
                throw new RuntimeException('Database error while restoring backup.');
            }
            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            $this->db->query($driver === 'SQLite3' ? 'PRAGMA foreign_keys = ON' : 'SET FOREIGN_KEY_CHECKS = 1');
            throw new RuntimeException('Restore failed and was rolled back: ' . $e->getMessage(), 0, $e);
        }

        return [
            'statements'  => count($statements),
            'safety_path' => $safetyPath,
        ];
    }

    /**
     * Splits SQL into individual statements while respecting single-quoted literals and -- comments.
     */
    private function splitStatements(string $sql): array
    {
        $statements = [];
        $current = '';
        $inQuote = false;
        $len = strlen($sql);

        for ($i = 0; $i < $len; $i++) {
            $ch = $sql[$i];

            if (!$inQuote && $ch === '-' && $i + 1 < $len && $sql[$i + 1] === '-') {
                while ($i < $len && $sql[$i] !== "\n") {
                    $i++;
                }
                continue;
            }

            if ($ch === "'") {
                $current .= $ch;
                if ($inQuote) {
                    if ($i + 1 < $len && $sql[$i + 1] === "'") {
                        $current .= $sql[++$i];
                    } else {
                        $inQuote = false;
                    }
                } else {
                    $inQuote = true;
                }
                continue;
            }

            if ($inQuote && $ch === '\\' && $i + 1 < $len) {
                $current .= $ch . $sql[++$i];
                continue;
            }

            if (!$inQuote && $ch === ';') {
                $trimmed = trim($current);
                if ($trimmed !== '') {
                    $statements[] = $trimmed;
                }
                $current = '';
                continue;
            }

            $current .= $ch;
        }

        $trimmed = trim($current);
        if ($trimmed !== '') {
            $statements[] = $trimmed;
        }

        return $statements;
    }
}
