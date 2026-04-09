<?php
/**
 * Generates a document pack for a demand (shipment).
 *
 * A pack is an ordered list of documents (local PDFs + external URLs)
 * ready for sequential printing.
 */
class PackGenerator
{
    /**
     * Generate pack for a single demand.
     *
     * @param int      $demandId
     * @param int      $profileId   0 = use default profile
     * @param int|null $createdBy   user id
     * @return array   {ok, pack_id, items[]}
     */
    public static function generate($demandId, $profileId = 0, $createdBy = null)
    {
        $demandId  = (int)$demandId;
        $profileId = (int)$profileId;

        // Load demand
        $rDemand = Database::fetchRow('Papir',
            "SELECT d.id, d.number, d.moment, d.created_at,
                    d.organization_id, d.customerorder_id, d.delivery_method_id,
                    dm.code AS delivery_code
             FROM demand d
             LEFT JOIN delivery_method dm ON dm.id = d.delivery_method_id
             WHERE d.id = {$demandId} AND d.deleted_at IS NULL
             LIMIT 1");
        if (!$rDemand['ok'] || empty($rDemand['row'])) {
            return array('ok' => false, 'error' => 'Відвантаження не знайдено');
        }
        $demand = $rDemand['row'];

        // Load profile
        $profile = self::loadProfile($profileId, (int)$demand['organization_id']);
        if (!$profile) {
            return array('ok' => false, 'error' => 'Профіль пакету не знайдено');
        }

        $profileItems = json_decode($profile['items_json'], true);
        if (empty($profileItems) || !is_array($profileItems)) {
            return array('ok' => false, 'error' => 'Профіль порожній');
        }

        // Create pending job
        $rJob = Database::insert('Papir', 'print_pack_jobs', array(
            'demand_id'  => $demandId,
            'profile_id' => (int)$profile['id'],
            'status'     => 'pending',
            'items_json' => '[]',
            'created_by' => $createdBy,
        ));
        if (!$rJob['ok'] || empty($rJob['insert_id'])) {
            return array('ok' => false, 'error' => 'Не вдалось створити job');
        }
        $jobId = (int)$rJob['insert_id'];

        // Process each item
        $resultItems = array();
        $hasError = false;

        foreach ($profileItems as $item) {
            $type = isset($item['type']) ? $item['type'] : '';

            if ($type === 'template') {
                $result = self::generateTemplatePdf($demand, $item, 'demand');
            } elseif ($type === 'order_template') {
                $result = self::generateOrderTemplatePdf($demand, $item);
            } elseif ($type === 'carrier_sticker' || $type === 'ttn_sticker') {
                $result = self::buildCarrierSticker($demand, $item);
            } else {
                $result = array(array(
                    'type'   => $type,
                    'label'  => isset($item['label']) ? $item['label'] : 'Невідомий тип',
                    'status' => 'error',
                    'error'  => 'Невідомий тип: ' . $type,
                ));
                $hasError = true;
            }

            foreach ($result as $r) {
                if (isset($r['status']) && $r['status'] === 'error') {
                    $hasError = true;
                }
                $resultItems[] = $r;
            }
        }

        // Update job
        $status = $hasError && empty(array_filter($resultItems, function($r) {
            return isset($r['status']) && $r['status'] === 'ok';
        })) ? 'error' : 'ready';

        Database::update('Papir', 'print_pack_jobs',
            array(
                'status'     => $status,
                'items_json' => json_encode($resultItems, JSON_UNESCAPED_UNICODE),
            ),
            array('id' => $jobId));

        return array(
            'ok'      => true,
            'pack_id' => $jobId,
            'status'  => $status,
            'items'   => $resultItems,
        );
    }

    /**
     * Auto-generate pack when demand reaches 'assembled' status.
     */
    public static function autoGenerate($demandId)
    {
        $demandId = (int)$demandId;

        // Check if a recent pack already exists (last 24h)
        $r = Database::fetchRow('Papir',
            "SELECT id FROM print_pack_jobs
             WHERE demand_id = {$demandId}
               AND status = 'ready'
               AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
             ORDER BY id DESC LIMIT 1");
        if ($r['ok'] && !empty($r['row'])) {
            return; // already has a recent pack
        }

        self::generate($demandId, 0, null);
    }

    /**
     * Get the latest ready pack for a demand.
     */
    public static function getLatest($demandId)
    {
        $demandId = (int)$demandId;
        $r = Database::fetchRow('Papir',
            "SELECT pj.*, pp.name AS profile_name
             FROM print_pack_jobs pj
             LEFT JOIN print_pack_profiles pp ON pp.id = pj.profile_id
             WHERE pj.demand_id = {$demandId}
               AND pj.status = 'ready'
             ORDER BY pj.id DESC LIMIT 1");
        return ($r['ok'] && !empty($r['row'])) ? $r['row'] : null;
    }

    // ── Private helpers ──────────────────────────────────────────────────

    private static function loadProfile($profileId, $orgId)
    {
        // Explicit profile
        if ($profileId > 0) {
            $r = Database::fetchRow('Papir',
                "SELECT * FROM print_pack_profiles WHERE id = {$profileId}");
            if ($r['ok'] && !empty($r['row'])) return $r['row'];
        }

        // Org default
        if ($orgId > 0) {
            $r = Database::fetchRow('Papir',
                "SELECT * FROM print_pack_profiles
                 WHERE org_id = {$orgId} AND is_default = 1
                 ORDER BY id DESC LIMIT 1");
            if ($r['ok'] && !empty($r['row'])) return $r['row'];
        }

        // Global default
        $r = Database::fetchRow('Papir',
            "SELECT * FROM print_pack_profiles
             WHERE org_id IS NULL AND is_default = 1
             ORDER BY id DESC LIMIT 1");
        if ($r['ok'] && !empty($r['row'])) return $r['row'];

        // Any profile
        $r = Database::fetchRow('Papir',
            "SELECT * FROM print_pack_profiles ORDER BY id ASC LIMIT 1");
        return ($r['ok'] && !empty($r['row'])) ? $r['row'] : null;
    }

    /**
     * Generate PDF from a template for a demand.
     */
    private static function generateTemplatePdf($demand, $item, $entityType = 'demand')
    {
        $templateId = isset($item['template_id']) ? (int)$item['template_id'] : 0;
        $label      = isset($item['label']) ? $item['label'] : 'Документ';

        require_once __DIR__ . '/../PrintContextBuilder.php';
        require_once __DIR__ . '/../repositories/PrintTemplateRepository.php';

        $repo = new PrintTemplateRepository();
        $tpl  = $templateId > 0 ? $repo->getById($templateId) : null;

        if (!$tpl) {
            return array(array(
                'type' => 'template', 'label' => $label,
                'status' => 'error', 'error' => 'Шаблон id=' . $templateId . ' не знайдено',
            ));
        }

        $demandId = (int)$demand['id'];
        $context  = PrintContextBuilder::build('demand', $demandId, 0);
        if (empty($context)) {
            return array(array(
                'type' => 'template', 'label' => $label,
                'status' => 'error', 'error' => 'Не вдалось зібрати контекст',
            ));
        }

        require_once __DIR__ . '/../../../vendor/autoload.php';
        $mustache = new Mustache_Engine();
        $html     = $mustache->render($tpl['html_body'], $context);

        // Convert image paths
        $html = preg_replace_callback(
            '/(<img[^>]+src=")\/storage\/([^"]+)(")/i',
            function ($m) { return $m[1] . '/var/www/papir/storage/' . $m[2] . $m[3]; },
            $html
        );

        // Output path
        $moment  = !empty($demand['moment']) ? $demand['moment'] : $demand['created_at'];
        $subdir  = date('Y_m', strtotime($moment));
        $baseDir = '/var/www/menufold/data/www/officetorg.com.ua/docum/demand/';
        $dir     = $baseDir . $subdir . '/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $safeNum  = preg_replace('/[^A-Za-z0-9_\-]/', '', $demand['number']);
        $filename = $safeNum . '_t' . $templateId . '.pdf';
        $filePath = $dir . $filename;

        // Page settings
        $ps = array('margin_left' => 15, 'margin_right' => 15, 'margin_top' => 15, 'margin_bottom' => 15);
        if (!empty($tpl['page_settings'])) {
            $parsed = json_decode($tpl['page_settings'], true);
            if ($parsed) $ps = array_merge($ps, $parsed);
        }

        $mpdf = new \Mpdf\Mpdf(array(
            'mode'          => 'utf-8',
            'format'        => isset($ps['format']) ? $ps['format'] : 'A4',
            'tempDir'       => '/tmp/mpdf',
            'margin_left'   => $ps['margin_left'],
            'margin_right'  => $ps['margin_right'],
            'margin_top'    => $ps['margin_top'],
            'margin_bottom' => $ps['margin_bottom'],
        ));
        $mpdf->SetTitle($label . ' ' . $demand['number']);
        $mpdf->WriteHTML($html);
        $mpdf->Output($filePath, 'F');

        $url = 'https://officetorg.com.ua/docum/demand/' . $subdir . '/' . $filename;

        return array(array(
            'type'        => 'template',
            'template_id' => $templateId,
            'label'       => $label,
            'url'         => $url,
            'filename'    => $filename,
            'status'      => 'ok',
        ));
    }

    /**
     * Generate PDF from a template for the linked customerorder.
     */
    private static function generateOrderTemplatePdf($demand, $item)
    {
        $orderId = (int)$demand['customerorder_id'];
        $label   = isset($item['label']) ? $item['label'] : 'Рахунок';

        if ($orderId <= 0) {
            return array(array(
                'type' => 'order_template', 'label' => $label,
                'status' => 'skip', 'error' => 'Немає пов\'язаного замовлення',
            ));
        }

        $templateId = isset($item['template_id']) ? (int)$item['template_id'] : 0;

        require_once __DIR__ . '/../PrintContextBuilder.php';
        require_once __DIR__ . '/../repositories/PrintTemplateRepository.php';

        $repo = new PrintTemplateRepository();
        $tpl  = $templateId > 0 ? $repo->getById($templateId) : null;
        if (!$tpl) {
            return array(array(
                'type' => 'order_template', 'label' => $label,
                'status' => 'error', 'error' => 'Шаблон id=' . $templateId . ' не знайдено',
            ));
        }

        $context = PrintContextBuilder::build('order', $orderId, 0);
        if (empty($context)) {
            return array(array(
                'type' => 'order_template', 'label' => $label,
                'status' => 'error', 'error' => 'Не вдалось зібрати контекст замовлення',
            ));
        }

        require_once __DIR__ . '/../../../vendor/autoload.php';
        $mustache = new Mustache_Engine();
        $html     = $mustache->render($tpl['html_body'], $context);

        $html = preg_replace_callback(
            '/(<img[^>]+src=")\/storage\/([^"]+)(")/i',
            function ($m) { return $m[1] . '/var/www/papir/storage/' . $m[2] . $m[3]; },
            $html
        );

        // Load order for path
        $rOrder = Database::fetchRow('Papir',
            "SELECT number, moment, created_at FROM customerorder WHERE id = {$orderId} LIMIT 1");
        $orderRow = ($rOrder['ok'] && !empty($rOrder['row'])) ? $rOrder['row'] : array();
        $moment   = !empty($orderRow['moment']) ? $orderRow['moment'] : (isset($orderRow['created_at']) ? $orderRow['created_at'] : date('Y-m-d'));
        $number   = isset($orderRow['number']) ? $orderRow['number'] : $orderId;
        $subdir   = date('Y_m', strtotime($moment));

        $baseDir  = '/var/www/menufold/data/www/officetorg.com.ua/docum/customerorder/';
        $dir      = $baseDir . $subdir . '/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $safeNum  = preg_replace('/[^A-Za-z0-9_\-]/', '', $number);
        $filename = $safeNum . '_t' . $templateId . '.pdf';
        $filePath = $dir . $filename;

        $ps = array('margin_left' => 15, 'margin_right' => 15, 'margin_top' => 15, 'margin_bottom' => 15);
        if (!empty($tpl['page_settings'])) {
            $parsed = json_decode($tpl['page_settings'], true);
            if ($parsed) $ps = array_merge($ps, $parsed);
        }

        $mpdf = new \Mpdf\Mpdf(array(
            'mode' => 'utf-8', 'format' => isset($ps['format']) ? $ps['format'] : 'A4',
            'tempDir' => '/tmp/mpdf',
            'margin_left' => $ps['margin_left'], 'margin_right' => $ps['margin_right'],
            'margin_top' => $ps['margin_top'], 'margin_bottom' => $ps['margin_bottom'],
        ));
        $mpdf->SetTitle($label . ' ' . $number);
        $mpdf->WriteHTML($html);
        $mpdf->Output($filePath, 'F');

        $url = 'https://officetorg.com.ua/docum/customerorder/' . $subdir . '/' . $filename;

        return array(array(
            'type'        => 'order_template',
            'template_id' => $templateId,
            'label'       => $label,
            'url'         => $url,
            'filename'    => $filename,
            'status'      => 'ok',
        ));
    }

    /**
     * Auto-detect carrier and build sticker URLs.
     * Checks: 1) demand.delivery_method_id  2) linked TTNs (НП first, then УП)
     */
    private static function buildCarrierSticker($demand, $item)
    {
        $demandId = (int)$demand['id'];
        $orderId  = (int)$demand['customerorder_id'];
        $format   = isset($item['format']) ? $item['format'] : '100x100';
        $label    = isset($item['label']) ? $item['label'] : 'Стікер перевізника';

        $linkWhere = "(demand_id = {$demandId}" . ($orderId > 0 ? " OR customerorder_id = {$orderId}" : '') . ")";

        // 1) Try Nova Poshta
        $rNp = Database::fetchAll('Papir',
            "SELECT t.id, t.int_doc_number, t.ref, s.api AS sender_api
             FROM ttn_novaposhta t
             LEFT JOIN np_sender s ON s.Ref = t.sender_ref
             WHERE t.deletion_mark = 0 AND {$linkWhere}
             ORDER BY t.id DESC");

        if ($rNp['ok'] && !empty($rNp['rows'])) {
            $results = array();
            foreach ($rNp['rows'] as $ttn) {
                if (empty($ttn['int_doc_number']) || empty($ttn['sender_api'])) {
                    $results[] = array(
                        'type'   => 'carrier_sticker',
                        'label'  => 'НП ' . ($ttn['int_doc_number'] ?: '(без номера)'),
                        'status' => 'skip',
                        'error'  => 'ТТН без номера або API ключа',
                    );
                    continue;
                }
                $results[] = array(
                    'type'     => 'carrier_sticker',
                    'ttn_id'   => (int)$ttn['id'],
                    'carrier'  => 'novaposhta',
                    'label'    => 'НП ' . $ttn['int_doc_number'],
                    'url'      => '/novaposhta/api/print_ttn_sticker?ttn_id=' . (int)$ttn['id'] . '&format=' . urlencode($format),
                    'status'   => 'ok',
                    'external' => true,
                );
            }
            return $results;
        }

        // 2) Try Ukrposhta
        $rUp = Database::fetchAll('Papir',
            "SELECT id, barcode FROM ttn_ukrposhta WHERE {$linkWhere} ORDER BY id DESC");

        if ($rUp['ok'] && !empty($rUp['rows'])) {
            $results = array();
            foreach ($rUp['rows'] as $ttn) {
                $barcode = isset($ttn['barcode']) ? $ttn['barcode'] : '';
                if (empty($barcode)) {
                    $results[] = array(
                        'type'   => 'carrier_sticker',
                        'label'  => 'Укрпошта (без штрихкоду)',
                        'status' => 'skip',
                        'error'  => 'ТТН без штрихкоду',
                    );
                    continue;
                }
                $results[] = array(
                    'type'     => 'carrier_sticker',
                    'carrier'  => 'ukrposhta',
                    'label'    => 'УП ' . $barcode,
                    'url'      => 'https://track.ukrposhta.ua/tracking_UA.html?barcode=' . urlencode($barcode),
                    'status'   => 'ok',
                    'external' => true,
                );
            }
            return $results;
        }

        // 3) No TTNs found
        return array(array(
            'type'   => 'carrier_sticker',
            'label'  => $label,
            'status' => 'skip',
            'error'  => 'ТТН не знайдено',
        ));
    }
}
