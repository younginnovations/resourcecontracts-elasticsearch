<?php namespace App\Services;

/**
 * Class MetadataService
 * @package App\Services
 */
class MetadataService extends Service
{
    /**
     *  ES Type
     * @var string
     */
    protected $type = 'metadata';

    public function __construct()
    {
        parent::__construct();
        $this->checkIndex();

    }

    /**
     * Create document
     * @param array $metaData
     * @return array
     */
    public function index($metaData)
    {
        try {
            $params        = $this->getIndexType();
            $params['id']  = $metaData['id'];
            $document      = $this->es->exists($params);
            $metadata      = json_decode($metaData['metadata']);
            $metadatatrans = json_decode($metaData['metadata_trans']);
            $createdBy     = json_decode($metaData['created_by']);
            $updatedBy     = json_decode($metaData['updated_by']);
            $data          = [
                'contract_id'          => $metaData['id'],
                'en'                   => $metadata->en,
                'fr'                   => $metadata->fr,
                'ar'                   => $metadata->ar,
                'updated_user_name'    => $updatedBy->name,
                'total_pages'          => $metaData['total_pages'],
                'updated_user_email'   => $updatedBy->email,
                'created_user_name'    => $createdBy->name,
                'created_user_email'   => $createdBy->email,
                'supporting_contracts' => $metaData['supporting_contracts'],
                'created_at'           => date('Y-m-d', strtotime($metaData['created_at'])) . 'T' . date(
                        'H:i:s',
                        strtotime($metaData['created_at'])
                    ),
                'updated_at'           => date('Y-m-d', strtotime($metaData['updated_at'])) . 'T' . date(
                        'H:i:s',
                        strtotime($metaData['updated_at'])
                    ),
            ];
            if ($document) {
                $params['body']['doc'] = $data;

                $response    = $this->es->update($params);
                $uText       = $this->updateTextOCID($params['id'], $metadata->en->open_contracting_id);
                $uAnnotation = $this->updateAnnotationOCID($params['id'], $metadata->en->open_contracting_id);
                $master      = $this->insertIntoMaster($metaData['id'], $metadata);
                logger()->info("Metadata Index updated", array_merge($response, $master, $uText, $uAnnotation));

                return array_merge($response, $master, $uText, $uAnnotation);
            }
            $params['body'] = $data;
            $response       = $this->es->index($params);
            $master         = $this->insertIntoMaster($metaData['id'], $metadata);

            logger()->info("Metadata Index created", $response);

            return array_merge($response, $master);

        } catch (\Exception $e) {
            logger()->error("Metadata Index Error", [$e->getMessage()]);

            return [$e->getMessage()];

        }

    }


    public function updateTextOCID($id, $ocid)
    {
        $params['index'] = $this->index;
        $params['type']  = "pdf_text";
        $params['body']  = ['size' => 10000, 'query' => ["term" => ["contract_id" => ["value" => $id]]]];
        $results         = $this->es->search($params);
        $results         = $results['hits']['hits'];
        $response        = [];
        foreach ($results as $result) {
            $uParam['index']       = $this->index;
            $uParam['type']        = 'pdf_text';
            $uParam['id']          = $result['_id'];
            $uParam['body']['doc'] = ['open_contracting_id' => $ocid];
            $res                   = $this->es->update($uParam);
            array_push($response, $res);
        }

        return $response;

    }


    public function updateAnnotationOCID($id, $ocid)
    {
        $params['index'] = $this->index;
        $params['type']  = "annotations";
        $params['body']  = ['size' => 10000, 'query' => ["term" => ["contract_id" => ["value" => $id]]]];
        $results         = $this->es->search($params);
        $results         = $results['hits']['hits'];
        $response        = [];
        foreach ($results as $result) {
            $uParam['index']       = $this->index;
            $uParam['type']        = 'annotations';
            $uParam['id']          = $result['_id'];
            $uParam['body']['doc'] = ['open_contracting_id' => $ocid];
            $res                   = $this->es->update($uParam);
            array_push($response, $res);
        }

        return $response;

    }

    /**
     * Delete document
     * @param $id
     * @return array
     */
    public function delete($id)
    {
        $params       = $this->getIndexType();
        $params['id'] = $id;

        return $this->es->delete($params);
    }

