<?php namespace App\Controllers;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class BaseController
 * @package App\Controllers
 */
class BaseController
{
    /**
     * @var Response
     */
    protected $response;
    protected $request;

    function __construct()
    {
        $this->request = Request::createFromGlobals();
        $this->response = new Response();
    }

    /**
     * display content
     *
     * @param $page
     * @return Response
     */
    protected function view($page)
    {
        $content = view($page);

        return $this->response->create($content);
    }

    /**
     * display jason data
     *
     * @param $array
     * @return Response
     */
    protected function json($array)
    {
        $content = json_encode($array, JSON_PRETTY_PRINT);

        return $this->response->create($content, 200, ['Content-Type: application/json']);
    }
}