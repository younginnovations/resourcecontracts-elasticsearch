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
                'supporting_contracts' => json_decode($this->request->request->get('supporting_contracts')),
                'parent_contract'      => json_decode($this->request->request->get('parent_contract')),
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
     * @param $fileName
     * @param $newLog
     *
     * @return void
     */
    private function appendLog($fileName, $newLog)
    {
        $log = @file_get_contents($fileName);
        if ($log) {
            $log = json_decode($log, true);
        } else {
            $log = [];
        }

        $log[date('Y-m-d H:i:s')] = $newLog;

        file_put_contents($fileName, json_encode($log));
    }

    /**
     * Updates annotation category name "Community consultation " to
     * "Community consultation" in elastic search
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function updateAnnotationCategory()
    {
        try {
            $params      = $this->request->request->all();
            $annotations = $params['annotations'];
            $annotations = json_decode($annotations, true);
            $this->appendLog('annotation_category_bk.json', $annotations);

            $annotationData              = new AnnotationsService();
            $updated_annotation_category = $annotationData->updateAnnotationCategory($annotations);

            $this->appendLog('updated_annotation_category.json', $updated_annotation_category);

            return $this->json(['result' => 'Update executed']);
        } catch (\Exception $e) {
            file_put_contents('annotation_category_error.log', $e->getMessage(), FILE_APPEND);

            return $this->json(['result' => $e->getMessage()]);
        }
    }

    /**
     * Updates the annotation cluster
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function updateAnnotationCluster()
    {
        try {
            $annotationData = new AnnotationsService();
            $docs           = $annotationData->getAnnotationDocs('size-of-concession-area', 'Legal Rules');
            $annotations    = $docs['hits']['hits'];
            $updated_docs   = [];

            foreach ($annotations as $annotation) {
                $annotation_service = new AnnotationsService();
                $updated_docs[]     = $annotation_service->updateAnnotationCluster($annotation['_id'], 'General');
            }
            $this->appendLog('annotation_cluster_bk.json', $annotations);
            $this->appendLog('annotation_cluster_updated_bk.json', $updated_docs);

            return $this->json(['result' => 'Update executed']);
        } catch (\Exception $e) {
            file_put_contents('annotation_cluster_error.log', $e, FILE_APPEND);

            $content = json_encode(['result' => $e->getMessage()], JSON_PRETTY_PRINT);

            return $this->response->create($content, 400, ['Content-Type: application/json']);
        }
    }

    /**
     * Restores updated annotation
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function restoreAnnotationCluster()
    {
        try {
            $params  = $this->request->request->all();
            $bk_docs = json_decode(file_get_contents('annotation_cluster_bk.json'), true);
            $docs    = $bk_docs[$params['key']];

            foreach ($docs as $doc) {
                $annotationData = new AnnotationsService();
                $annotationData->updateAnnotationCluster($doc['_id'], $doc['_source']['cluster']);
            }

            return $this->json(['result' => 'Restore executed']);
        } catch (\Exception $e) {
            file_put_contents('annotation_cluster_error.log', $e->getMessage(), FILE_APPEND);

            $content = json_encode(['result' => $e->getMessage()], JSON_PRETTY_PRINT);

            return $this->response->create($content, 400, ['Content-Type: application/json']);
        }
    }

    /**
     * Re indexes the master
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function updateSupportingDocIndex()
    {
        try {
            file_put_contents('add_to_master_track.json',"Indexing called with ES refreshed".PHP_EOL, FILE_APPEND);
            $params   = $this->request->request->all();
            $metadata = new MetadataService();
            file_put_contents('add_to_master_track.json',json_encode($params).PHP_EOL, FILE_APPEND);

            if (array_key_exists('get_master_pages', $params)) {
                $master_count = $metadata->getMasterPages();

                return $this->json(['result' => $master_count, 'status' => true]);
            } elseif (array_key_exists('add_to_master', $params)) {
                $page          = $params['add_to_master'];
                file_put_contents('add_to_master_track.json',"started $page ".PHP_EOL, FILE_APPEND);
                $master_key_bk = $metadata->addMasterDocKey($page);

                $this->appendLog("master_key_bk_$page.json", $master_key_bk);
            } elseif (array_key_exists('parent_child_contracts', $params)) {
                $parent_child_contracts = $params['parent_child_contracts'];

                $this->appendLog('parent_child_contract_bk.json', $parent_child_contracts);

                $parent_child_contracts   = json_decode($parent_child_contracts);
                $updated_parent_contracts = $metadata->updateParent($parent_child_contracts);

                $this->appendLog('updated_parent_child_contract_bk.json', $updated_parent_contracts);
            } elseif (array_key_exists('child_parent_contracts', $params)) {
                $child_parent_contracts = $params['child_parent_contracts'];

                $this->appendLog('child_parent_contract_bk.json', $child_parent_contracts);

                $child_parent_contracts  = json_decode($child_parent_contracts);
                $updated_child_contracts = $metadata->updateChild($child_parent_contracts);

                $this->appendLog('updated_child_parent_contract_bk.json', $updated_child_contracts);
            }

            return $this->json(['result' => 'Update executed', 'status' => true]);
        } catch (\Exception $e) {
            file_put_contents('supporting_doc_error.log', $e->getMessage());

            return $this->json(['result' => $e->getMessage(), 'status' => false]);
        }
    }
}
