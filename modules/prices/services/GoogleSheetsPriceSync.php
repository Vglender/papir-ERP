<?php

/**
 * Синхронизирует цены из Google Sheets в price_supplier_items.
 * Конфигурация столбцов хранится в price_supplier_pricelists.source_config (JSON).
 */
class GoogleSheetsPriceSync
{
    const GOOGLE_VENDOR_AUTOLOAD = '/var/www/menufold/data/www/officetorg.com.ua/Google/vendor/autoload.php';
    const CREDENTIALS_FILE       = __DIR__ . '/../google_credentials.json';

    /** @var PricelistRepository */
    private $pricelistRepo;

    /** @var PricelistItemRepository */
    private $itemRepo;

    public function __construct(PricelistRepository $pricelistRepo, PricelistItemRepository $itemRepo)
    {
        $this->pricelistRepo = $pricelistRepo;
        $this->itemRepo      = $itemRepo;
    }

    /**
     * @param int $pricelistId
     * @return array  ['ok'=>bool, 'imported'=>int, 'matched'=>int, 'error'=>string]
     */
    public function sync($pricelistId)
    {
        if (!file_exists(self::GOOGLE_VENDOR_AUTOLOAD)) {
            return array('ok' => false, 'error' => 'Google API vendor not found', 'imported' => 0, 'matched' => 0);
        }
        if (!file_exists(self::CREDENTIALS_FILE)) {
            return array('ok' => false, 'error' => 'Google credentials file not found', 'imported' => 0, 'matched' => 0);
        }

        $pricelist = $this->pricelistRepo->getById($pricelistId);
        if (!$pricelist || $pricelist['source_type'] !== 'google_sheets') {
            return array('ok' => false, 'error' => 'Pricelist not found or wrong type', 'imported' => 0, 'matched' => 0);
        }

        $config = $this->pricelistRepo->decodeConfig($pricelist);
        if (empty($config['spreadsheet_id'])) {
            return array('ok' => false, 'error' => 'Не настроены параметры Google Sheets (нет spreadsheet_id)', 'imported' => 0, 'matched' => 0);
        }

        require_once self::GOOGLE_VENDOR_AUTOLOAD;

        try {
            $client = new Google\Client();
            $client->setAuthConfig(self::CREDENTIALS_FILE);
            $client->setScopes(array(Google\Service\Sheets::SPREADSHEETS_READONLY));

            $service       = new Google\Service\Sheets($client);
            $spreadsheetId = $config['spreadsheet_id'];
            $headerRow     = isset($config['header_row']) ? max(0, (int)$config['header_row']) : 1;

            // Всегда получаем метаданные — валидируем документ и находим листы
            try {
                $meta = $service->spreadsheets->get($spreadsheetId);
            } catch (Exception $metaEx) {
                return array(
                    'ok'       => false,
                    'error'    => 'Не удалось получить метаданные. Проверьте ID таблицы и доступ сервисного аккаунта. ' . $metaEx->getMessage(),
                    'imported' => 0,
                    'matched'  => 0,
                );
            }

            $sheets     = $meta->getSheets();
            $sheetNames = array();
            foreach ($sheets as $s) {
                $sheetNames[] = $s->getProperties()->getTitle();
            }

            // Определяем имя листа
            if (!empty($config['sheet_name'])) {
                if (!in_array($config['sheet_name'], $sheetNames)) {
                    return array(
                        'ok'       => false,
                        'error'    => 'Лист "' . $config['sheet_name'] . '" не найден. Доступные листы: ' . implode(', ', $sheetNames),
                        'imported' => 0,
                        'matched'  => 0,
                    );
                }
                $sheetName = $config['sheet_name'];
            } else {
                $sheetName = $sheetNames[0];
            }

            // Читаем данные — пробуем несколько форматов диапазона
            $rawValues = null;
            $rangeFormats = array(
                "'" . str_replace("'", "''", $sheetName) . "'",           // 'ИмяЛиста'
                "'" . str_replace("'", "''", $sheetName) . "'!A:Z",       // 'ИмяЛиста'!A:Z
                $sheetName,                                                 // ИмяЛиста (без кавычек)
            );
            $lastRangeError = '';
            foreach ($rangeFormats as $range) {
                try {
                    $response  = $service->spreadsheets_values->get($spreadsheetId, $range);
                    $rawValues = $response->getValues();
                    break;
                } catch (Exception $rangeEx) {
                    $lastRangeError = $rangeEx->getMessage();
                }
            }
            if ($rawValues === null) {
                return array(
                    'ok'       => false,
                    'error'    => 'Не удалось прочитать данные листа "' . $sheetName . '". Доступные листы: ' . implode(', ', $sheetNames) . '. Ошибка: ' . $lastRangeError,
                    'imported' => 0,
                    'matched'  => 0,
                );
            }

            if (empty($rawValues)) {
                return array('ok' => true, 'imported' => 0, 'matched' => 0, 'error' => '');
            }

            $colSku   = $this->colIdx(isset($config['col_sku'])        ? $config['col_sku']        : '');
            $colModel = $this->colIdx(isset($config['col_model'])      ? $config['col_model']      : '');
            $colName  = $this->colIdx(isset($config['col_name'])       ? $config['col_name']       : '');
            $colCost  = $this->colIdx(isset($config['col_price_cost']) ? $config['col_price_cost'] : '');
            $colRrp   = $this->colIdx(isset($config['col_price_rrp'])  ? $config['col_price_rrp']  : '');

            $rawRows = array();
            foreach ($rawValues as $rowIndex => $row) {
                if ($rowIndex < $headerRow) continue;

                $sku   = $colSku   !== null ? $this->cell($row, $colSku)   : '';
                $model = $colModel !== null ? $this->cell($row, $colModel) : '';
                $name  = $colName  !== null ? $this->cell($row, $colName)  : '';
                $cost  = $colCost  !== null ? $this->parsePrice($this->cell($row, $colCost)) : null;
                $rrp   = $colRrp   !== null ? $this->parsePrice($this->cell($row, $colRrp))  : null;

                if ($sku === '' && $model === '') continue;
                if ($cost === null && $rrp === null) continue;

                $rawRows[] = array(
                    'raw_sku'    => $sku,
                    'raw_model'  => $model,
                    'raw_name'   => $name,
                    'price_cost' => $cost,
                    'price_rrp'  => $rrp,
                );
            }

            $stats = $this->itemRepo->replaceAll($pricelistId, $rawRows);
            $this->pricelistRepo->refreshStats($pricelistId);

            return array('ok' => true, 'imported' => $stats['inserted'], 'matched' => $stats['matched'], 'error' => '');

        } catch (Exception $e) {
            return array('ok' => false, 'error' => $e->getMessage(), 'imported' => 0, 'matched' => 0);
        }
    }

    private function colIdx($letter)
    {
        $letter = strtoupper(trim((string)$letter));
        if ($letter === '') return null;
        $result = 0;
        for ($i = 0; $i < strlen($letter); $i++) {
            $result = $result * 26 + (ord($letter[$i]) - 64);
        }
        return $result - 1;
    }

    private function cell(array $row, $index)
    {
        return ($index !== null && isset($row[$index])) ? trim((string)$row[$index]) : '';
    }

    private function parsePrice($str)
    {
        $str = str_replace(array(' ', "\xc2\xa0", ','), array('', '', '.'), $str);
        $str = preg_replace('/[^0-9.]/', '', $str);
        if ($str === '' || $str === '.') return null;
        $val = (float)$str;
        return $val > 0 ? $val : null;
    }
}
