<?php

class MoySkladAttributesSync
{
    /** @var MoySkladApi */
    protected $ms;

    /** @var string */
    protected $dbName;

    /** @var bool */
    protected $debug = true;

    /** @var resource|null */
    protected $outputStream = null;

    public function __construct(MoySkladApi $ms, $dbName = 'Papir', $debug = true)
    {
        $this->ms = $ms;
        $this->dbName = $dbName;
        $this->debug = (bool)$debug;
        $this->outputStream = defined('STDOUT') ? STDOUT : null;
    }

    /**
     * Синхронизация набора документов.
     *
     * @param array<int, string> $documents
     * @return array<string, mixed>
     */
    public function syncAll(array $documents)
    {
        $result = [
            'ok' => true,
            'documents' => [],
            'errors' => [],
        ];

        $documents = array_values(array_unique(array_filter($documents)));

        $this->debugLine('');
        $this->debugLine('========== START MoySkladAttributesSync::syncAll ==========');
        $this->debugValue('Documents to sync', $documents);

        foreach ($documents as $document) {
            $documentResult = $this->syncDocumentAttributes($document);
            $result['documents'][$document] = $documentResult;

            if (!$documentResult['ok']) {
                $result['ok'] = false;
                $result['errors'][] = [
                    'document' => $document,
                    'error' => isset($documentResult['error']) ? $documentResult['error'] : 'Unknown error',
                ];
            }
        }

        $this->debugValue('Final sync summary', $result);
        $this->debugLine('========== END MoySkladAttributesSync::syncAll ==========');
        $this->debugLine('');

        return $result;
    }

    /**
     * Синхронизация атрибутов одного документа.
     *
     * @param string $document
     * @return array<string, mixed>
     */
    public function syncDocumentAttributes($document)
    {
        $this->debugLine('');
        $this->debugLine('--------------------------------------------------');
        $this->debugLine('SYNC DOCUMENT: ' . $document);
        $this->debugLine('--------------------------------------------------');

        $apiResponse = $this->ms->getMetadataAttributes($document);

        // DEBUG_START: сырой ответ API по атрибутам документа
        $this->debugValue('Raw metadata response for ' . $document, $apiResponse);
        // DEBUG_END

        if ($this->isApiError($apiResponse)) {
            return [
                'ok' => false,
                'document' => $document,
                'error' => $this->extractApiErrorMessage($apiResponse),
            ];
        }

        $rows = $this->extractRows($apiResponse);

        $this->debugLine('Attributes found in API: ' . count($rows));

        $preparedRows = [];
        $customEntityIds = [];

        foreach ($rows as $row) {
            $prepared = $this->prepareAttributeRow($document, $row);
            $preparedRows[] = $prepared;

            if (!empty($prepared['custom_entity_id'])) {
                $customEntityIds[$prepared['custom_entity_id']] = $prepared['custom_entity_id'];
            }
        }

        // DEBUG_START: подготовленные строки для Documents_attr
        $this->debugValue('Prepared attribute rows for DB [' . $document . ']', $preparedRows);
        // DEBUG_END

        $transaction = Database::begin($this->dbName);
        if (!$transaction['ok']) {
            return [
                'ok' => false,
                'document' => $document,
                'error' => 'Failed to begin transaction',
            ];
        }

        try {
            $archiveResult = Database::update(
                $this->dbName,
                'Documents_attr',
                ['is_archived' => 1],
                ['document' => $document]
            );

            // DEBUG_START: результат архивирования старых атрибутов документа
            $this->debugValue('Archive old attributes result [' . $document . ']', $archiveResult);
            // DEBUG_END

            if (!$archiveResult['ok']) {
                throw new Exception('Archive old attributes failed: ' . $archiveResult['error']);
            }

            $upsertResult = [
                'ok' => true,
                'updated' => 0,
                'inserted' => 0,
                'errors' => [],
            ];

            if (!empty($preparedRows)) {
                $upsertResult = Database::upsertRows(
                    $this->dbName,
                    'Documents_attr',
                    $preparedRows,
                    ['document', 'ms_attribute_id']
                );
            }

            // DEBUG_START: результат upsert по атрибутам
            $this->debugValue('Upsert attributes result [' . $document . ']', $upsertResult);
            // DEBUG_END

            if (!$upsertResult['ok'] || !empty($upsertResult['errors'])) {
                $message = !$upsertResult['ok']
                    ? 'Upsert attributes failed'
                    : 'Upsert attributes completed with row errors: ' . implode('; ', $upsertResult['errors']);

                throw new Exception($message);
            }

            $customEntityResults = [];

            foreach ($customEntityIds as $customEntityId) {
                $customResult = $this->syncCustomEntity($document, $customEntityId);
                $customEntityResults[$customEntityId] = $customResult;

                if (!$customResult['ok']) {
                    throw new Exception(
                        'CustomEntity sync failed [' . $customEntityId . ']: ' .
                        (isset($customResult['error']) ? $customResult['error'] : 'Unknown error')
                    );
                }
            }

            $commit = Database::commit($this->dbName);
            if (!$commit['ok']) {
                throw new Exception('Commit failed');
            }

            $result = [
                'ok' => true,
                'document' => $document,
                'attributes_found' => count($preparedRows),
                'custom_entities_found' => count($customEntityIds),
                'inserted' => $upsertResult['inserted'],
                'updated' => $upsertResult['updated'],
                'custom_entities' => $customEntityResults,
            ];

            $this->debugValue('Document sync result [' . $document . ']', $result);

            return $result;
        } catch (Exception $e) {
            Database::rollback($this->dbName);

            $errorResult = [
                'ok' => false,
                'document' => $document,
                'error' => $e->getMessage(),
            ];

            $this->debugValue('Document sync ERROR [' . $document . ']', $errorResult);

            return $errorResult;
        }
    }

