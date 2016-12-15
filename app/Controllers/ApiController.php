<?php namespace App\Controllers;

use App\Services\AnnotationsService;
use App\Services\DeleteContractService;
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
        try {
            $metadata = new MetadataService();

            $data = [
                'id'                   => $this->request->request->get('id'),
                'metadata'             => $this->request->request->get('metadata'),
                'metadata_trans'       => $this->request->request->get('metadata_trans'),
                'created_by'           => $this->request->request->get('created_by'),
                'updated_by'           => $this->request->request->get('updated_by'),
                'created_at'           => $this->request->request->get('created_at'),
                'updated_at'           => $this->request->request->get('updated_at'),
                'total_pages'          => $this->request->request->get('total_pages'),
                'supporting_contracts' => $this->request->request->get('supporting_contracts'),
            ];

            if ($response = $metadata->index($data)) {
                return $this->json($response);
            }

            return $this->json(['result' => 'failed']);

        } catch (\Exception $e) {
            return $this->json(['result' => $e->getMessage()]);
        }

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


    /**
     * Delete Contract
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function deleteContract()
    {
        $contract = new DeleteContractService();
        if ($response = $contract->deleteContract($this->request->request->all())) {
            return $this->json($response);
        }

        return $this->json(['failed']);
    }

    /**
     * Delete contract annotations
     */
    public function deleteContractAnnotation()
    {
        $contract   = new DeleteContractService();
        $contractId = $this->request->request->all();
        if ($response = $contract->deleteAnnotations($contractId['contract_id'])) {
            return $this->json($response);
        }

        return $this->json(['failed']);
    }

    /**
     * Delete contract's document from metadata and master
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function deleteContractMetadata()
    {
        $contract   = new DeleteContractService();
        $contractId = $this->request->request->all();
        if ($response = $contract->deleteMetadata($contractId['contract_id']) && $contract->deleteMaster($contractId['contract_id'])) {
            return $this->json($response);
        }

        return $this->json(['failed']);
    }

    /**
     * Delete text document from pdf_text
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function deleteContractText()
    {
        $contract   = new DeleteContractService();
        $contractId = $this->request->request->all();
        if ($response = $contract->deletePdfText($contractId['contract_id'])) {
            return $this->json($response);
        }

        return $this->json(['failed']);
    }

}