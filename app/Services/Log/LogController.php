<?php namespace App\Services\Log;

use App\Controllers\BaseController;

class LogController extends BaseController
{

    public function index()
    {
        $log   = new LogViewer();
        $files = $log->getAllLogs();
        $logs  = $log->getLogList($this->request->query->get('file', $files[0]));

        return $this->view('logs', compact('files', 'logs'));
    }

}