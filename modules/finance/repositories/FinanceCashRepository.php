<?php

class FinanceCashRepository
{
    private function buildWhere($params, &$args)
    {
        $where = array('1=1');

        $showDrafts = !empty($params['show_drafts']);
        if (!$showDrafts) {
            $where[] = "fc.is_posted = 1";
        }

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

        // unmatched: показати тільки нерозпізнані (НЕРАЗОБРАННОЕ або без контрагента)
        if (!empty($params['unmatched'])) {
            $where[] = "(cp.id IS NULL OR cp.name = 'НЕРАЗОБРАННОЕ')";
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
                    $chipConds[] = 'fc.id = ' . (int)$chip;
                    continue;
                }
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
        // counterparty_id — локальний FK, джерело правди (як cp_id у finance_bank).
        // agent_ms лишається в таблиці, але читаємо через нього лише як fallback
        // для legacy-рядків (~1097), де cp був видалений локально, але існував
        // у МС. Це покриває обидва кейси без розгалуження UI-логіки.
        return "FROM finance_cash fc
                LEFT JOIN counterparty cp
                       ON cp.id = fc.counterparty_id
                       OR (fc.counterparty_id IS NULL AND cp.id_ms = fc.agent_ms)
                LEFT JOIN finance_expense_category fec ON fec.id = fc.expense_category_id";
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
                    fc.agent_ms, fc.counterparty_id, fc.organization_id,
                    fc.expense_item_ms, fc.operations, fc.source,
                    fc.expense_category_id,
                    fec.name AS expense_category_name,
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
        $args          = array();
        $summaryParams = $params;
        $summaryParams['show_drafts'] = false; // чернетки не влияют на сводку
        $where = $this->buildWhere($summaryParams, $args);
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