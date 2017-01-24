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
     * Shows Home page
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function index()
    {
        return $this->view('home');
    }

    /**
     * Indexes metadata
<<<<<<< Updated upstream
=======
     *
>>>>>>> Stashed changes
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
                return $this->json(['result' => $response]);
            }

            return $this->json(['result' => 'failed']);

        } catch (\Exception $e) {
            return $this->json(['result' => $e->getMessage()]);
        }
    }

    /**
     * Indexes Pdf Text
<<<<<<< Updated upstream
=======
     *
>>>>>>> Stashed changes
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
<<<<<<< Updated upstream
=======
     *
>>>>>>> Stashed changes
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
<<<<<<< Updated upstream
=======
     *
>>>>>>> Stashed changes
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
<<<<<<< Updated upstream
     * Deletes contract annotations
=======
     * Deletes contract's annotations
     *
     * @return \Symfony\Component\HttpFoundation\Response
>>>>>>> Stashed changes
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
<<<<<<< Updated upstream
=======
     *
>>>>>>> Stashed changes
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
<<<<<<< Updated upstream
=======
     *
>>>>>>> Stashed changes
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function deleteContractText()
    {
        try {
            $contract   = new DeleteContractService();
            $contractId = $this->request->request->all();

<<<<<<< Updated upstream
        return $this->json(['failed']);
=======
            if ($response = $contract->deletePdfText($contractId['contract_id'])) {
                return $this->json(['result' => $response]);
            }

            return $this->json(['result' => 'failed']);
        } catch (\Exception $e) {
            return $this->json(['result' => $e->getMessage()]);
        }
>>>>>>> Stashed changes
    }
}
