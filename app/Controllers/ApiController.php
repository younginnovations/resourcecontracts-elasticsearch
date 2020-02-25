<?php

namespace App\Controllers;

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
     * Shows Home page
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function index()
    {
        return $this->view('home');
    }

    /**
     * Indexes metadata
     *
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
                'published_at'         => $this->request->request->get('published_at'),
            ];

            if ($response = $metadata->index($data)) {
                return $this->json(['result' => $response]);
            }

            return $this->json(['result' => 'failed']);
        } catch (\Exception $e) {
            return $this->json(['result' => $e->getMessage()]);
        }
    }

    /**
     * Indexes Pdf Text
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function pdfText()
    {
        try {
            $pdfText = new PdfTextService();

            if ($response = $pdfText->index($this->request->request->all())) {
                return $this->json(['result' => $response]);
            }

            return $this->json(['result' => 'failed']);
        } catch (\Exception $e) {
            return $this->json(['result' => $e->getMessage()]);
        }
    }

    /**
     * Indexes Annotation
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function annotation()
    {
        try {
            $annotations = new AnnotationsService();

            if ($response = $annotations->index($this->request->request->all())) {
                return $this->json(['result' => $response]);
            }

            return $this->json(['result' => 'failed']);
        } catch (\Exception $e) {
            return $this->json(['result' => $e->getMessage()]);
        }
    }

    /**
     * Deletes Contract
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function deleteContract()
    {
        try {
            $contract = new DeleteContractService();

            if ($response = $contract->deleteContract($this->request->request->all())) {
                return $this->json(['result' => $response]);
            }

            return $this->json(['result' => 'failed']);
        } catch (\Exception $e) {
            return $this->json(['result' => $e->getMessage()]);
        }
    }

    /**
     * Deletes contract's annotations
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function deleteContractAnnotation()
    {
        try {
            $contract   = new DeleteContractService();
            $contractId = $this->request->request->all();

            if ($response = $contract->deleteAnnotations($contractId['contract_id'])) {
                return $this->json(['result' => $response]);
            }

            return $this->json(['result' => 'failed']);
        } catch (\Exception $e) {
            return $this->json(['result' => $e->getMessage()]);
        }
    }

    /**
     * Deletes contract's document from metadata and master
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function deleteContractMetadata()
    {
        try {
            $contract   = new DeleteContractService();
            $contractId = $this->request->request->all();
            $response   = ($contract->deleteMetadata($contractId['contract_id'])
                && $contract->deleteMaster($contractId['contract_id']));
            if ($response) {
                return $this->json(['result' => $response]);
            }

            return $this->json(['result' => 'failed']);
        } catch (\Exception $e) {
            return $this->json(['result' => $e->getMessage()]);
        }
    }

    /**
     * Deletes text document from pdf_text
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function deleteContractText()
    {
        try {
            $contract   = new DeleteContractService();
            $contractId = $this->request->request->all();

            if ($response = $contract->deletePdfText($contractId['contract_id'])) {
                return $this->json(['result' => $response]);
            }

            return $this->json(['result' => 'failed']);
        } catch (\Exception $e) {
            return $this->json(['result' => $e->getMessage()]);
        }
    }

    /**
     * Updates published_at in elastic search index
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function updatePublishedAtIndex()
    {
        try {
            $params           = $this->request->request->all();
            $recent_contracts = $params['recent_contracts'];

            $this->appendLog('published_at_bk.json', $recent_contracts);

            $recent_contracts     = json_decode($recent_contracts);
            $metadata             = new MetadataService();
            $updated_published_at = $metadata->updatePublishedAt($recent_contracts);

            $this->appendLog('updated_published_at.json', $updated_published_at);
            
            return $this->json(['result' => 'Update executed']);
        } catch (\Exception $e) {
            file_put_contents('published_at_error.log', $e->getMessage());
            return $this->json(['result' => $e->getMessage()]);
        }
    }

    /**
     * Append log file with timestamp in a json file
     *
     * @return void
     */
    private function appendLog($fileName, $newLog)
    {
        $log = file_get_contents($fileName);
        if ($log) {
            $log = json_decode($log, true);
        } else {
            $log = [];
        }

        $log[date('Y-m-d H:i:s')] = $newLog;

        file_put_contents($fileName, json_encode($log));
    }
}
