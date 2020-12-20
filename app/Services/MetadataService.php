<?php namespace App\Services;

use Exception;

/**
 * Class MetadataService
 * @package App\Services
 */
class MetadataService extends Service
{
    /**
     * MetadataService constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->checkIndices();
    }

    /**
     * Creates document
     *
     * @param array $metaData
     *
     * @return array
     */
    public function index($metaData)
    {
        try {
            $params                                = [];
            $params['index']                       = $this->getMetadataIndex();
            $params['id']                          = $metaData['id'];
            $document                              = $this->es->exists($params);
            $master_metadata                       = json_decode($metaData['metadata']);
            $createdBy                             = json_decode($metaData['created_by']);
            $updatedBy                             = json_decode($metaData['updated_by']);
            $published_at                          = $metaData['published_at'];
            $published_at                          = (!empty($published_at) && validateDate($published_at)) ? date(
                    'Y-m-d',
                    strtotime
                    (
                        $published_at
                    )
                ).'T'.date(
                    'H:i:s',
                    strtotime($published_at)
                ) : '';
            $master_metadata->published_at         = $published_at;
            $master_metadata->supporting_contracts = $metaData['supporting_contracts'];
            $master_metadata->parent_contract      = $metaData['parent_contract'];
            $data                                  = [
                'contract_id'          => $metaData['id'],
                'en'                   => $master_metadata->en,
                'fr'                   => $master_metadata->fr,
                'ar'                   => $master_metadata->ar,
                'updated_user_name'    => $updatedBy->name,
                'total_pages'          => $metaData['total_pages'],
                'updated_user_email'   => $updatedBy->email,
                'created_user_name'    => $createdBy->name,
                'created_user_email'   => $createdBy->email,
                'supporting_contracts' => $metaData['supporting_contracts'],
                'created_at'           => date('Y-m-d', strtotime($metaData['created_at'])).'T'.date(
                        'H:i:s',
                        strtotime($metaData['created_at'])
                    ),
                'updated_at'           => date('Y-m-d', strtotime($metaData['updated_at'])).'T'.date(
                        'H:i:s',
                        strtotime($metaData['updated_at'])
                    ),
                'published_at'         => $published_at,
            ];
            if ($document) {
                $params['body']['doc'] = $data;

                $response    = $this->es->update($params);
                $uText       = $this->updateTextOCID($params['id'], $master_metadata->en->open_contracting_id);
                $uAnnotation = $this->updateAnnotationOCID($params['id'], $master_metadata->en->open_contracting_id);
                $master      = $this->insertIntoMaster($metaData['id'], $master_metadata);
                logger()->info("Metadata Index updated", array_merge($response, $master, $uText, $uAnnotation));

                return array_merge($response, $master, $uText, $uAnnotation);
            }

            $params['body'] = $data;
            $response       = $this->es->index($params);
            $master         = $this->insertIntoMaster($metaData['id'], $master_metadata);

            logger()->info("Metadata Index created", $response);

            return array_merge($response, $master);
        } catch (Exception $e) {
            logger()->error("Metadata Index Error", [$e->getMessage()]);

            return [$e->getMessage()];
        }
    }

    /**
     * Updates Text OCID
     *
     * @param $id
     * @param $ocid
     *
     * @return array
     */
    public function updateTextOCID($id, $ocid)
    {
        $params['index'] = $this->getPdfTextIndex();
        $params['body']  = ['size' => 10000, 'query' => ["term" => ["contract_id" => ["value" => $id]]]];
        $results         = $this->es->search($params);
        $results         = $results['hits']['hits'];
        $response        = [];

        foreach ($results as $result) {
            $uParam['index']       = $this->getPdfTextIndex();
            $uParam['id']          = $result['_id'];
            $uParam['body']['doc'] = ['open_contracting_id' => $ocid];
            $res                   = $this->es->update($uParam);
            array_push($response, $res);
        }

        return $response;
    }

    /**
     * Updates Annotation OCID
     *
     * @param $id
     * @param $ocid
     *
     * @return array
     */
    public function updateAnnotationOCID($id, $ocid)
    {
        $params['index'] = $this->getAnnotationsIndex();
        $params['body']  = ['size' => 10000, 'query' => ["term" => ["contract_id" => ["value" => $id]]]];
        $results         = $this->es->search($params);
        $results         = $results['hits']['hits'];
        $response        = [];

        foreach ($results as $result) {
            $uParam['index']       = $this->getAnnotationsIndex();
            $uParam['id']          = $result['_id'];
            $uParam['body']['doc'] = ['open_contracting_id' => $ocid];
            $res                   = $this->es->update($uParam);
            array_push($response, $res);
        }

        return $response;
    }

    /**
     * Deletes document
     *
     * @param $id
     *
     * @return array
     */
    public function delete($id)
    {
        $params             = [];
        $params['index']    = $this->getMetadataIndex();
        $params['id']       = $id;

        return $this->es->delete($params);
    }

    /**
     * Creates document
     *
     * @param $contractId
     * @param $metadata
     *
     * @return array
     */
    public function insertIntoMaster($contractId, $metadata)
    {
        try {
            $params['index'] = $this->getMasterIndex();
            $params['id']    = $contractId;
            $document        = $this->es->exists($params);
            $body            = [
                "en"                     => $this->filterMetadata($metadata->en),
                "fr"                     => $this->filterMetadata($metadata->fr),
                "ar"                     => $this->filterMetadata($metadata->ar),
                "metadata_string"        => [
                    "en" => $this->getMetadataString($this->removeURL($metadata->en)),
                    "fr" => $this->getMetadataString($this->removeURL($metadata->fr)),
                    "ar" => $this->getMetadataString($this->removeURL($metadata->ar)),
                ],
                "pdf_text_string"        => [],
                "annotations_category"   => [],
                "annotations_string"     => [],
                "published_at"           => $metadata->published_at,
                "is_supporting_document" => (!empty($metadata->parent_contract) && empty($metadata->supporting_contracts)) ? "1" : "0",
                "supporting_contracts"   => $metadata->supporting_contracts,
                "parent_contract"        => $metadata->parent_contract,
            ];

            if ($document) {
                $params['body']['doc'] = [
                    "en"                     => $this->filterMetadata($metadata->en),
                    "fr"                     => $this->filterMetadata($metadata->fr),
                    "ar"                     => $this->filterMetadata($metadata->ar),
                    "metadata_string"        => [
                        "en" => $this->getMetadataString($this->removeURL($metadata->en)),
                        "fr" => $this->getMetadataString($this->removeURL($metadata->fr)),
                        "ar" => $this->getMetadataString($this->removeURL($metadata->ar)),
                    ],
                    "published_at"           => $metadata->published_at,
                    "is_supporting_document" => (!empty($metadata->parent_contract) && empty($metadata->supporting_contracts)) ? "1" : "0",
                    "supporting_contracts"   => $metadata->supporting_contracts,
                    "parent_contract"        => $metadata->parent_contract,
                ];


                return $this->es->update($params);
            }
            $params['body'] = $body;

            return $this->es->index($params);
        } catch (Exception $e) {
            logger()->error("Error while indexing Metadata in master", [$e->getMessage()]);

            return [$e->getMessage()];
        }
    }

