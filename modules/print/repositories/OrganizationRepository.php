<?php

class OrganizationRepository
{
    public function getList()
    {
        $r = Database::fetchAll('Papir',
            "SELECT o.id, o.alias, o.name, o.short_name, o.status, o.is_default,
                    o.director_name, o.director_title,
                    o.logo_path, o.stamp_path, o.signature_path
             FROM organization o
             ORDER BY o.status DESC, o.is_default DESC, o.name ASC"
        );
        return ($r['ok'] && !empty($r['rows'])) ? $r['rows'] : array();
    }

    public function getById($id)
    {
        $id = (int)$id;
        $r = Database::fetchRow('Papir',
            "SELECT * FROM organization WHERE id = {$id}"
        );
        if (!$r['ok'] || empty($r['row'])) {
            return null;
        }
        $org = $r['row'];
        $org['bank_accounts'] = $this->getBankAccounts($id);
        return $org;
    }

    public function getBankAccounts($orgId)
    {
        $orgId = (int)$orgId;
        $r = Database::fetchAll('Papir',
            "SELECT * FROM organization_bank_account
             WHERE organization_id = {$orgId}
             ORDER BY is_default DESC, id ASC"
        );
        return ($r['ok'] && !empty($r['rows'])) ? $r['rows'] : array();
    }

    public function save($data)
    {
        $id = isset($data['id']) ? (int)$data['id'] : 0;

        $fields = array(
            'name'           => isset($data['name'])           ? trim($data['name'])           : '',
            'short_name'     => isset($data['short_name'])     ? trim($data['short_name'])     : null,
            'alias'          => isset($data['alias'])          ? strtoupper(trim($data['alias'])) : null,
            'code'           => isset($data['code'])           ? trim($data['code'])           : null,
            'okpo'           => isset($data['okpo'])           ? trim($data['okpo'])           : null,
            'inn'            => isset($data['inn'])            ? trim($data['inn'])            : null,
            'vat_number'     => isset($data['vat_number'])     ? trim($data['vat_number'])     : null,
            'legal_address'  => isset($data['legal_address'])  ? trim($data['legal_address'])  : null,
            'actual_address' => isset($data['actual_address']) ? trim($data['actual_address']) : null,
            'director_name'  => isset($data['director_name'])  ? trim($data['director_name'])  : null,
            'director_title' => isset($data['director_title']) ? trim($data['director_title']) : null,
            'phone'          => isset($data['phone'])          ? trim($data['phone'])          : null,
            'email'          => isset($data['email'])          ? trim($data['email'])          : null,
            'website'        => isset($data['website'])        ? trim($data['website'])        : null,
            'description'    => isset($data['description'])   ? trim($data['description'])    : null,
            'status'         => isset($data['status'])        ? (int)$data['status']           : 1,
            'is_vat_payer'   => !empty($data['is_vat_payer']) ? 1 : 0,
            'default_store_id'                 => !empty($data['default_store_id'])                 ? (int)$data['default_store_id']                 : null,
            'default_delivery_method_id'       => !empty($data['default_delivery_method_id'])       ? (int)$data['default_delivery_method_id']       : null,
            'default_payment_method_id_legal'  => !empty($data['default_payment_method_id_legal'])  ? (int)$data['default_payment_method_id_legal']  : null,
            'default_payment_method_id_person' => !empty($data['default_payment_method_id_person']) ? (int)$data['default_payment_method_id_person'] : null,
        );

        // Nullable string fields — store null instead of empty string
        foreach (array('short_name','alias','code','okpo','inn','vat_number',
                       'legal_address','actual_address','director_name','director_title',
                       'phone','email','website','description') as $f) {
            if (isset($fields[$f]) && $fields[$f] === '') {
                $fields[$f] = null;
            }
        }

        if ($id > 0) {
            $r = Database::update('Papir', 'organization', $fields, array('id' => $id));
            if (!$r['ok']) {
                return array('ok' => false, 'error' => 'Update failed');
            }
            return array('ok' => true, 'id' => $id);
        } else {
            $r = Database::insert('Papir', 'organization', $fields);
            if (!$r['ok']) {
                return array('ok' => false, 'error' => 'Insert failed');
            }
            $lr = Database::fetchRow('Papir', "SELECT LAST_INSERT_ID() AS new_id");
            $newId = ($lr['ok'] && !empty($lr['row'])) ? (int)$lr['row']['new_id'] : 0;
            return array('ok' => true, 'id' => $newId);
        }
    }

    public function saveBankAccount($data)
    {
        $id    = isset($data['id'])              ? (int)$data['id']              : 0;
        $orgId = isset($data['organization_id']) ? (int)$data['organization_id'] : 0;
        if ($orgId <= 0) {
            return array('ok' => false, 'error' => 'organization_id required');
        }

        $fields = array(
            'organization_id' => $orgId,
            'account_name'    => isset($data['account_name']) ? trim($data['account_name']) : null,
            'bank_name'       => isset($data['bank_name'])    ? trim($data['bank_name'])    : null,
            'mfo'             => isset($data['mfo'])          ? trim($data['mfo'])          : null,
            'iban'            => isset($data['iban'])         ? strtoupper(trim($data['iban'])) : '',
            'currency_code'   => isset($data['currency_code']) ? strtoupper(trim($data['currency_code'])) : 'UAH',
            'is_default'      => isset($data['is_default'])  ? (int)(bool)$data['is_default'] : 0,
            'status'          => 1,
        );

        foreach (array('account_name','bank_name','mfo') as $f) {
            if (isset($fields[$f]) && $fields[$f] === '') {
                $fields[$f] = null;
            }
        }

        if ($fields['is_default']) {
            // unset other defaults for this org
            Database::query('Papir',
                "UPDATE organization_bank_account SET is_default=0 WHERE organization_id={$orgId}"
            );
        }

        if ($id > 0) {
            $r = Database::update('Papir', 'organization_bank_account', $fields, array('id' => $id));
            if (!$r['ok']) {
                return array('ok' => false, 'error' => 'Update failed');
            }
            return array('ok' => true, 'id' => $id);
        } else {
            $r = Database::insert('Papir', 'organization_bank_account', $fields);
            if (!$r['ok']) {
                return array('ok' => false, 'error' => 'Insert failed');
            }
            $lr = Database::fetchRow('Papir', "SELECT LAST_INSERT_ID() AS new_id");
            $newId = ($lr['ok'] && !empty($lr['row'])) ? (int)$lr['row']['new_id'] : 0;
            return array('ok' => true, 'id' => $newId);
        }
    }

    public function deleteBankAccount($id, $orgId)
    {
        $id    = (int)$id;
        $orgId = (int)$orgId;
        $r = Database::query('Papir',
            "DELETE FROM organization_bank_account WHERE id={$id} AND organization_id={$orgId}"
        );
        return $r['ok'];
    }

    public function updateImageField($orgId, $field, $path)
    {
        $allowed = array('logo_path', 'stamp_path', 'signature_path');
        if (!in_array($field, $allowed)) {
            return false;
        }
        $orgId = (int)$orgId;
        $r = Database::update('Papir', 'organization',
            array($field => $path),
            array('id' => $orgId)
        );
        return $r['ok'];
    }
}