<?php

/**
 * Get View content
 *
 * @param $page
 *
 * @return string
 * @throws Exception
 */
function view($page, $data = [])
{
    extract($data);
    $view_path = app_path().'/'.trim(config('view_path'), '/');
    $file      = sprintf('%s/%s.php', $view_path, $page);
    if (!file_exists($file)) {
        throw new Exception($page.' not found.');
    }
    ob_start();
    require($file);

    return ob_get_clean();
}

/**
 * Get config value
 *
 * @param string $key
 * @param null   $default
 *
 * @return string/array/null
 */
function config($key = '', $default = null)
{
    $config = require 'config.php';

    if (array_key_exists($key, $config)) {
        return $config[$key];
    }

    return $default;
}

/**
 * Application Path
 *
 * @return string
 */
function app_path()
{
    return APP_PATH;
}

/**
 * Application Url
 *
 * @param string $uri
 *
 * @return string
 */
function url($uri = '')
{
    $uri = trim($uri);

    return 'http://'.$_SERVER['HTTP_HOST'].'/'.trim($uri, '/');
}

function logger($message = null)
{
    $log = new \App\Services\Log\Logger();

    if (is_null($message)) {
        return $log;
    }

    return $log->info($message);
}