    /**
     * Filters metadata
     *
     * @param $metadata
     *
     * @return array
     */
    public function filterMetadata($metadata)
    {
        $data                        = [];
        $data['contract_name']       = $metadata->contract_name;
        $data['open_contracting_id'] = $metadata->open_contracting_id;
        $data['country_name']        = $metadata->country->name;
        $data['country_code']        = $metadata->country->code;
        $data['signature_year']      = $metadata->signature_year;
        $data['signature_date']      = $metadata->signature_date;
        $data['resource']            = $metadata->resource;
        $data['file_size']           = $metadata->file_size;
        $data['language']            = $metadata->language;
        $data['category']            = $metadata->category;
        $data['contract_type']       = $metadata->type_of_contract;
        $data['document_type']       = $metadata->document_type;
        $data['resource_raw']        = $data['resource'];
        $data['show_pdf_text']       = $metadata->show_pdf_text;
        $data['company_name']        = [];
        $data['corporate_grouping']  = [];

        foreach ($metadata->company as $company) {
            if ($company->name != "") {
                array_push($data['company_name'], $company->name);
            }
            if ($company->parent_company != "") {
                array_push($data['corporate_grouping'], $company->parent_company);
            }
        }

        return $data;
    }

    /**
     * Removes url from metadata
     *
     * @param $metadata
     *
     * @return array $metadata
     */
    public function removeURL($metadata)
    {
        $metadatas = $metadata;

        try {
            unset($metadatas->source_url, $metadatas->amla_url, $metadatas->file_url, $metadatas->word_file);
            $i = 0;

            foreach ($metadatas->company as $company) {
                unset($metadatas->company[$i]->open_corporate_id);
                $i++;
            }
            logger()->info("URL removed metadata", (array) $metadatas);

            return $metadatas;
        } catch (Exception $e) {
            logger()->error("Error URL removed metadata", [$e->getMessage()]);

            return [];
        }
    }

    /**
     * Checks if indices exist. If not, creates them and updates the metadata mapping.
     */
    public function checkIndices()
    {
        $condition = $this->es->indices()->exists(['index' => $this->getMasterIndex()]);
        if (!$condition) {
            logger()->info("Creating master index.");
            $this->es->indices()->create(['index' => $this->getMasterIndex()]);
            $this->createMasterMapping();
            logger()->info("Created master index.");
        }

        $condition = $this->es->indices()->exists(['index' => $this->getMetadataIndex()]);
        if (!$condition) {
            logger()->info("Creating metadata index.");
            $this->es->indices()->create(['index' => $this->getMetadataIndex()]);
            $this->createMetadataMapping();
            logger()->info("Created metadata index.");
        }

        $condition = $this->es->indices()->exists(['index' => $this->getAnnotationsIndex()]);
        if (!$condition) {
            logger()->info("Creating annotations index.");
            $this->es->indices()->create(['index' => $this->getAnnotationsIndex()]);
            $this->createAnnotationsMapping();
            logger()->info("Created annotations index.");
        }

        $condition = $this->es->indices()->exists(['index' => $this->getPdfTextIndex()]);
        if (!$condition) {
            logger()->info("Creating pdf text index.");
            $this->es->indices()->create(['index' => $this->getPdfTextIndex()]);
            $this->createPdfTextMapping();
            logger()->info("Created pdf text index.");
        }

        return true;
    }

    /**
     * Puts Mapping of Metadata
     * @return bool
     */
    public function createMetadataMapping()
    {
        try {
            $params['index'] = $this->getMetadataIndex();
            $this->es->indices()->refresh($params);
            $mapping = $this->getMetadataMapping();

            $params['body'] = $mapping;
            $metadata = $this->es->indices()->putMapping($params);
            logger()->info("Metadata Mapping done", $metadata);

            return 1;
        } catch (Exception $e) {
            logger()->error("Metadata Mapping Error", (array) $e->getMessage());

            return 0;
        }
    }

