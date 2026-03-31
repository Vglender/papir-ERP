<?php

class PrintTemplateRepository
{
    public function getTypes()
    {
        $r = Database::fetchAll('Papir',
            "SELECT * FROM print_template_types WHERE status=1 ORDER BY id"
        );
        return ($r['ok'] && !empty($r['rows'])) ? $r['rows'] : array();
    }

    public function getList($typeId = 0, $status = '')
    {
        $where = array('1=1');
        if ($typeId > 0) {
            $where[] = 'pt.type_id = ' . (int)$typeId;
        }
        if ($status !== '') {
            $safeStatus = Database::escape('Papir', $status);
            $where[] = "pt.status = '{$safeStatus}'";
        }
        $r = Database::fetchAll('Papir',
            "SELECT pt.id, pt.code, pt.name, pt.status, pt.version, pt.created_at,
                    ptt.name AS type_name, ptt.code AS type_code
             FROM print_templates pt
             JOIN print_template_types ptt ON ptt.id = pt.type_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY ptt.id, pt.status DESC, pt.version DESC"
        );
        return ($r['ok'] && !empty($r['rows'])) ? $r['rows'] : array();
    }

    public function getById($id)
    {
        $id = (int)$id;
        $r = Database::fetchRow('Papir',
            "SELECT pt.*, ptt.name AS type_name, ptt.code AS type_code
             FROM print_templates pt
             JOIN print_template_types ptt ON ptt.id = pt.type_id
             WHERE pt.id = {$id}"
        );
        return ($r['ok'] && !empty($r['row'])) ? $r['row'] : null;
    }

    public function save($data)
    {
        $id = isset($data['id']) ? (int)$data['id'] : 0;

        $fields = array(
            'type_id'          => isset($data['type_id'])          ? (int)$data['type_id']    : 0,
            'code'             => isset($data['code'])             ? trim($data['code'])       : '',
            'name'             => isset($data['name'])             ? trim($data['name'])       : '',
            'html_body'        => isset($data['html_body'])        ? $data['html_body']        : '',
            'status'           => isset($data['status'])           ? $data['status']           : 'draft',
            'variables_schema' => isset($data['variables_schema']) ? $data['variables_schema'] : null,
            'page_settings'    => isset($data['page_settings'])    ? $data['page_settings']    : null,
        );

        if ($fields['type_id'] <= 0) {
            return array('ok' => false, 'error' => 'type_id required');
        }
        if (empty($fields['code']) || empty($fields['name'])) {
            return array('ok' => false, 'error' => 'code and name required');
        }

        $allowedStatuses = array('draft', 'active', 'archived');
        if (!in_array($fields['status'], $allowedStatuses)) {
            $fields['status'] = 'draft';
        }

        if ($id > 0) {
            $r = Database::update('Papir', 'print_templates', $fields, array('id' => $id));
            if (!$r['ok']) {
                return array('ok' => false, 'error' => 'Update failed');
            }
            return array('ok' => true, 'id' => $id);
        } else {
            $fields['version']   = 1;
            $fields['parent_id'] = null;
            $r = Database::insert('Papir', 'print_templates', $fields);
            if (!$r['ok']) {
                return array('ok' => false, 'error' => 'Insert failed');
            }
            $lr = Database::fetchRow('Papir', "SELECT LAST_INSERT_ID() AS new_id");
            $newId = ($lr['ok'] && !empty($lr['row'])) ? (int)$lr['row']['new_id'] : 0;
            return array('ok' => true, 'id' => $newId);
        }
    }

    /**
     * Clone template as new version (parent_id = current id, version++)
     */
    public function createVersion($parentId)
    {
        $parentId = (int)$parentId;
        $parent   = $this->getById($parentId);
        if (!$parent) {
            return array('ok' => false, 'error' => 'Template not found');
        }

        $newCode = $parent['code'] . '_v' . ((int)$parent['version'] + 1);
        // Make unique if exists
        $check = Database::fetchRow('Papir', "SELECT id FROM print_templates WHERE code='" . Database::escape('Papir', $newCode) . "'");
        if ($check['ok'] && !empty($check['row'])) {
            $newCode = $parent['code'] . '_v' . time();
        }

        $fields = array(
            'type_id'          => $parent['type_id'],
            'parent_id'        => $parentId,
            'code'             => $newCode,
            'name'             => $parent['name'] . ' (v' . ((int)$parent['version'] + 1) . ')',
            'html_body'        => $parent['html_body'],
            'variables_schema' => $parent['variables_schema'],
            'page_settings'    => $parent['page_settings'],
            'status'           => 'draft',
            'version'          => (int)$parent['version'] + 1,
        );

        $r = Database::insert('Papir', 'print_templates', $fields);
        if (!$r['ok']) {
            return array('ok' => false, 'error' => 'Insert failed');
        }
        $lr = Database::fetchRow('Papir', "SELECT LAST_INSERT_ID() AS new_id");
        $newId = ($lr['ok'] && !empty($lr['row'])) ? (int)$lr['row']['new_id'] : 0;
        return array('ok' => true, 'id' => $newId);
    }
}