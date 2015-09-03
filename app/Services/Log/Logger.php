<?php namespace App\Services\Log;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger as MonologLogger;

/**
 * Class Logger
 * @package App\Services\Log
 */
class Logger
{

    public $logName = 'RC';
    /**
     * @var string
     */
    protected $filename = 'RC.log';

    /**
     * @var string
     */
    protected $path = 'logs';

    /**
     * @var int
     */
    protected $maxFiles = 5;

    /**
     * The Monolog logger instance.
     *
     * @var MonologLogger
     */
    protected $monolog;

    /**
     * Create a new log writer instance.
     */
    public function __construct()
    {
        $this->monolog = new MonologLogger($this->logName);
        $path          = sprintf('%s/%s/%s', app_path(), $this->path, $this->filename);
        $this->useDailyFiles($path, $this->maxFiles);
    }

    /**
     * write brief description
     * @param        $path
     * @param int    $maxFiles
     */
    public function useDailyFiles($path, $maxFiles = 0)
    {
        $handler = new RotatingFileHandler($path, $maxFiles, MonologLogger::DEBUG);
        $this->monolog->pushHandler($handler);
        $handler->setFormatter($this->getDefaultFormatter());
    }

    /**
     * System is unusable.
     *
     * @param string $message
     * @param array  $context
     * @return null
     */
    public function emergency($message, array $context = [])
    {
        return $this->monolog->emergency($message, $context);
    }

    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     *
     * @param string $message
     * @param array  $context
     * @return null
     */
    public function alert($message, array $context = [])
    {
        return $this->monolog->alert($message, $context);
    }

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param string $message
     * @param array  $context
     * @return null
     */
    public function critical($message, array $context = [])
    {
        return $this->monolog->critical($message, $context);
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param string $message
     * @param array  $context
     * @return null
     */
    public function error($message, array $context = [])
    {
        return $this->monolog->error($message, $context);
    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param string $message
     * @param array  $context
     * @return null
     */
    public function warning($message, array $context = [])
    {
        return $this->monolog->warning($message, $context);
    }

    /**
     * Normal but significant events.
     *
     * @param string $message
     * @param array  $context
     * @return null
     */
    public function notice($message, array $context = [])
    {
        return $this->monolog->notice($message, $context);
    }

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param string $message
     * @param array  $context
     * @return null
     */
    public function info($message, array $context = [])
    {
        return $this->monolog->info($message, $context);
    }

    /**
     * Detailed debug information.
     *
     * @param string $message
     * @param array  $context
     * @return null
     */
    public function debug($message, array $context = [])
    {
        return $this->monolog->debug($message, $context);
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed  $level
     * @param string $message
     * @param array  $context
     * @return null
     */
    public function log($level, $message, array $context = [])
    {
        return $this->monolog->log($message, $context);
    }

    /**
     * Get a defaut Monolog formatter instance.
     *
     * @return \Monolog\Formatter\LineFormatter
     */
    protected function getDefaultFormatter()
    {
        return new LineFormatter(null, null, true, true);
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return app_path() . '/' . $this->path;
    }
}