    /**
     * Returns Metadata Mapping according to language
     *
     * @return array
     */
    public function getMetadataLangMapping()
    {
        return [
            'properties' =>
                [
                    'amla_url'                   =>
                        [
                            'type' => 'text',
                        ],
                    'annexes_missing'            =>
                        [
                            'type' => 'text',
                        ],
                    'category'                   =>
                        [
                            'type' => 'text',
                            'fields' => [
                                'keyword' => [
                                    'type' => 'keyword',
                                    'ignore_above' => 256
                                ]
                            ],
                        ],
                    'ckan'                       =>
                        [
                            'type' => 'text',
                        ],
                    'company'                    =>
                        [
                            'properties' =>
                                [
                                    'company_address'               =>
                                        [
                                            'type' => 'text',
                                        ],
                                    'company_founding_date'         =>
                                        [
                                            'type'   => 'date',
                                            'format' => 'dateOptionalTime',
                                        ],
                                    'company_number'                =>
                                        [
                                            'type' => 'text',
                                        ],
                                    'jurisdiction_of_incorporation' =>
                                        [
                                            'type' => 'text',
                                        ],
                                    'name'                          =>
                                        [
                                            'type'   => 'text',
                                            'fields' =>
                                                [
                                                    'keyword' =>
                                                        [
                                                            'type' => 'keyword',
                                                        ],
                                                ],
                                        ],
                                    'open_corporate_id'             =>
                                        [
                                            'type' => 'text',
                                        ],
                                    'operator'                      =>
                                        [
                                            'type' => 'text',
                                        ],
                                    'parent_company'                =>
                                        [
                                            'type' => 'text',
                                        ],
                                    'participation_share'           =>
                                        [
                                            'type' => 'double',
                                        ],
                                    'registration_agency'           =>
                                        [
                                            'type' => 'text',
                                        ],
                                ],
                        ],
                    'concession'                 =>
                        [
                            'properties' =>
                                [
                                    'license_identifier' =>
                                        [
                                            'type' => 'text',
                                        ],
                                    'license_name'       =>
                                        [
                                            'type' => 'text',
                                        ],
                                ],
                        ],
                    'contract_identifier'        =>
                        [
                            'type' => 'text',
                        ],
                    'contract_name'              =>
                        [
                            'type'     => 'text',
                            'fields'   =>
                                [
                                    'keyword' =>
                                        [
                                            'type' => 'keyword',
                                        ],
                                ],
                            'analyzer' => 'english',
                        ],
                    'contract_note'              =>
                        [
                            'type' => 'text',
                        ],
                    'country'                    =>
                        [
                            'properties' =>
                                [
                                    'code' =>
                                        [
                                            'type'   => 'text',
                                            'fields' =>
                                                [
                                                    'keyword' =>
                                                        [
                                                            'type' => 'keyword',
                                                        ],
                                                ],
                                        ],
                                    'name' =>
                                        [
                                            'type'     => 'text',
                                            'fields'   =>
                                                [
                                                    'keyword' =>
                                                        [
                                                            'type' => 'keyword',
                                                        ],
                                                ],
                                            'analyzer' => 'english',
                                        ],
                                ],
                        ],
                    'date_retrieval'             =>
                        [
                            'type'   => 'date',
                            'format' => 'dateOptionalTime',
                        ],
                    'deal_no'                    =>
                        [
                            'type' => 'text',
                        ],
                    'deal_number'                =>
                        [
                            'type' => 'text',
                        ],
                    'disclosure_mode'            =>
                        [
                            'type' => 'text',
                        ],
                    'disclosure_mode_text'       =>
                        [
                            'type' => 'text',
                        ],
                    'document_type'              =>
                        [
                            'type'     => 'text',
                            'fields'   =>
                                [
                                    'keyword' =>
                                        [
                                            'type' => 'keyword',
                                        ],
                                ],
                            'analyzer' => 'english',
                        ],
                    'documentcloud_url'          =>
                        [
                            'type' => 'text',
                        ],
                    'file_size'                  =>
                        [
                            'type' => 'integer',
                        ],
                    'file_url'                   =>
                        [
                            'type' => 'text',
                        ],
                    'government_entity'          =>
                        [
                            'properties' =>
                                [
                                    [
                                        'properties' =>
                                            [
                                                'entity'     =>
                                                    [
                                                        'type' => 'text',
                                                    ],
                                                'identifier' =>
                                                    [
                                                        'type' => 'text',
                                                    ],
                                            ],
                                    ],
                                    [
                                        'properties' =>
                                            [
                                                'entity'     =>
                                                    [
                                                        'type' => 'text',
                                                    ],
                                                'identifier' =>
                                                    [
                                                        'type' => 'text',
                                                    ],
                                            ],
                                    ],
                                    [
                                        'properties' =>
                                            [
                                                'entity'     =>
                                                    [
                                                        'type' => 'text',
                                                    ],
                                                'identifier' =>
                                                    [
                                                        'type' => 'text',
                                                    ],
                                            ],
                                    ],
                                    [
                                        'properties' =>
                                            [
                                                'entity'     =>
                                                    [
                                                        'type' => 'text',
                                                    ],
                                                'identifier' =>
                                                    [
                                                        'type' => 'text',
                                                    ],
                                            ],
                                    ],
                                    [
                                        'properties' =>
                                            [
                                                'entity'     =>
                                                    [
                                                        'type' => 'text',
                                                    ],
                                                'identifier' =>
                                                    [
                                                        'type' => 'text',
                                                    ],
                                            ],
                                    ],
                                    'entity'     =>
                                        [
                                            'type' => 'text',
                                        ],
                                    'identifier' =>
                                        [
                                            'type' => 'text',
                                        ],
                                ],
                        ],
                    'government_identifier'      =>
                        [
                            'type' => 'text',
                        ],
                    'is_contract_signed'         =>
                        [
                            'type' => 'text',
                        ],
                    'is_supporting_document'     =>
                        [
                            'type' => 'text',
                        ],
                    'language'                   =>
                        [
                            'type' => 'text',
                            'fields' => [
                                'keyword' => [
                                    'type' => 'keyword',
                                    'ignore_above' => 256
                                ]
                            ],
                        ],
                    'matrix_page'                =>
                        [
                            'type' => 'text',
                        ],
                    'open_contracting_id'        =>
                        [
                            'type' => 'keyword',
                        ],
                    'open_contracting_id_old'    =>
                        [
                            'type' => 'text',
                        ],
                    'pages_missing'              =>
                        [
                            'type' => 'text',
                        ],
                    'parent_open_contracting_id' =>
                        [
                            'type'   => 'text',
                            'fields' =>
                                [
                                    'keyword' =>
                                        [
                                            'type'         => 'keyword',
                                            'ignore_above' => 256,
                                        ],
                                ],
                        ],
                    'project_identifier'         =>
                        [
                            'type' => 'text',
                        ],
                    'project_title'              =>
                        [
                            'type' => 'text',
                        ],
                    'resource'                   =>
                        [
                            'type'     => 'text',
                            'fields'   =>
                                [
                                    'keyword' =>
                                        [
                                            'type' => 'keyword',
                                        ],
                                ],
                            'analyzer' => 'english',
                        ],
                    'show_pdf_text'              =>
                        [
                            'type' => 'integer',
                        ],
                    'signature_date'             =>
                        [
                            'type'   => 'date',
                            'format' => 'dateOptionalTime',
                        ],
                    'signature_year'             =>
                        [
                            'type'   => 'text',
                            'fields' =>
                                [
                                    'keyword' =>
                                        [
                                            'type' => 'keyword',
                                        ],
                                ],
                        ],
                    'source_url'                 =>
                        [
                            'type' => 'text',
                        ],
                    'translated_from'            =>
                        [
                            'properties' =>
                                [
                                    'contract_name' =>
                                        [
                                            'type' => 'text',
                                        ],
                                    'id'            =>
                                        [
                                            'type' => 'integer',
                                        ],
                                ],
                        ],
                    'translation_from_original'  =>
                        [
                            'type' => 'text',
                        ],
                    'translation_parent'         =>
                        [
                            'type' => 'text',
                        ],
                    'type_of_contract'           =>
                        [
                            'type'     => 'text',
                            'fields'   =>
                                [
                                    'keyword' =>
                                        [
                                            'type' => 'keyword',
                                        ],
                                ],
                            'analyzer' => 'english',
                        ],
                    'word_file'                  =>
                        [
                            'type' => 'text',
                        ],
                ],
        ];
    }

    /**
     * Returns Metadata Mapping
     *
     * @return array
     */
    public function getMetadataMapping()
    {
        return [
            '_source' => [
                'enabled' => true
            ],
            'properties' => [
                'amla_url' =>
                    [
                        'type' => 'text',
                    ],
                'ar' => $this->getMetadataLangMapping(),
                'contract_id' =>
                    [
                        'type' => 'integer',
                    ],
                'created_at' =>
                    [
                        'type' => 'date',
                        'format' => 'dateOptionalTime',
                    ],
                'created_user_email' =>
                    [
                        'type' => 'text',
                    ],
                'created_user_name'    =>
                    [
                        'type' => 'text',
                    ],
                'en' => $this->getMetadataLangMapping(),
                'file_size' =>
                    [
                        'type' => 'long',
                    ],
                'file_url' =>
                    [
                        'type' => 'text',
                    ],
                'fr' => $this->getMetadataLangMapping(),
                'published_at' => [
                    'type' => 'date'
                ],
                'supporting_contracts' =>
                    [
                        'properties' =>
                            [
                                'contract_name' =>
                                    [
                                        'type' => 'text',
                                    ],
                                'id' =>
                                    [
                                        'type' => 'integer',
                                    ],
                            ],
                    ],
                'total_pages'          =>
                    [
                        'type' => 'integer',
                    ],
                'updated_at'           =>
                    [
                        'type'   => 'date',
                        'format' => 'dateOptionalTime',
                    ],
                'updated_user_email'   =>
                    [
                        'type' => 'text',
                    ],
                'updated_user_name'    =>
                    [
                        'type' => 'text',
                    ],
                'word_file'            =>
                    [
                        'type' => 'text',
                    ],
            ],
        ];
    }

