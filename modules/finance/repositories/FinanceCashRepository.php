<?php

class FinanceCashRepository
{
    private function buildWhere($params, &$args)
    {
        $where = array('1=1');

        $direction = isset($params['direction']) ? trim($params['direction']) : '';
        if ($direction === 'in' || $direction === 'out') {
            $d = Database::escape('Papir', $direction);
            $where[] = "fc.direction = '{$d}'";
        }

        $dateFrom = isset($params['date_from']) ? trim($params['date_from']) : '';
        $dateTo   = isset($params['date_to'])   ? trim($params['date_to'])   : '';
        if ($dateFrom !== '') {
            $df = Database::escape('Papir', $dateFrom);
            $where[] = "fc.moment >= '{$df} 00:00:00'";
        }
        if ($dateTo !== '') {
            $dt = Database::escape('Papir', $dateTo);
            $where[] = "fc.moment <= '{$dt} 23:59:59'";
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
                    $parts[] = "(LOWER(COALESCE(fc.doc_number,''))  LIKE '%{$t}%'
                              OR LOWER(COALESCE(fc.description,'')) LIKE '%{$t}%'
                              OR LOWER(COALESCE(cp.name,''))        LIKE '%{$t}%')";
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
        return "FROM finance_cash fc
                LEFT JOIN counterparty cp ON cp.id_ms = fc.agent_ms";
    }

    public function getList($params)
    {
        $limit  = isset($params['limit'])  ? max(1, (int)$params['limit'])  : 50;
        $offset = isset($params['offset']) ? max(0, (int)$params['offset']) : 0;
        $args   = array();
        $where  = $this->buildWhere($params, $args);

        $r = Database::fetchAll('Papir',
            "SELECT fc.id, fc.id_ms, fc.direction, fc.moment, fc.doc_number,
                    fc.sum, fc.description, fc.payment_purpose, fc.is_posted, fc.is_moving,
                    fc.agent_ms, fc.expense_item_ms, fc.operations, fc.source,
                    cp.id   AS cp_id,
                    cp.name AS cp_name,
                    cp.type AS cp_type
             " . $this->baseFrom() . "
             WHERE {$where}
             ORDER BY fc.moment DESC
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
        $args  = array();
        $where = $this->buildWhere($params, $args);
        $r = Database::fetchAll('Papir',
            "SELECT fc.direction, SUM(fc.sum) AS total
             " . $this->baseFrom() . "
             WHERE {$where}
             GROUP BY fc.direction"
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