    /**
     * Синхронизация значений пользовательского справочника.
     *
     * @param string $document
     * @param string $customEntityId
     * @return array<string, mixed>
     */
    public function syncCustomEntity($document, $customEntityId)
    {
        $this->debugLine('');
        $this->debugLine('CustomEntity sync: document=' . $document . ', customEntity=' . $customEntityId);

        $apiResponse = $this->ms->getCustomEntity($customEntityId);

        // DEBUG_START: сырой ответ API по customentity
        $this->debugValue('Raw customentity response [' . $document . '][' . $customEntityId . ']', $apiResponse);
        // DEBUG_END

        if ($this->isApiError($apiResponse)) {
            return [
                'ok' => false,
                'document' => $document,
                'customentity' => $customEntityId,
                'error' => $this->extractApiErrorMessage($apiResponse),
            ];
        }

        $rows = $this->extractRows($apiResponse);

        $archiveResult = Database::update(
            $this->dbName,
            'customentity',
            ['is_archived' => 1],
            [
                'document' => $document,
                'customentity' => $customEntityId,
            ]
        );

        // DEBUG_START: результат архивирования старых значений справочника
        $this->debugValue(
            'Archive old customentity rows [' . $document . '][' . $customEntityId . ']',
            $archiveResult
        );
        // DEBUG_END

        if (!$archiveResult['ok']) {
            return [
                'ok' => false,
                'document' => $document,
                'customentity' => $customEntityId,
                'error' => 'Archive customentity failed: ' . $archiveResult['error'],
            ];
        }

        $preparedRows = [];

        foreach ($rows as $row) {
            $preparedRows[] = $this->prepareCustomEntityRow($document, $customEntityId, $row);
        }

        // DEBUG_START: подготовленные строки для customentity
        $this->debugValue(
            'Prepared customentity rows [' . $document . '][' . $customEntityId . ']',
            $preparedRows
        );
        // DEBUG_END

        $upsertResult = [
            'ok' => true,
            'updated' => 0,
            'inserted' => 0,
            'errors' => [],
        ];

        if (!empty($preparedRows)) {
            $upsertResult = Database::upsertRows(
                $this->dbName,
                'customentity',
                $preparedRows,
                ['document', 'meta']
            );
        }

        // DEBUG_START: результат upsert по customentity
        $this->debugValue(
            'Upsert customentity result [' . $document . '][' . $customEntityId . ']',
            $upsertResult
        );
        // DEBUG_END

        if (!$upsertResult['ok'] || !empty($upsertResult['errors'])) {
            return [
                'ok' => false,
                'document' => $document,
                'customentity' => $customEntityId,
                'error' => !$upsertResult['ok']
                    ? 'Upsert customentity failed'
                    : 'Upsert customentity row errors: ' . implode('; ', $upsertResult['errors']),
                'details' => $upsertResult,
            ];
        }

        return [
            'ok' => true,
            'document' => $document,
            'customentity' => $customEntityId,
            'rows_found' => count($preparedRows),
            'inserted' => $upsertResult['inserted'],
            'updated' => $upsertResult['updated'],
        ];
    }

