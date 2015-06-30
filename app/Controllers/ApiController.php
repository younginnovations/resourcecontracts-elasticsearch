<?php namespace App\Controllers;

use App\Services\AnnotationsService;
use App\Services\MetadataService;
use App\Services\PdfTextService;

/**
 * Class ApiController
 * @package App\Controllers
 */
class ApiController extends BaseController
{
    /**
     * Show Home page
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function index()
    {
        return $this->view('home');
    }

    /**
     * index metadata
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function metadata()
    {
        $metadata = new MetadataService();

        $data = [
            'id'          => $this->request->request->get('id'),
            'metadata'    => $this->request->request->get('metadata'),
            'created_by'  => $this->request->request->get('created_by'),
            'updated_by'  => $this->request->request->get('updated_by'),
            'created_at'  => $this->request->request->get('created_at'),
            'updated_at'  => $this->request->request->get('updated_at'),
            'total_pages' => $this->request->request->get('total_pages'),
        ];

        if ($response = $metadata->index($data)) {
            return $this->json($response);
        }

        return $this->json(['result' => 'fail']);
    }

    /**
     * Index Pdf Text
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function pdfText()
    {
        $pdfText = new PdfTextService();

        if ($response = $pdfText->index($this->request->request->all())) {
            return $this->json($response);
        }

        return $this->json(['failed']);
    }


    /**
     * Index Annotation
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function annotation()
    {
        $annotations = new AnnotationsService();

        if ($response = $annotations->index($this->request->request->all())) {
            return $this->json($response);
        }

        return $this->json(['failed']);
    }

}