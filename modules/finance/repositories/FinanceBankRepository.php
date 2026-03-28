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

        $showMoving = !empty($params['show_moving']);
        if (!$showMoving) {
            $where[] = "fb.is_moving = 0";
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

        $search = isset($params['search']) ? trim($params['search']) : '';
        if ($search !== '') {
            $chipSep = (strpos($search, '|||') !== false) ? '/\s*\|\|\|\s*/u' : '/\s*,\s*/u';
            $chips   = preg_split($chipSep, $search);
            $chipConds = array();

            foreach ($chips as $chip) {
                $chip = trim($chip);
                if ($chip === '') continue;
                $tokens = preg_split('/\s+/u', mb_strtolower($chip, 'UTF-8'));
                $tokens = array_filter($tokens, function($t) { return $t !== ''; });
                $parts  = array();
                foreach ($tokens as $token) {
                    $t = Database::escape('Papir', $token);
                    $parts[] = "(LOWER(COALESCE(fb.doc_number,''))   LIKE '%{$t}%'
                              OR LOWER(COALESCE(fb.description,''))   LIKE '%{$t}%'
                              OR LOWER(COALESCE(fb.payment_purpose,'')) LIKE '%{$t}%'
                              OR LOWER(COALESCE(cp.name,''))           LIKE '%{$t}%')";
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
                LEFT JOIN counterparty cp ON cp.id_ms = fb.agent_ms";
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
                    fb.is_posted, fb.is_moving, fb.agent_ms,
                    fb.expense_item_ms, fb.operations, fb.external_code,
                    cp.id   AS cp_id,
                    cp.name AS cp_name,
                    cp.type AS cp_type
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
        // Підсумки реальних операцій (is_moving=0) — завжди, незалежно від фільтра show_moving
        $realParams = $params;
        $realParams['show_moving'] = false;
        $args  = array();
        $where = $this->buildWhere($realParams, $args);

        $r = Database::fetchAll('Papir',
            "SELECT fb.direction, SUM(fb.sum) AS total
             " . $this->baseFrom() . "
             WHERE {$where}
             GROUP BY fb.direction"
        );
        $result = array('in' => 0.0, 'out' => 0.0, 'moving_in' => 0.0, 'moving_out' => 0.0);
        if ($r['ok']) {
            foreach ($r['rows'] as $row) {
                $result[$row['direction']] = (float)$row['total'];
            }
        }

        // Окремо підрахувати внутрішні перекази
        $movingParams = $params;
        $movingParams['show_moving'] = true;
        $movingParams['force_moving'] = true; // тільки moving
        $args2  = array();
        $where2 = $this->buildWhere($movingParams, $args2);
        $r2 = Database::fetchAll('Papir',
            "SELECT fb.direction, SUM(fb.sum) AS total
             " . $this->baseFrom() . "
             WHERE {$where2} AND fb.is_moving = 1
             GROUP BY fb.direction"
        );
        if ($r2['ok']) {
            foreach ($r2['rows'] as $row) {
                $result['moving_' . $row['direction']] = (float)$row['total'];
            }
        }

        return $result;
    }
}