    /**
     * @param string $document
     * @param object|array<string, mixed> $row
     * @return array<string, mixed>
     */
    protected function prepareAttributeRow($document, $row)
    {
        $sourceName = $this->value($row, 'name');
        $type = $this->value($row, 'type');
        $msAttributeId = $this->value($row, 'id');

        $customEntityId = null;
        if ($type === 'customentity') {
            $customEntityHref = $this->nestedValue($row, ['customEntityMeta', 'href']);
            if ($customEntityHref) {
                $customEntityId = $this->extractLastPathSegment($customEntityHref);
            }
        }

        return [
            'document' => $document,
            'ms_attribute_id' => $msAttributeId,
            'name_source' => $sourceName,
            'name_code' => $this->makeCodeName($sourceName),
            'name_main' => null,
            'data_type' => $type,
            'custom_entity_id' => $customEntityId,
            'is_archived' => 0,
        ];
    }

    /**
     * @param string $document
     * @param string $customEntityId
     * @param object|array<string, mixed> $row
     * @return array<string, mixed>
     */
    protected function prepareCustomEntityRow($document, $customEntityId, $row)
    {
        $metaHref = $this->nestedValue($row, ['meta', 'href']);
        $metaId = $metaHref ? $this->extractLastPathSegment($metaHref) : null;

        return [
            'document' => $document,
            'customentity' => $customEntityId,
            'meta' => $metaId,
            'name_source' => $this->value($row, 'name'),
            'name_code' => $this->makeCodeName($this->value($row, 'name')),
            'is_archived' => 0,
        ];
    }

    /**
     * @param object|array<string, mixed>|null $response
     * @return array<int, object|array<string, mixed>>
     */
    protected function extractRows($response)
    {
        if (is_object($response) && isset($response->rows) && is_array($response->rows)) {
            return $response->rows;
        }

        if (is_array($response) && isset($response['rows']) && is_array($response['rows'])) {
            return $response['rows'];
        }

        return [];
    }

    /**
     * @param object|array<string, mixed>|null $response
     * @return bool
     */
    protected function isApiError($response)
    {
        if (!$response) {
            return true;
        }

        if (is_object($response)) {
            return isset($response->errors) || isset($response->error);
        }

        if (is_array($response)) {
            return isset($response['errors']) || isset($response['error']);
        }

        return true;
    }

    /**
     * @param object|array<string, mixed>|null $response
     * @return string
     */
    protected function extractApiErrorMessage($response)
    {
        if (!$response) {
            return 'Empty API response';
        }

        if (is_object($response)) {
            if (isset($response->errors) && is_array($response->errors) && !empty($response->errors)) {
                $messages = [];
                foreach ($response->errors as $error) {
                    if (is_object($error) && isset($error->error)) {
                        $messages[] = $error->error;
                    } elseif (is_object($error) && isset($error->moreInfo)) {
                        $messages[] = $error->moreInfo;
                    } else {
                        $messages[] = json_encode($error, JSON_UNESCAPED_UNICODE);
                    }
                }
                return implode(' | ', $messages);
            }

            if (isset($response->error)) {
                return (string)$response->error;
            }
        }

        if (is_array($response)) {
            if (isset($response['errors']) && is_array($response['errors']) && !empty($response['errors'])) {
                $messages = [];
                foreach ($response['errors'] as $error) {
                    if (is_array($error) && isset($error['error'])) {
                        $messages[] = $error['error'];
                    } elseif (is_array($error) && isset($error['moreInfo'])) {
                        $messages[] = $error['moreInfo'];
                    } else {
                        $messages[] = json_encode($error, JSON_UNESCAPED_UNICODE);
                    }
                }
                return implode(' | ', $messages);
            }

            if (isset($response['error'])) {
                return (string)$response['error'];
            }
        }

        return 'Unknown API error';
    }

