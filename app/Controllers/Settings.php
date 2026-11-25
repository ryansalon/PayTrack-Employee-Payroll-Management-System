<?php

namespace App\Controllers;

use App\Models\AppSettingModel;

class Settings extends BaseController
{
    public function index()
    {
        if (session()->get('role') !== 'admin') {
            return redirect()->to('/dashboard')->with('error', 'Administrator access is required.');
        }

        $model = new AppSettingModel();
        return view('settings/index', ['settings' => $model->getAllSettings()]);
    }

    public function update()
    {
        if (session()->get('role') !== 'admin') {
            return redirect()->to('/dashboard')->with('error', 'Administrator access is required.');
        }

        $fields = [
            'lgu_name',
            'municipality_label',
            'mayor_name',
            'mayor_title',
            'treasurer_name',
            'treasurer_title',
        ];

        $updates = [];
        foreach ($fields as $key) {
            $val = trim((string) $this->request->getPost($key));
            if ($val === '') {
                return redirect()->back()->withInput()->with('error', 'All LGU and signatory fields are required.');
            }
            $updates[$key] = $val;
        }

        (new AppSettingModel())->setMany($updates);
        return redirect()->to('/settings')->with('success', 'LGU signatory settings updated.');
    }
}