    /**
     * Create document
     * @param array response
     * @return array
     */
    public function insertIntoMaster($contracId, $metadata)
    {

        try {
            $response        = [];
            $params['index'] = $this->index;
            $params['type']  = "master";
            $params['id']    = $contracId;
            $document        = $this->es->exists($params);
            $body            = [
                "en"                   => $this->filterMetadata($metadata->en),
                "fr"                   => $this->filterMetadata($metadata->fr),
                "ar"                   => $this->filterMetadata($metadata->ar),
                "metadata_string"      => [
                    "en" => $this->getMetadataString($this->removeURL($metadata->en)),
                    "fr" => $this->getMetadataString($this->removeURL($metadata->fr)),
                    "ar" => $this->getMetadataString($this->removeURL($metadata->ar))
                ],
                "pdf_text_string"      => [],
                "annotations_category" => [],
                "annotations_string"   => []
            ];

            if ($document) {
                $params['body']['doc'] = [
                    "en"              => $this->filterMetadata($metadata->en),
                    "fr"              => $this->filterMetadata($metadata->fr),
                    "ar"              => $this->filterMetadata($metadata->ar),
                    "metadata_string" => [
                        "en" => $this->getMetadataString($this->removeURL($metadata->en)),
                        "fr" => $this->getMetadataString($this->removeURL($metadata->fr)),
                        "ar" => $this->getMetadataString($this->removeURL($metadata->ar))
                    ],
                ];

                $response = $this->es->update($params);

                //-logger()->info("Master Index updated",$response);
                return $response;
            }
            $params['body'] = $body;

            $response = $this->es->index($params);

            // logger()->info("Master Index created",$response);
            return $response;
        } catch (\Exception $e) {
            logger()->error("Error while indexing Metadata in master", [$e->getMessage()]);

            return [$e->getMessage()];
        }
    }

