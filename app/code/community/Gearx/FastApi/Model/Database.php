<?php
/**
 * Created by PhpStorm.
 * User: zeke
 * Date: 3/11/16
 * Time: 3:34 PM
 */

class Gearx_FastApi_Model_Database
{
    protected $read;
    protected $write;

    public function __construct()
    {
        $this->read = Mage::getSingleton('core/resource')->getConnection('core/read');
        $this->write = Mage::getSingleton('core/resource')->getConnection('core/write');
    }

    /**
     * Get full table name including possible prefix
     * @param $table_name
     * @return string
     */
    public function table($table_name)
    {
        return Mage::getSingleton('core/resource')->getTableName($table_name);
    }

    /**
     * Fetch the value of the first field of the first record returned from a query
     * @param $query
     * @param $binds
     * @return string
     */
    public function fetchValue($query, $binds)
    {
        $value = $this->read->query($query, $binds)->fetch();
        return $value[0];
    }

    /**
     * Fetch the first record returned from a query
     * @param $query
     * @param $binds
     * @return array
     */
    public function fetchRecord($query, $binds)
    {
        $record = $this->read->query($query, $binds)->fetch();
        return $record;
    }

    /**
     * Fetch all records returned from a query
     * @param $query
     * @param $binds
     * @return array
     */
    public function fetchAll($query, $binds)
    {
        $records = $this->read->query($query, $binds)->fetchAll();
        return $records;
    }

    /**
     * Execute INSERT or UPDATE queries
     * @param $query
     * @param $binds
     */
    public function write($query, $binds)
    {
        $this->write->query($query, $binds);
    }
}
