<?php namespace App\Services\Log;

class LogViewer
{
    protected $log;

    function __construct()
    {
        $this->log = new Logger();
    }

    /**
     * Get Log Path
     *
     * @return string
     */
    public function logPath()
    {
        return $this->log->getPath();
    }

    /**
     * Get All log files
     *
     * @return array
     */
    public function getAllLogs()
    {
        $path  = $this->logPath();
        $files = scandir($path, SCANDIR_SORT_DESCENDING);
        $files = array_diff($files, ['..', '.']);

        return $files;
    }

    /**
     * Get complete log file path
     *
     * @param $file
     *
     * @return string
     */
    public function fullPath($file)
    {
        return $this->logPath().'/'.$file;
    }

    /**
     * Get log content
     *
     * @param $file
     *
     * @return string
     */
    public function getLogContent($file)
    {
        $path = $this->fullPath($file);

        return @file_get_contents($path);
    }

    /**
     * Get Log List from a file
     *
     * @param $file
     *
     * @return object
     */
    public function getLogList($file)
    {
        $logs = $this->getLogContent($file);
        $logs = array_filter(explode("\n", $logs));

        foreach ($logs as &$log) {
            $arr   = array_map('trim', explode(']', $log));
            $date  = ltrim($arr[0], '[');
            $array = array_map('trim', explode(':', $arr[1]));
            $type  = $array[0];
            unset($array[0]);
            $message = join(' ', $array);
            $type    = explode($this->log->logName, $type);
            $type    = ltrim(strtolower(end($type)), '.');
            $log     = (object) [
                'date'    => $date,
                'type'    => $this->getTypeSet($type),
                'message' => $message,
            ];
        }

        return $logs;
    }

    public function getTypeSet($log)
    {
        $set = [
            'default' => [
                'class' => $log,
                'text'  => $log,
                'icon'  => $log,
            ],
            'info'    => [
                'class' => $log,
                'text'  => $log,
                'icon'  => $log,
            ],
            'error'   => [
                'class' => 'danger',
                'text'  => $log,
                'icon'  => 'warning',
            ],
        ];

        $set = isset($set[$log]) ? $set[$log] : $set['default'];

        return (object) $set;
    }

}