    /**
     * @param object|array<string, mixed> $source
     * @param string $key
     * @return mixed|null
     */
    protected function value($source, $key)
    {
        if (is_object($source) && isset($source->{$key})) {
            return $source->{$key};
        }

        if (is_array($source) && array_key_exists($key, $source)) {
            return $source[$key];
        }

        return null;
    }

    /**
     * @param object|array<string, mixed> $source
     * @param array<int, string> $path
     * @return mixed|null
     */
    protected function nestedValue($source, array $path)
    {
        $current = $source;

        foreach ($path as $key) {
            if (is_object($current) && isset($current->{$key})) {
                $current = $current->{$key};
                continue;
            }

            if (is_array($current) && array_key_exists($key, $current)) {
                $current = $current[$key];
                continue;
            }

            return null;
        }

        return $current;
    }

    /**
     * @param string $href
     * @return string
     */
    protected function extractLastPathSegment($href)
    {
        $href = trim((string)$href);
        $href = rtrim($href, '/');

        if ($href === '') {
            return '';
        }

        $parts = explode('/', $href);
        return (string)end($parts);
    }

    /**
     * Техническое имя поля.
     * Специально сделал самодостаточную функцию без зависимости от внешнего translit helper.
     *
     * @param string|null $name
     * @return string
     */
    protected function makeCodeName($name)
    {
        $name = trim((string)$name);

        if ($name === '') {
            return '';
        }

        $map = [
            'А'=>'A','Б'=>'B','В'=>'V','Г'=>'G','Д'=>'D','Е'=>'E','Ё'=>'E','Ж'=>'Zh','З'=>'Z','И'=>'I','Й'=>'Y',
            'К'=>'K','Л'=>'L','М'=>'M','Н'=>'N','О'=>'O','П'=>'P','Р'=>'R','С'=>'S','Т'=>'T','У'=>'U','Ф'=>'F',
            'Х'=>'Kh','Ц'=>'Ts','Ч'=>'Ch','Ш'=>'Sh','Щ'=>'Shch','Ъ'=>'','Ы'=>'Y','Ь'=>'','Э'=>'E','Ю'=>'Yu','Я'=>'Ya',
            'І'=>'I','Ї'=>'Yi','Є'=>'Ye','Ґ'=>'G',
            'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'e','ж'=>'zh','з'=>'z','и'=>'i','й'=>'y',
            'к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f',
            'х'=>'kh','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'shch','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya',
            'і'=>'i','ї'=>'yi','є'=>'ye','ґ'=>'g',
        ];

        $name = strtr($name, $map);
        $name = mb_strtolower($name, 'UTF-8');
        $name = preg_replace('/[^a-z0-9]+/u', '_', $name);
        $name = preg_replace('/_+/', '_', $name);
        $name = trim($name, '_');

        return $name;
    }

    /**
     * DEBUG helpers
     */

    // DEBUG_START: быстро удалить этот метод и его вызовы, когда утилита будет готова
    protected function debugLine($text)
    {
        if (!$this->debug) {
            return;
        }

        if ($this->outputStream) {
            fwrite($this->outputStream, $text . PHP_EOL);
            return;
        }

        echo $text . PHP_EOL;
    }
    // DEBUG_END

    // DEBUG_START: быстро удалить этот метод и его вызовы, когда утилита будет готова
    protected function debugValue($title, $value)
    {
        if (!$this->debug) {
            return;
        }

        $this->debugLine('[DEBUG] ' . $title . ':');

        if (is_scalar($value) || $value === null) {
            $this->debugLine((string)$value);
            return;
        }

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        if ($encoded !== false) {
            $this->debugLine($encoded);
        } else {
            ob_start();
            print_r($value);
            $dump = ob_get_clean();
            $this->debugLine($dump);
        }
    }
    // DEBUG_END
}