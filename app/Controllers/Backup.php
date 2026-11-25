<?php

namespace App\Controllers;

use App\Libraries\DatabaseBackup;
use App\Models\AppSettingModel;

class Backup extends BaseController
{
    public function index()
    {
        if (session()->get('role') !== 'admin') {
            return redirect()->to('/dashboard')->with('error', 'Administrator access is required.');
        }

        $settings = (new AppSettingModel())->getAllSettings();
        $dir = rtrim(WRITEPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'backups';
        $localBackups = [];
        if (is_dir($dir)) {
            $files = glob($dir . DIRECTORY_SEPARATOR . '*.sql') ?: [];
            rsort($files);
            foreach (array_slice($files, 0, 15) as $file) {
                $localBackups[] = [
                    'name'     => basename($file),
                    'size'     => filesize($file),
                    'modified' => date('M d, Y g:i A', filemtime($file)),
                ];
            }
        }

        return view('backup/index', [
            'last_backup_at' => $settings['last_backup_at'] ?? '',
            'local_backups'  => $localBackups,
        ]);
    }

    public function download()
    {
        if (session()->get('role') !== 'admin') {
            return redirect()->to('/dashboard')->with('error', 'Administrator access is required.');
        }

        $backup = new DatabaseBackup();
        $savedPath = $backup->saveBackupFile('paytrack_backup', 10);
        (new AppSettingModel())->setMany(['last_backup_at' => date('Y-m-d H:i:s')]);

        return $this->response->download($savedPath, null)->setFileName(basename($savedPath));
    }

    public function restore()
    {
        if (session()->get('role') !== 'admin') {
            return redirect()->to('/dashboard')->with('error', 'Administrator access is required.');
        }

        if ($this->request->getPost('confirm') !== 'RESTORE') {
            return redirect()->to('/backup')->with('error', 'Please type RESTORE to confirm database restore.');
        }

        $file = $this->request->getFile('backup_file');
        if (!$file || !$file->isValid() || strtolower($file->getClientExtension()) !== 'sql') {
            return redirect()->to('/backup')->with('error', 'Please upload a valid PayTrack .sql backup file.');
        }

        try {
            $sql = file_get_contents($file->getTempName());
            $result = (new DatabaseBackup())->restoreSql((string) $sql, true);
            return redirect()->to('/backup')->with(
                'success',
                'Database restored successfully (' . $result['statements'] . ' statements executed). A pre-restore safety copy was saved locally.'
            );
        } catch (\Throwable $e) {
            return redirect()->to('/backup')->with('error', $e->getMessage());
        }
    }
}
