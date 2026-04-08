<?php

class FinanceBankRepository
{
    private function buildWhere($params, &$args)
    {
        $where = array('1=1');

        $direction = isset($params['direction']) ? trim($params['direction']) : '';
        if ($direction === 'in' || $direction === 'out') {
            $d = Database::escape('Papir', $direction);
            $where[] = "fb.direction = '{$d}'";
        }

        // hide_moving: якщо true — приховати внутрішні перекази
        $hideMoving = !empty($params['hide_moving']);
        if ($hideMoving) {
            $where[] = "fb.is_moving = 0";
        }

        // show_drafts: якщо false (за замовч.) — показувати тільки проведені (is_posted=1)
        $showDrafts = !empty($params['show_drafts']);
        if (!$showDrafts) {
            $where[] = "fb.is_posted = 1";
        }

        $dateFrom = isset($params['date_from']) ? trim($params['date_from']) : '';
        $dateTo   = isset($params['date_to'])   ? trim($params['date_to'])   : '';
        if ($dateFrom !== '') {
            $df = Database::escape('Papir', $dateFrom);
            $where[] = "fb.moment >= '{$df} 00:00:00'";
        }
        if ($dateTo !== '') {
            $dt = Database::escape('Papir', $dateTo);
            $where[] = "fb.moment <= '{$dt} 23:59:59'";
        }

        // unmatched: показати тільки нерозпізнані (НЕРАЗОБРАННОЕ cp_id=28352 або cp_id IS NULL)
        if (!empty($params['unmatched'])) {
            $where[] = "(fb.cp_id = 28352 OR (fb.cp_id IS NULL AND cp_m.id IS NULL))";
        }

        $search = isset($params['search']) ? trim($params['search']) : '';
        if ($search !== '') {
            $chipSep = (strpos($search, '|||') !== false) ? '/\s*\|\|\|\s*/u' : '/\s*,\s*/u';
            $chips   = preg_split($chipSep, $search);
            $chipConds = array();

            foreach ($chips as $chip) {
                $chip = trim($chip);
                if ($chip === '') continue;
                if (preg_match('/^\d+$/', $chip)) {
                    $chipConds[] = 'fb.id = ' . (int)$chip;
                    continue;
                }
                $tokens = preg_split('/\s+/u', mb_strtolower($chip, 'UTF-8'));
                $tokens = array_filter($tokens, function($t) { return $t !== ''; });
                $parts  = array();
                foreach ($tokens as $token) {
                    $t = Database::escape('Papir', $token);
                    $parts[] = "(LOWER(COALESCE(fb.doc_number,''))     LIKE '%{$t}%'
                              OR LOWER(COALESCE(fb.description,''))     LIKE '%{$t}%'
                              OR LOWER(COALESCE(fb.payment_purpose,'')) LIKE '%{$t}%'
                              OR LOWER(COALESCE(cp_d.name, cp_m.name,'')) LIKE '%{$t}%')";
                }
                if (!empty($parts)) {
                    $chipConds[] = '(' . implode(' AND ', $parts) . ')';
                }
            }

            if (!empty($chipConds)) {
                $where[] = '(' . implode(' OR ', $chipConds) . ')';
            }
        }

        return implode(' AND ', $where);
    }

    private function baseFrom()
    {
        return "FROM finance_bank fb
                LEFT JOIN counterparty cp_d ON cp_d.id = fb.cp_id
                LEFT JOIN counterparty cp_m ON cp_m.id_ms = fb.agent_ms AND fb.cp_id IS NULL
                LEFT JOIN finance_expense_category fec ON fec.id = fb.expense_category_id";
    }

    public function getList($params)
    {
        $limit  = isset($params['limit'])  ? max(1, (int)$params['limit'])  : 50;
        $offset = isset($params['offset']) ? max(0, (int)$params['offset']) : 0;
        $args   = array();
        $where  = $this->buildWhere($params, $args);

        $r = Database::fetchAll('Papir',
            "SELECT fb.id, fb.id_ms, fb.direction, fb.moment, fb.doc_number,
                    fb.sum, fb.description, fb.payment_purpose,
                    fb.is_posted, fb.is_moving, fb.agent_ms, fb.source,
                    fb.expense_item_ms, fb.operations, fb.external_code,
                    fb.expense_category_id,
                    fec.name AS expense_category_name,
                    COALESCE(cp_d.id, cp_m.id)     AS cp_id,
                    COALESCE(cp_d.name, cp_m.name) AS cp_name,
                    COALESCE(cp_d.type, cp_m.type) AS cp_type
             " . $this->baseFrom() . "
             WHERE {$where}
             ORDER BY fb.moment DESC
             LIMIT {$limit} OFFSET {$offset}"
        );
        return ($r['ok']) ? $r['rows'] : array();
    }

    public function getTotal($params)
    {
        $args  = array();
        $where = $this->buildWhere($params, $args);
        $r = Database::fetchRow('Papir',
            "SELECT COUNT(*) AS cnt " . $this->baseFrom() . " WHERE {$where}"
        );
        return ($r['ok'] && $r['row']) ? (int)$r['row']['cnt'] : 0;
    }

    public function getSummary($params)
    {
        // Підсумки завжди тільки реальних операцій (is_moving=0, is_posted=1)
        $realParams = $params;
        $realParams['hide_moving'] = true;
        $realParams['show_drafts'] = false;
        $args  = array();
        $where = $this->buildWhere($realParams, $args);

        $r = Database::fetchAll('Papir',
            "SELECT fb.direction, SUM(fb.sum) AS total
             " . $this->baseFrom() . "
             WHERE {$where}
             GROUP BY fb.direction"
        );
        $result = array('in' => 0.0, 'out' => 0.0);
        if ($r['ok']) {
            foreach ($r['rows'] as $row) {
                $result[$row['direction']] = (float)$row['total'];
            }
        }

        return $result;
    }
}
