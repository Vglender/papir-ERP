<?php

class GlobalSettingsRepository
{
    private $dbName = 'Papir';

    private $defaults = array(
        'id'                       => 1,
        'sale_markup_percent'      => 30.0,
        'wholesale_markup_percent' => 15.0,
        'dealer_markup_percent'    => 10.0,
        'discount_strategy_id'     => null,
        'quantity_strategy_id'     => null,
        'use_tiered_markup'        => 0,
        'updated_at'               => null,
        'tiers'                    => array(),
    );

    /**
     * Returns global settings row + tiers array.
     */
    public function get()
    {
        $result = Database::fetchRow($this->dbName,
            "SELECT * FROM `price_settings_global` WHERE id = 1 LIMIT 1"
        );

        $row = ($result['ok'] && !empty($result['row'])) ? $result['row'] : $this->defaults;
        $row['tiers'] = $this->getTiers();

        return $row;
    }

    /**
     * Returns tiers sorted by price_from ASC.
     */
    public function getTiers()
    {
        $result = Database::fetchAll($this->dbName,
            "SELECT * FROM `price_markup_tiers` ORDER BY price_from ASC"
        );

        return ($result['ok'] && !empty($result['rows'])) ? $result['rows'] : array();
    }

    /**
     * Replaces all tiers. $tiers = [['price_from' => X, 'markup_percent' => Y], ...]
     */
    public function saveTiers(array $tiers)
    {
        Database::query($this->dbName, "DELETE FROM `price_markup_tiers`");

        $sort = 0;
        foreach ($tiers as $tier) {
            $pf  = (float)$tier['price_from'];
            $pct = (float)$tier['markup_percent'];
            if ($pct <= 0) {
                continue;
            }
            Database::insert($this->dbName, 'price_markup_tiers', array(
                'price_from'      => $pf,
                'markup_percent'  => $pct,
                'sort_order'      => $sort++,
            ));
        }

        return array('ok' => true);
    }

    /**
     * Upsert id=1 row.
     */
    public function save(array $data)
    {
        $exists = Database::exists($this->dbName, 'price_settings_global', array('id' => 1));

        $data['updated_at'] = date('Y-m-d H:i:s');

        if ($exists['ok'] && $exists['exists']) {
            return Database::update($this->dbName, 'price_settings_global', $data, array('id' => 1));
        }

        $data['id'] = 1;
        return Database::insert($this->dbName, 'price_settings_global', $data);
    }
}