    /**
     * Gets Master Mapping
     * @return array
     */
    public function getMasterMapping()
    {
        return [
            '_source' => [
                'enabled' => true
            ],
            'properties' =>
                [
                    'annotations_category' =>
                        [
                            'type' => 'text',
                            'fields' =>
                                [
                                    'keyword' =>
                                        [
                                            'type' => 'keyword',
                                            'ignore_above' => 256,
                                        ],
                                ],
                        ],
                    'annotations_string'   =>
                        [
                            'properties' =>
                                [
                                    'ar' =>
                                        [
                                            'type'   => 'text',
                                            'fields' =>
                                                [
                                                    'keyword' =>
                                                        [
                                                            'type'         => 'keyword',
                                                            'ignore_above' => 256,
                                                        ],
                                                ],
                                        ],
                                    'en' =>
                                        [
                                            'type'   => 'text',
                                            'fields' =>
                                                [
                                                    'keyword' =>
                                                        [
                                                            'type'         => 'keyword',
                                                            'ignore_above' => 256,
                                                        ],
                                                ],
                                        ],
                                    'fr' =>
                                        [
                                            'type'   => 'text',
                                            'fields' =>
                                                [
                                                    'keyword' =>
                                                        [
                                                            'type'         => 'keyword',
                                                            'ignore_above' => 256,
                                                        ],
                                                ],
                                        ],
                                ],
                        ],
                    'ar'                   =>
                        [
                            'properties' =>
                                [
                                    'category'            =>
                                        [
                                            'type'   => 'text',
                                            'fields' =>
                                                [
                                                    'keyword' =>
                                                        [
                                                            'type'         => 'keyword',
                                                            'ignore_above' => 256,
                                                        ],
                                                ],
                                        ],
                                    'company_name'        =>
                                        [
                                            'type'   => 'text',
                                            'fields' =>
                                                [
                                                    'keyword' =>
                                                        [
                                                            'type'         => 'keyword',
                                                            'ignore_above' => 256,
                                                        ],
                                                ],
                                        ],
                                    'contract_name'       =>
                                        [
                                            'type'     => 'text',
                                            'fields'   =>
                                                [
                                                    'keyword' =>
                                                        [
                                                            'type'         => 'keyword',
                                                            'ignore_above' => 256,
                                                        ],
                                                ],
                                            'analyzer' => 'english',
                                        ],
                                    'contract_type'       =>
                                        [
                                            'type'   => 'text',
                                            'fields' =>
                                                [
                                                    'keyword' =>
                                                        [
                                                            'type'         => 'keyword',
                                                            'ignore_above' => 256,
                                                        ],
                                                ],
                                        ],
                                    'corporate_grouping'  =>
                                        [
                                            'type'   => 'text',
                                            'fields' =>
                                                [
                                                    'keyword' =>
                                                        [
                                                            'type'         => 'keyword',
                                                            'ignore_above' => 256,
                                                        ],
                                                ],
                                        ],
                                    'country_code'        =>
                                        [
                                            'type'   => 'text',
                                            'fields' =>
                                                [
                                                    'keyword' =>
                                                        [
                                                            'type'         => 'keyword',
                                                            'ignore_above' => 256,
                                                        ],
                                                ],
                                        ],
                                    'country_name'        =>
                                        [
                                            'type'   => 'text',
                                            'fields' =>
                                                [
                                                    'keyword' =>
                                                        [
                                                            'type'         => 'keyword',
                                                            'ignore_above' => 256,
                                                        ],
                                                ],
                                        ],
                                    'document_type'       =>
                                        [
                                            'type'     => 'text',
                                            'fields'   =>
                                                [
                                                    'keyword' =>
                                                        [
                                                            'type'         => 'keyword',
                                                            'ignore_above' => 256,
                                                        ],
                                                ],
                                            'analyzer' => 'english',
                                        ],
                                    'file_size'           =>
                                        [
                                            'type' => 'integer',
                                        ],
                                    'language'            =>
                                        [
                                            'type'   => 'text',
                                            'fields' =>
                                                [
                                                    'keyword' =>
                                                        [
                                                            'type'         => 'keyword',
                                                            'ignore_above' => 256,
                                                        ],
                                                ],
                                        ],
                                    'open_contracting_id' =>
                                        [
                                            'type' => 'keyword',
                                        ],
                                    'resource'            =>
                                        [
                                            'type'     => 'text',
                                            'fields'   =>
                                                [
                                                    'keyword' =>
                                                        [
                                                            'type'         => 'keyword',
                                                            'ignore_above' => 256,
                                                        ],
                                                ],
                                            'analyzer' => 'english',
                                        ],
                                    'resource_raw'        =>
                                        [
                                            'type'   => 'text',
                                            'fields' =>
                                                [
                                                    'keyword' =>
                                                        [
                                                            'type'         => 'keyword',
                                                            'ignore_above' => 256,
                                                        ],
                                                ],
                                        ],
                                    'show_pdf_text'       =>
                                        [
                                            'type' => 'integer',
                                        ],
                                    'signature_date'      =>
                                        [
                                            'type'   => 'date',
                                            'format' => 'dateOptionalTime',
                                        ],
                                    'signature_year'      =>
                                        [
                                            'type'   => 'text',
                                            'fields' =>
                                                [
                                                    'keyword' =>
                                                        [
                                                            'type'         => 'keyword',
                                                            'ignore_above' => 256,
                                                        ],
                                                ],
                                        ],
                                ],
                        ],
                    'en'                   =>
                        [
                            'properties' =>
                                [
                                    'category'            =>
                                        [
                                            'type'   => 'text',
                                            'fields' =>
                                                [
                                                    'keyword' =>
                                                        [
                                                            'type'         => 'keyword',
                                                            'ignore_above' => 256,
                                                        ],
                                                ],
                                        ],
                                    'company_name'        =>
                                        [
                                            'type'   => 'text',
                                            'fields' =>
                                                [
                                                    'keyword' =>
                                                        [
                                                            'type'         => 'keyword',
                                                            'ignore_above' => 256,
                                                        ],
                                                ],
                                        ],
                                    'contract_name'       =>
                                        [
                                            'type'     => 'text',
                                            'fields'   =>
                                                [
                                                    'keyword' =>
                                                        [
                                                            'type'         => 'keyword',
                                                            'ignore_above' => 256,
                                                        ],
                                                ],
                                            'analyzer' => 'english',
                                        ],
                                    'contract_type'       =>
                                        [
                                            'type'   => 'text',
                                            'fields' =>
                                                [
                                                    'keyword' =>
                                                        [
                                                            'type'         => 'keyword',
                                                            'ignore_above' => 256,
                                                        ],
                                                ],
                                        ],
                                    'corporate_grouping'  =>
                                        [
                                            'type'   => 'text',
                                            'fields' =>
                                                [
                                                    'keyword' =>
                                                        [
                                                            'type'         => 'keyword',
                                                            'ignore_above' => 256,
                                                        ],
                                                ],
                                        ],
                                    'country_code'        =>
                                        [
                                            'type'   => 'text',
                                            'fields' =>
                                                [
                                                    'keyword' =>
                                                        [
                                                            'type'         => 'keyword',
                                                            'ignore_above' => 256,
                                                        ],
                                                ],
                                        ],
                                    'country_name'        =>
                                        [
                                            'type'   => 'text',
                                            'fields' =>
                                                [
                                                    'keyword' =>
                                                        [
                                                            'type'         => 'keyword',
                                                            'ignore_above' => 256,
                                                        ],
                                                ],
                                        ],
                                    'document_type'       =>
                                        [
                                            'type'     => 'text',
                                            'fields'   =>
                                                [
                                                    'keyword' =>
                                                        [
                                                            'type'         => 'keyword',
                                                            'ignore_above' => 256,
                                                        ],
                                                ],
                                            'analyzer' => 'english',
                                        ],
                                    'file_size'           =>
                                        [
                                            'type' => 'integer',
                                        ],
                                    'language'            =>
                                        [
                                            'type'   => 'text',
                                            'fields' =>
                                                [
                                                    'keyword' =>
                                                        [
                                                            'type'         => 'keyword',
                                                            'ignore_above' => 256,
                                                        ],
                                                ],
                                        ],
                                    'open_contracting_id' =>
                                        [
                                            'type' => 'keyword',
                                        ],
                                    'resource'            =>
                                        [
                                            'type'     => 'text',
                                            'fields'   =>
                                                [
                                                    'keyword' =>
                                                        [
                                                            'type'         => 'keyword',
                                                            'ignore_above' => 256,
                                                        ],
                                                ],
                                            'analyzer' => 'english',
                                        ],
                                    'resource_raw'        =>
                                        [
                                            'type'   => 'text',
                                            'fields' =>
                                                [
                                                    'keyword' =>
                                                        [
                                                            'type'         => 'keyword',
                                                            'ignore_above' => 256,
                                                        ],
                                                ],
                                        ],
                                    'show_pdf_text'       =>
                                        [
                                            'type' => 'integer',
                                        ],
                                    'signature_date'      =>
                                        [
                                            'type'   => 'date',
                                            'format' => 'dateOptionalTime',
                                        ],
                                    'signature_year'      =>
                                        [
                                            'type'   => 'text',
                                            'fields' =>
                                                [
                                                    'keyword' =>
                                                        [
                                                            'type'         => 'keyword',
                                                            'ignore_above' => 256,
                                                        ],
                                                ],
                                        ],
                                ],
                        ],
                    'fr'                   =>
                        [
                            'properties' =>
                                [
                                    'category'            =>
                                        [
                                            'type'   => 'text',
                                            'fields' =>
                                                [
                                                    'keyword' =>
                                                        [
                                                            'type'         => 'keyword',
                                                            'ignore_above' => 256,
                                                        ],
                                                ],
                                        ],
                                    'company_name'        =>
                                        [
                                            'type'   => 'text',
                                            'fields' =>
                                                [
                                                    'keyword' =>
                                                        [
                                                            'type'         => 'keyword',
                                                            'ignore_above' => 256,
                                                        ],
                                                ],
                                        ],
                                    'contract_name'       =>
                                        [
                                            'type'     => 'text',
                                            'fields'   =>
                                                [
                                                    'keyword' =>
                                                        [
                                                            'type'         => 'keyword',
                                                            'ignore_above' => 256,
                                                        ],
                                                ],
                                            'analyzer' => 'english',
                                        ],
                                    'contract_type'       =>
                                        [
                                            'type'   => 'text',
                                            'fields' =>
                                                [
                                                    'keyword' =>
                                                        [
                                                            'type'         => 'keyword',
                                                            'ignore_above' => 256,
                                                        ],
                                                ],
                                        ],
                                    'corporate_grouping'  =>
                                        [
                                            'type'   => 'text',
                                            'fields' =>
                                                [
                                                    'keyword' =>
                                                        [
                                                            'type'         => 'keyword',
                                                            'ignore_above' => 256,
                                                        ],
                                                ],
                                        ],
                                    'country_code'        =>
                                        [
                                            'type'   => 'text',
                                            'fields' =>
                                                [
                                                    'keyword' =>
                                                        [
                                                            'type'         => 'keyword',
                                                            'ignore_above' => 256,
                                                        ],
                                                ],
                                        ],
                                    'country_name'        =>
                                        [
                                            'type'   => 'text',
                                            'fields' =>
                                                [
                                                    'keyword' =>
                                                        [
                                                            'type'         => 'keyword',
                                                            'ignore_above' => 256,
                                                        ],
                                                ],
                                        ],
                                    'document_type'       =>
                                        [
                                            'type'     => 'text',
                                            'fields'   =>
                                                [
                                                    'keyword' =>
                                                        [
                                                            'type'         => 'keyword',
                                                            'ignore_above' => 256,
                                                        ],
                                                ],
                                            'analyzer' => 'english',
                                        ],
                                    'file_size'           =>
                                        [
                                            'type' => 'integer',
                                        ],
                                    'language'            =>
                                        [
                                            'type'   => 'text',
                                            'fields' =>
                                                [
                                                    'keyword' =>
                                                        [
                                                            'type'         => 'keyword',
                                                            'ignore_above' => 256,
                                                        ],
                                                ],
                                        ],
                                    'open_contracting_id' =>
                                        [
                                            'type' => 'keyword',
                                        ],
                                    'resource'            =>
                                        [
                                            'type'     => 'text',
                                            'fields'   =>
                                                [
                                                    'keyword' =>
                                                        [
                                                            'type'         => 'keyword',
                                                            'ignore_above' => 256,
                                                        ],
                                                ],
                                            'analyzer' => 'english',
                                        ],
                                    'resource_raw'        =>
                                        [
                                            'type'   => 'text',
                                            'fields' =>
                                                [
                                                    'keyword' =>
                                                        [
                                                            'type'         => 'keyword',
                                                            'ignore_above' => 256,
                                                        ],
                                                ],
                                        ],
                                    'show_pdf_text'       =>
                                        [
                                            'type' => 'integer',
                                        ],
                                    'signature_date'      =>
                                        [
                                            'type'   => 'date',
                                            'format' => 'dateOptionalTime',
                                        ],
                                    'signature_year'      =>
                                        [
                                            'type'   => 'text',
                                            'fields' =>
                                                [
                                                    'keyword' =>
                                                        [
                                                            'type' => 'keyword',
                                                            'ignore_above' => 256,
                                                        ],
                                                ],
                                        ],
                                ],
                        ],
                    'is_supporting_document' => [
                        'type' => 'text',
                        'fields' => [
                            'keyword' => [
                                'type' => 'keyword',
                                'ignore_above' => 256
                            ]
                        ]
                    ],
                    'metadata_string' =>
                        [
                            'properties' =>
                                [
                                    'ar' =>
                                        [
                                            'type' => 'text',
                                            'fields' =>
                                                [
                                                    'keyword' =>
                                                        [
                                                            'type' => 'keyword',
                                                            'ignore_above' => 256,
                                                        ],
                                                ],
                                        ],
                                    'en' =>
                                        [
                                            'type'   => 'text',
                                            'fields' =>
                                                [
                                                    'keyword' =>
                                                        [
                                                            'type'         => 'keyword',
                                                            'ignore_above' => 256,
                                                        ],
                                                ],
                                        ],
                                    'fr' =>
                                        [
                                            'type'   => 'text',
                                            'fields' =>
                                                [
                                                    'keyword' =>
                                                        [
                                                            'type' => 'keyword',
                                                            'ignore_above' => 256,
                                                        ],
                                                ],
                                        ],
                                ],
                        ],
                    'parent_contract' => [
                        'properties' => [
                            'id' => [
                                'type' => 'long'
                            ],
                            'open_contracting_id' => [
                                'type' => 'text',
                                'fields' => [
                                    'keyword' => [
                                        'type' => 'keyword',
                                        'ignore_above' => 256
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'pdf_text_string' =>
                        [
                            'type' => 'text',
                            'term_vector' => 'with_positions_offsets',
                            'fields' =>
                                [
                                    'keyword' =>
                                        [
                                            'type' => 'keyword',
                                            'ignore_above' => 256,
                                        ],
                                ],
                        ],
                    'published_at' => [
                        'type' => 'date',
                        'format' => 'dateOptionalTime',
                    ],
                    'supporting_contracts' => [
                        'properties' => [
                            'contract_name' => [
                                'type' => 'text',
                                'fields' => [
                                    'keyword' => [
                                        'type' => 'keyword',
                                        'ignore_above' => 256
                                    ]
                                ]
                            ],
                            'id' => [
                                'type' => 'integer'
                            ],
                            'open_contracting_id' => [
                                'type' => 'text',
                                'fields' => [
                                    'keyword' => [
                                        'type' => 'keyword',
                                        'ignore_above' => 256
                                    ]
                                ]
                            ]
                        ]
                    ],
                ]
        ];
    }

    /**
     * Returns Annotation Mapping
     *
     * @return array
     */
    public function getAnnotationMapping()
    {
        return [
            '_source' => [
                'enabled' => true
            ],
            'properties' =>
                [
                    'annotation_id' =>
                        [
                            'type' => 'long',
                        ],
                    'annotation_text' =>
                        [
                            'properties' =>
                                [
                                    'ar' =>
                                        [
                                            'type' => 'text',
                                            'fields' =>
                                                [
                                                    'keyword' =>
                                                        [
                                                            'type' => 'keyword',
                                                            'ignore_above' => 256,
                                                        ],
                                                ],
                                        ],
                                    'en' =>
                                        [
                                            'type'   => 'text',
                                            'fields' =>
                                                [
                                                    'keyword' =>
                                                        [
                                                            'type'         => 'keyword',
                                                            'ignore_above' => 256,
                                                        ],
                                                ],
                                        ],
                                    'fr' =>
                                        [
                                            'type'   => 'text',
                                            'fields' =>
                                                [
                                                    'keyword' =>
                                                        [
                                                            'type'         => 'keyword',
                                                            'ignore_above' => 256,
                                                        ],
                                                ],
                                        ],
                                ],
                        ],
                    'article_reference'   =>
                        [
                            'properties' =>
                                [
                                    'ar' =>
                                        [
                                            'type'   => 'text',
                                            'fields' =>
                                                [
                                                    'keyword' =>
                                                        [
                                                            'type'         => 'keyword',
                                                            'ignore_above' => 256,
                                                        ],
                                                ],
                                        ],
                                    'en' =>
                                        [
                                            'type'   => 'text',
                                            'fields' =>
                                                [
                                                    'keyword' =>
                                                        [
                                                            'type'         => 'keyword',
                                                            'ignore_above' => 256,
                                                        ],
                                                ],
                                        ],
                                    'fr' =>
                                        [
                                            'type'   => 'text',
                                            'fields' =>
                                                [
                                                    'keyword' =>
                                                        [
                                                            'type'         => 'keyword',
                                                            'ignore_above' => 256,
                                                        ],
                                                ],
                                        ],
                                ],
                        ],
                    'category'            =>
                        [
                            'type'   => 'text',
                            'fields' =>
                                [
                                    'keyword' =>
                                        [
                                            'type'         => 'keyword',
                                            'ignore_above' => 256,
                                        ],
                                ],
                            'analyzer' => 'english'
                        ],
                    'category_key'        =>
                        [
                            'type'   => 'text',
                            'fields' =>
                                [
                                    'keyword' =>
                                        [
                                            'type'         => 'keyword',
                                            'ignore_above' => 256,
                                        ],
                                ],
                        ],
                    'cluster'             =>
                        [
                            'type'   => 'text',
                            'fields' =>
                                [
                                    'keyword' =>
                                        [
                                            'type'         => 'keyword',
                                            'ignore_above' => 256,
                                        ],
                                ],
                        ],
                    'contract'            =>
                        [
                            'type'   => 'text',
                            'fields' =>
                                [
                                    'keyword' =>
                                        [
                                            'type'         => 'keyword',
                                            'ignore_above' => 256,
                                        ],
                                ],
                        ],
                    'contract_id'         =>
                        [
                            'type' => 'integer',
                        ],
                    'document_page_no'    =>
                        [
                            'type' => 'long',
                        ],
                    'id'                  =>
                        [
                            'type' => 'long',
                        ],
                    'open_contracting_id' =>
                        [
                            'type'   => 'text',
                            'fields' =>
                                [
                                    'keyword' =>
                                        [
                                            'type'         => 'keyword',
                                            'ignore_above' => 256,
                                        ],
                                ],
                        ],
                    'page'                =>
                        [
                            'type' => 'long',
                        ],
                    'page_id'             =>
                        [
                            'type' => 'long',
                        ],
                    'position'            =>
                        [
                            'type'   => 'text',
                            'fields' =>
                                [
                                    'keyword' =>
                                        [
                                            'type'         => 'keyword',
                                            'ignore_above' => 256,
                                        ],
                                ],
                        ],
                    'quote'               =>
                        [
                            'type'   => 'text',
                            'fields' =>
                                [
                                    'keyword' =>
                                        [
                                            'type'         => 'keyword',
                                            'ignore_above' => 256,
                                        ],
                                ],
                        ],
                    'ranges'              =>
                        [
                            'properties' =>
                                [
                                    'end'         =>
                                        [
                                            'type'   => 'text',
                                            'fields' =>
                                                [
                                                    'keyword' =>
                                                        [
                                                            'type'         => 'keyword',
                                                            'ignore_above' => 256,
                                                        ],
                                                ],
                                        ],
                                    'endOffset'   =>
                                        [
                                            'type' => 'long',
                                        ],
                                    'start'       =>
                                        [
                                            'type'   => 'text',
                                            'fields' =>
                                                [
                                                    'keyword' =>
                                                        [
                                                            'type'         => 'keyword',
                                                            'ignore_above' => 256,
                                                        ],
                                                ],
                                        ],
                                    'startOffset' =>
                                        [
                                            'type' => 'long',
                                        ],
                                ],
                        ],
                    'shapes'              =>
                        [
                            'properties' =>
                                [
                                    'geometry' =>
                                        [
                                            'properties' =>
                                                [
                                                    'height' =>
                                                        [
                                                            'type' => 'double',
                                                        ],
                                                    'width'  =>
                                                        [
                                                            'type' => 'double',
                                                        ],
                                                    'x'      =>
                                                        [
                                                            'type' => 'double',
                                                        ],
                                                    'y'      =>
                                                        [
                                                            'type' => 'double',
                                                        ],
                                                ],
                                        ],
                                    'type'     =>
                                        [
                                            'type'   => 'text',
                                            'fields' =>
                                                [
                                                    'keyword' =>
                                                        [
                                                            'type'         => 'keyword',
                                                            'ignore_above' => 256,
                                                        ],
                                                ],
                                        ],
                                ],
                        ],
                    'status'              =>
                        [
                            'type'   => 'text',
                            'fields' =>
                                [
                                    'keyword' =>
                                        [
                                            'type'         => 'keyword',
                                            'ignore_above' => 256,
                                        ],
                                ],
                        ],
                    'url'                 =>
                        [
                            'type'   => 'text',
                            'fields' =>
                                [
                                    'keyword' =>
                                        [
                                            'type'         => 'keyword',
                                            'ignore_above' => 256,
                                        ],
                                ],
                        ],
                ],
        ];
    }

    /**
     * Returns PdfText Mapping
     *
     * @return array
     */
    public function getPdfTextMapping()
    {
        return [
            '_source' => [
                'enabled' => true
            ],
            'properties' =>
                [
                    'contract_id' =>
                        [
                            'type' => 'integer',
                        ],
                    'metadata' =>
                        [
                            'properties' =>
                                [
                                    'category' =>
                                        [
                                            'type' => 'text',
                                            'fields' =>
                                                [
                                                    'keyword' =>
                                                        [
                                                            'type' => 'keyword',
                                                            'ignore_above' => 256,
                                                        ],
                                                ],
                                        ],
                                    'contract_name'  =>
                                        [
                                            'type'   => 'text',
                                            'fields' =>
                                                [
                                                    'keyword' =>
                                                        [
                                                            'type'         => 'keyword',
                                                            'ignore_above' => 256,
                                                        ],
                                                ],
                                        ],
                                    'country'        =>
                                        [
                                            'properties' =>
                                                [
                                                    'code' =>
                                                        [
                                                            'type'   => 'text',
                                                            'fields' =>
                                                                [
                                                                    'keyword' =>
                                                                        [
                                                                            'type'         => 'keyword',
                                                                            'ignore_above' => 256,
                                                                        ],
                                                                ],
                                                        ],
                                                    'name' =>
                                                        [
                                                            'type'   => 'text',
                                                            'fields' =>
                                                                [
                                                                    'keyword' =>
                                                                        [
                                                                            'type'         => 'keyword',
                                                                            'ignore_above' => 256,
                                                                        ],
                                                                ],
                                                        ],
                                                ],
                                        ],
                                    'file_size'      =>
                                        [
                                            'type' => 'long',
                                        ],
                                    'file_url'       =>
                                        [
                                            'type'   => 'text',
                                            'fields' =>
                                                [
                                                    'keyword' =>
                                                        [
                                                            'type'         => 'keyword',
                                                            'ignore_above' => 256,
                                                        ],
                                                ],
                                        ],
                                    'resource'       =>
                                        [
                                            'type'   => 'text',
                                            'fields' =>
                                                [
                                                    'keyword' =>
                                                        [
                                                            'type'         => 'keyword',
                                                            'ignore_above' => 256,
                                                        ],
                                                ],
                                        ],
                                    'signature_date' =>
                                        [
                                            'type' => 'date',
                                        ],
                                    'signature_year' =>
                                        [
                                            'type'   => 'text',
                                            'fields' =>
                                                [
                                                    'keyword' =>
                                                        [
                                                            'type'         => 'keyword',
                                                            'ignore_above' => 256,
                                                        ],
                                                ],
                                        ],
                                ],
                        ],
                    'open_contracting_id' =>
                        [
                            'type'   => 'text',
                            'fields' =>
                                [
                                    'keyword' =>
                                        [
                                            'type'         => 'keyword',
                                            'ignore_above' => 256,
                                        ],
                                ],
                        ],
                    'page_no'             =>
                        [
                            'type' => 'long',
                        ],
                    'pdf_url'             =>
                        [
                            'type'   => 'text',
                            'fields' =>
                                [
                                    'keyword' =>
                                        [
                                            'type'         => 'keyword',
                                            'ignore_above' => 256,
                                        ],
                                ],
                        ],
                    'size'                =>
                        [
                            'type' => 'long',
                        ],
                    'text'                =>
                        [
                            'type'   => 'text',
                            'fields' =>
                                [
                                    'keyword' =>
                                        [
                                            'type'         => 'keyword',
                                            'ignore_above' => 256,
                                        ],
                                ],
                        ],
                ],
        ];
    }

    /**
     * Gets Metadata String
     *
     * @param        $metadata
     * @param string $data
     *
     * @return string
     */
    private function getMetadataString($metadata, $data = '')
    {
        foreach ($metadata as $value) {
            if (is_array($value) || is_object($value)) {
                $data = $this->getMetadataString($value, $data);
            } else {
                if ($value != '') {
                    $data .= ' '.$value;
                }
            }
        }

        return trim($data);
    }

    /**
     * Creates Master Mapping
     */
    private function createMasterMapping()
    {
        try {
            $params['index'] = $this->getMasterIndex();
            $this->es->indices()->refresh($params);
            $mapping = $this->getMasterMapping();

            $params['body'] = $mapping;
            $masterIndex = $this->es->indices()->putMapping($params);
            logger()->info("Master mapping created", $masterIndex);

            return $masterIndex;
        } catch (Exception $e) {
            logger()->error("Master Index Error", [$e->getMessage()]);

            return [$e->getMessage()];
        }
    }

    /**
     * Creates Annotations Mapping
     */
    private function createAnnotationsMapping()
    {
        try {
            $params['index'] = $this->getAnnotationsIndex();
            $this->es->indices()->refresh($params);
            $mapping = $this->getAnnotationMapping();

            $params['body'] = $mapping;
            $annotationsIndex = $this->es->indices()->putMapping($params);
            logger()->info("Annotations mapping created", $annotationsIndex);

            return $annotationsIndex;
        } catch (Exception $e) {
            logger()->error("Annotations Mapping Error", [$e->getMessage()]);

            return [$e->getMessage()];
        }
    }

    /**
     * Creates PdfText Mapping
     */
    private function createPdfTextMapping()
    {
        try {
            $params['index'] = $this->getPdfTextIndex();
            $this->es->indices()->refresh($params);
            $mapping = $this->getPdfTextMapping();

            $params['body'] = $mapping;
            $pdfText = $this->es->indices()->putMapping($params);
            logger()->info("Pdf Text mapping created", $pdfText);

            return $pdfText;
        } catch (Exception $e) {
            logger()->error("Pdf Text Mapping Error", [$e->getMessage()]);

            return [$e->getMessage()];
        }
    }

    /**
     * Updates published_at in elastic search index
     *
     * @param $recent_contracts
     *
     * @return array
     */
    public function updatePublishedAt($recent_contracts)
    {
        $response = array();

        foreach ($recent_contracts as $contract_id => $published_at) {
            $published_at                = date('Y-m-d', strtotime($published_at)).'T'.date(
                    'H:i:s',
                    strtotime
                    (
                        $published_at
                    )
                );
            $master_param['index']       = $this->getMasterIndex();
            $master_param['id']          = $contract_id;
            $master_param['body']['doc'] = ['published_at' => $published_at];
            $master_res                  = $this->es->update($master_param);
            array_push($response, $master_res);

            $metadata_param['index']       = $this->getMetadataIndex();
            $metadata_param['id']          = $contract_id;
            $metadata_param['body']['doc'] = ['published_at' => $published_at];
            $metadata_res                  = $this->es->update($metadata_param);
            array_push($response, $metadata_res);
        }

        return $response;

    }

    /**
     * Returns page numbers of master doc
     *
     * @return false|float
     */
    public function getMasterPages()
    {
        $master_param          = [];
        $master_param['index'] = $this->getMasterIndex();
        $results               = $this->es->count($master_param);
        $count                 = (int) $results['count'];
        $page_size             = 1000;

        return ceil($count / $page_size);
    }

    /**
     * Adds is_supporting_document, supporting_contracts and parent_contract keys to master
     *
     * @param $page
     *
     * @return array[]
     */
    public function addMasterDocKey($page)
    {
        try {
            $page_size                                                       = 1000;
            $from                                                            = ($page == 1) ? 0 : (($page - 1) * $page_size);
            $master_ids                                                      = [];
            $res                                                             = [];
            $master_param                                                    = [];
            $master_param['index']                                           = $this->getMasterIndex();
            $master_param['from']                                            = $from;
            $master_param['size']                                            = $page_size;
            $master_param['body']['_source']                                 = ['en.open_contracting_id'];
            $master_param['body']['sort']['en.open_contracting_id']['order'] = 'asc';

            file_put_contents('add_to_master_track.json', 'master-param-pass'.PHP_EOL, FILE_APPEND);
            $results                                                         = $this->es->search($master_param);
            $results                                                         = $results['hits']['hits'];

            file_put_contents('add_to_master_track.json', 'results-pass'.PHP_EOL, FILE_APPEND);

            foreach ($results as $result) {
                $master_ids[]                = $result['_id'];
                $master_param                = [];
                $master_param['index']       = $this->getMasterIndex();
                $master_param['id']          = $result['_id'];
                $master_param['body']['doc'] = [
                    'is_supporting_document' => '0',
                    'supporting_contracts'   => null,
                    'parent_contract'        => null,
                ];
                $res[]                       = $this->es->update($master_param);
            }
            file_put_contents('add_to_master_track.json','update-pass'.PHP_EOL, FILE_APPEND);

            return [
                'count'               => [
                    'master_id_count'           => count($master_ids),
                    'updated_master_docs_count' => count($res),
                ],
                'master_ids'          => $master_ids,
                'updated_master_docs' => $res,
            ];
        } catch (\Exception $e) {
            file_put_contents('master_key_error.log', $e->getMessage());
        }
    }

    /**
     * Updates is_supporting_document, supporting_contracts in master for parent contract
     *
     * @param $parent_contracts
     *
     * @return array
     */
    public function updateParent($parent_contracts)
    {
        $response = array();

        foreach ($parent_contracts as $parent_id => $child_contracts) {
            $master_param          = [];
            $master_param['index'] = $this->getMasterIndex();
            $master_param['id']    = $parent_id;

            if ($this->es->exists($master_param)) {
                $master_param                = [];
                $master_param['index']       = $this->getMasterIndex();
                $master_param['id']          = $parent_id;
                $master_param['body']['doc'] = [
                    'is_supporting_document' => '0',
                    'supporting_contracts'   => $child_contracts,
                ];
                $master_res                  = $this->es->update($master_param);
                array_push($response, $master_res);
            }
        }
        file_put_contents('add_to_master_track.json',"finished parent-child-indexing ".PHP_EOL, FILE_APPEND);

        return $response;
    }

    /**
     * Updates is_supporting_document, parent_contract in master for child contract
     *
     * @param $child_contracts
     *
     * @return array
     */
    public function updateChild($child_contracts)
    {
        $response = array();

        foreach ($child_contracts as $child_id => $parent_contract) {
            $master_param          = [];
            $master_param['index'] = $this->getMasterIndex();
            $master_param['id']    = $child_id;

            if ($this->es->exists($master_param)) {
                $master_param                = [];
                $master_param['index']       = $this->getMasterIndex();
                $master_param['id']          = $child_id;
                $master_param['body']['doc'] = [
                    'is_supporting_document' => '1',
                    'parent_contract'        => $parent_contract,
                ];
                $master_res                  = $this->es->update($master_param);
                array_push($response, $master_res);
            }

        }
        file_put_contents('add_to_master_track.json',"finished child-parent-indexing ".PHP_EOL, FILE_APPEND);

        return $response;
    }
}