    public function filterMetadata($metadata)
    {
        logger()->info('sdfsdf', (array) $metadata);

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

    private function getMetadataString($metadata, $data = '')
    {

        foreach ($metadata as $value) {
            if (is_array($value) || is_object($value)) {
                $data = $this->getMetadataString($value, $data);
            } else {
                if ($value != '') {
                    $data .= ' ' . $value;
                }
            }
        }

        return trim($data);
    }

    /**
     * Remove url from metadata
     * @param $metadata
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
                $i ++;
            }
            logger()->info("URL removed metadata", (array) $metadatas);

            return $metadatas;
        } catch (Exception $e) {
            logger()->error("Error URL removed metadata", [$e->getMessage()]);

            return [];
        }


    }

    /**
     * Check if Index exist or not and update the metadata mapping
     */
    public function checkIndex()
    {
        $condition = $this->es->indices()->exists(['index' => $this->index]);
        if (!$condition) {
            $this->es->indices()->create(['index' => $this->index]);
            $metadata    = $this->createMetadataMapping();
            $master      = $this->createMasterMapping();
            $annotations = $this->createAnnotationsMapping();
            $pdftext     = $this->createPdfTextMapping();
            logger()->info("master mapping");
            return true;
        }

        return true;
    }

    /**
     * Put Mapping of Metadata
     * @return bool
     */
    public function createMetadataMapping()
    {
        try {
            $params['index'] = $this->index;
            $this->es->indices()->refresh($params);
            $params['type'] = $this->type;
            $mapping        = [
                'properties' => [

                    'contract_id'          =>
                        [
                            'type' => 'integer',
                        ],
                    "en"                   => [
                        "properties" => $this->metadataMapping()
                    ],
                    "ar"                   => [
                        "properties" => $this->metadataMapping()
                    ],
                    "fr"                   => [
                        "properties" => $this->metadataMapping()
                    ],
                    'file_size'            =>
                        [
                            'type' => 'long',
                        ],
                    'amla_url'             =>
                        [
                            'type' => 'string',
                        ],
                    'file_url'             =>
                        [
                            'type' => 'string',
                        ],
                    'word_file'            =>
                        [
                            'type' => 'string',
                        ],
                    'updated_user_name'    =>
                        [
                            'type' => 'string',
                        ],
                    'total_pages'          =>
                        [
                            'type' => 'integer',
                        ],
                    'updated_user_email'   =>
                        [
                            'type' => 'string',
                        ],
                    'created_user_name'    =>
                        [
                            'type' => 'string',
                        ],
                    'created_user_email'   =>
                        [
                            'type' => 'string',
                        ],
                    'supporting_contracts' =>
                        [
                            'properties' =>
                                [
                                    'id'            =>
                                        [
                                            'type' => 'integer',
                                        ],
                                    'contract_name' =>
                                        [
                                            'type' => 'string',
                                        ],
                                ],
                        ],
                    'created_at'           =>
                        [
                            'type'   => 'date',
                            'format' => 'dateOptionalTime',
                        ],
                    'updated_at'           =>
                        [
                            'type'   => 'date',
                            'format' => 'dateOptionalTime',
                        ],
                ]

            ];

            $params['body'][$this->type] = $mapping;

            $meadata = $this->es->indices()->putMapping($params);;
            logger()->info("Metadata Mapping done", $meadata);

            return $meadata;
        } catch (\Exception $e) {
            logger()->error("Metadata Mapping Error", (array) $e->getMessage());

            return (array) $e->getMessage();
        }
    }

    /**
     * Create Master Mapping
     */
    private function createMasterMapping()
    {
        try {

            $params['index'] = $this->index;
            $this->es->indices()->refresh($params);
            $params['type'] = "master";
            $mapping        = [
                "properties" => [
                    "en"                   => $this->getMasterMapping(),
                    "fr"                   => $this->getMasterMapping(),
                    "ar"                   => $this->getMasterMapping(),
                    "annotations_category" => [
                        "type"  => "string",
                        "index" => "not_analyzed"
                    ],
                    "metadata_string"      => [
                        "properties"=>[
                            "en"=>[
                                "type" => "string"
                            ],
                            "fr"=>[
                                "type"=>"string"
                            ],
                            "ar"=>[
                                "type"=>"string"
                            ]
                        ]
                    ],
                    "pdf_text_string"      => [
                        "type" => "string"
                    ],
                    "annotations_string"   => [
                        "type" => "string"
                    ]
                ]
            ];

            $params['body']["master"] = $mapping;
            $masterIndex              = $this->es->indices()->putMapping($params);
            logger()->info("Master mapping created", $masterIndex);

            return $masterIndex;
        } catch (\Exception $e) {
            logger()->error("Master Index Erro", [$e->getMessage()]);

            return [$e->getMessage()];
        }
    }

    /**
     * Create Master Mapping
     */
    private function createAnnotationsMapping()
    {
        try {
            $params['index'] = $this->index;
            $this->es->indices()->refresh($params);
            $params['type'] = "annotations";
            $mapping        = [
                "properties" => [
                    "open_contracting_id" => [
                        "type"  => "string",
                        "index" => "not_analyzed"
                    ],
                    'category'            =>
                        [
                            'type'     => 'string',
                            "analyzer" => "english",
                            "fields"   => [
                                "raw" => [
                                    "type"  => "string",
                                    "index" => "not_analyzed"
                                ]
                            ]
                        ],
                ]
            ];

            $params['body']["annotations"] = $mapping;
            $annotationsIndex              = $this->es->indices()->putMapping($params);
            logger()->info("Annotations mapping created", $annotationsIndex);

            return $annotationsIndex;
        } catch (\Exception $e) {
            logger()->error("Annotations Mapping Error", [$e->getMessage()]);

            return [$e->getMessage()];
        }
    }

    /**
     * Create PdfText Mapping
     */
    private function createPdfTextMapping()
    {
        try {
            $params['index'] = $this->index;
            $this->es->indices()->refresh($params);
            $params['type'] = "pdf_text";
            $mapping        = [
                "properties" => [
                    "open_contracting_id" => [
                        "type"  => "string",
                        "index" => "not_analyzed"
                    ],
                ]
            ];

            $params['body']["pdf_text"] = $mapping;
            $pdftext                    = $this->es->indices()->putMapping($params);
            logger()->info("Pdf Text mapping created", $pdftext);

            return $pdftext;
        } catch (\Exception $e) {
            logger()->error("Pdf Text Mapping Error", [$e->getMessage()]);

            return [$e->getMessage()];
        }
    }

    private function metadataMapping()
    {
        return
            [
                'contract_name'        =>
                    [
                        'type'     => 'string',
                        "analyzer" => "english",
                        "fields"   => [
                            "raw" => [
                                "type"  => "string",
                                "index" => "not_analyzed"
                            ]
                        ]
                    ],
                "open_contracting_id"  => [
                    "type"  => "string",
                    "index" => "not_analyzed"
                ],
                'show_pdf_text'        =>
                    [
                        'type' => 'integer'
                    ],
                'contract_identifier'  =>
                    [
                        'type' => 'string',
                    ],
                'language'             =>
                    [
                        'type' => 'string',
                    ],
                'country'              =>
                    [
                        'properties' =>
                            [
                                'code' =>
                                    [
                                        'type' => 'string',
                                    ],
                                'name' =>
                                    [
                                        'type'     => 'string',
                                        "analyzer" => "english",
                                        "fields"   => [
                                            "raw" => [
                                                "type"  => "string",
                                                "index" => "not_analyzed"
                                            ]
                                        ]
                                    ],
                            ],
                    ],
                'resource'             =>
                    [
                        'type'     => 'string',
                        "analyzer" => "english",
                        "fields"   => [
                            "raw" => [
                                "type"  => "string",
                                "index" => "not_analyzed"
                            ]
                        ]
                    ],
                'government_entity'    =>
                    [
                        'properties' =>
                            [
                                'entity'     =>
                                    [
                                        'type' => 'string'
                                    ],
                                'identifier' =>
                                    [
                                        'type' => 'string'
                                    ]
                            ]
                    ],
                'type_of_contract'     =>
                    [
                        'type'     => 'string',
                        "analyzer" => "english",
                        "fields"   => [
                            "raw" => [
                                "type"  => "string",
                                "index" => "not_analyzed"
                            ]
                        ]
                    ],
                'document_type'        =>
                    [
                        'type'     => 'string',
                        "analyzer" => "english",
                        "fields"   => [
                            "raw" => [
                                "type"  => "string",
                                "index" => "not_analyzed"
                            ]
                        ]
                    ],
                'signature_date'       =>
                    [
                        'type'   => 'date',
                        'format' => 'dateOptionalTime',
                    ],
                'signature_year'       =>
                    [
                        'type' => 'string',
                    ],
                'translation_parent'   =>
                    [
                        'type' => 'string',
                    ],
                'company'              =>
                    [
                        'properties' =>
                            [
                                'name'                          =>
                                    [
                                        'type' => 'string',
                                        "fields"   => [
                                            "raw" => [
                                                "type"  => "string",
                                                "index" => "not_analyzed"
                                            ]
                                        ]
                                    ],
                                'participation_share'           =>
                                    [
                                        'type' => 'double',
                                    ],
                                'jurisdiction_of_incorporation' =>
                                    [
                                        'type' => 'string',
                                    ],
                                'registration_agency'           =>
                                    [
                                        'type' => 'string',
                                    ],
                                'company_founding_date'         =>
                                    [
                                        'type'   => 'date',
                                        'format' => 'dateOptionalTime',
                                    ],
                                'company_address'               =>
                                    [
                                        'type' => 'string',
                                    ],
                                'company_number'                =>
                                    [
                                        'type' => 'string',
                                    ],
                                'parent_company'                =>
                                    [
                                        'type' => 'string',
                                    ],
                                'open_corporate_id'             =>
                                    [
                                        'type' => 'string',
                                    ],
                                'operator'                      =>
                                    [
                                        'type' => 'string',
                                    ],
                            ],
                    ],
                'project_title'        =>
                    [
                        'type' => 'string',
                    ],
                'project_identifier'   =>
                    [
                        'type' => 'string',
                    ],
                'concession'           =>
                    [
                        'properties' =>
                            [
                                'license_name'       =>
                                    [
                                        'type' => 'string',
                                    ],
                                'license_identifier' =>
                                    [
                                        'type' => 'string',
                                    ],
                            ],
                    ],
                'source_url'           =>
                    [
                        'type' => 'string',
                    ],
                'disclosure_mode'      =>
                    [
                        'type' => 'string',
                    ],
                'disclosure_mode_text' =>
                    [
                        'type' => 'string',
                    ],
                'date_retrieval'       =>
                    [
                        'type'   => 'date',
                        'format' => 'dateOptionalTime',
                    ],
                'category'             =>
                    [
                        'type' => 'string',
                    ],
                'is_contract_signed'   =>
                    [
                        'type' => 'string'
                    ],
                'translated_from'      =>
                    [
                        'properties' =>
                            [
                                'id'            =>
                                    [
                                        'type' => 'integer',
                                    ],
                                'contract_name' =>
                                    [
                                        'type' => 'string',
                                    ],
                            ],
                    ],
            ];
    }

    public function getMasterMapping()
    {
        return [
            "properties" => [
                "contract_name"       => [
                    "type"     => "string",
                    "analyzer" => "english",
                    "fields"   => [
                        "raw" => [
                            "type"  => "string",
                            "index" => "not_analyzed"
                        ]
                    ]

                ],
                "open_contracting_id" => [
                    "type"  => "string",
                    "index" => "not_analyzed"
                ],
                "country_name"        => [
                    "type"  => "string",
                    "index" => "not_analyzed"
                ],
                "country_code"        => [
                    "type" => "string"
                ],
                "signature_year"      => [
                    "type" => "string"
                ],
                "signature_date"      => [
                    'type'   => 'date',
                    'format' => 'dateOptionalTime',
                ],
                "resource"            => [
                    "type" => "string"
                ],
                "resource_raw"        => [
                    "type"  => "string",
                    'index' => 'not_analyzed'
                ],
                "file_size"           => [
                    "type" => "integer",
                ],
                "language"            => [
                    "type" => "string"
                ],
                "category"            => [
                    "type" => "string"
                ],
                "contract_type"       => [
                    "type"  => "string",
                    "index" => "not_analyzed"
                ],
                'document_type'       =>
                    [
                        'type'     => 'string',
                        "analyzer" => "english",
                        "fields"   => [
                            "raw" => [
                                "type"  => "string",
                                "index" => "not_analyzed"
                            ]
                        ]
                    ],
                "company_name"        => [
                    "type"  => "string",
                    "index" => "not_analyzed"
                ],
                "corporate_grouping"  => [
                    "type"  => "string",
                    "index" => "not_analyzed"
                ]
            ]
        ];
    }

}
