<?php

/**
 * Created by PhpStorm.
 * User: zeke
 * Date: 2/26/16
 * Time: 4:41 PM
 */
class Gearx_FastApi_Model_Product
{
    protected $request;
    protected $database;

    protected $sku;
    protected $entity_id;
    protected $has_parents;
    protected $parent_ids;

    /**
     * Gearx_FastApi_Model_Product constructor.
     * Load entity_id from database and init object
     * @param $sku
     * @throws Exception   if sku not found
     */
    public function __construct($sku)
    {
        $this->request  = Mage::getSingleton('gxapi/request');
        $this->database = Mage::getSingleton('gxapi/database');

        $cpe = $this->database->table('catalog_product_entity');
        $query = "SELECT entity_id FROM $cpe WHERE sku = :sku ;";
        $binds = array('sku' => $sku);
        $entity_id = $this->database->fetchValue($query, $binds);
        
        if (is_null($entity_id)) {
            throw new Exception("SKU $sku not found", 101);
        } else {
            $this->sku = $sku;
            $this->entity_id = $entity_id;
            $this->request->addProduct($entity_id);
        }
    }

    /**
     * Choose appropriate update action based on field type
     * @param $code   string   field code
     * @param $value  mixed    field value
     */
    public function updateField($code, $value)
    {
        try {
            if ($code == 'qty') {
                $this->updateStock($value);
            } else {
                $attribute = $this->request->getAttribute($code);
                $attribute->updateValue($this->entity_id, $value);
            }
        } catch (Exception $e) {
            echo "SKU $this->sku - skipping field $code:  " . $e->getMessage() . PHP_EOL;
        }

    }

    /**
     * Update qty and stock status
     */
    public function updateStock($qty) 
    {
        $csi  = $this->database->table('cataloginventory_stock_item');
        $binds = array(
            'id' => $this->entity_id,
            'qty'       => $qty,
            'stat' => ($qty > 0)? 1: 0,
        );
        $query = "UPDATE $csi  SET qty = :qty, is_in_stock  = :stat WHERE product_id = :id; ";
        $this->database->write($query, $binds);
        
        if ($this->hasParents()) {
            $this->updateParentStockStatus();
        }
    }

    /**
     * Update stock status of a parent item based on total qty of it's children
     */
    protected function updateParentStockStatus()
    {
        $csi  = $this->database->table('cataloginventory_stock_item');
        foreach ($this->getParentIds() as $parent_id) {
            $qty = $this->getParentQty($parent_id);
            $binds = array(
                'id' => $parent_id,
                'stat' => ($qty > 0)? 1: 0,
            );
            $query = "UPDATE $csi  SET is_in_stock  = :stat WHERE product_id = :id; ";
            $this->database->write($query, $binds);
            
            // add parent id to the request for reindexing later
            $this->request->addProduct($parent_id);
        }
    }
    
    /**
     * @return boolean 
     */
    protected function hasParents()
    {
        if (!isset($this->has_parents))  $this->loadParentIds();
        return $this->has_parents;
    }

    /**
     * @return array|false
     */
    protected function getParentIds()
    {
        if (!isset($this->parent_ids))   $this->loadParentIds();
        return $this->parent_ids;
    }

    /**
     * Attempt to load parent ids from database
     * Set has_parents and parent_ids properties based on result
     * 
     * @return void
     */
    protected function loadParentIds()
    {
        $table = $this->database->table('catalog_product_super_link');
        $query = "SELECT parent_id from $table where product_id = :entity_id;";
        $binds = array('entity_id' => $this->entity_id);
        $results = $this->database->fetchAll($query, $binds);
        
        if (is_null($results[0])) {
            $this->has_parents = false;
            $this->parent_ids = false;
        } else {
            $this->has_parents = true;
            foreach ($results as $result) {
                $this->parent_ids[] = $result[0];
            }
        }
    }

    /**
     * Get the total stock quantity of a given parent's child products
     * @param $parent_id
     * @return number
     */
    protected function getParentQty($parent_id)
    {
        $ciss = $this->database->table('cataloginventory_stock_status');
        $cpsl = $this->database->table('catalog_product_super_link');
        $binds = array('parent_id' => $parent_id);
        
        $query = "SELECT sum($ciss.qty) as total
                  FROM $ciss LEFT JOIN $cpsl ON ($cpsl.product_id = $ciss.product_id)
                  WHERE $cpsl.parent_id = :parent_id";
        
        return $this->database->fetchValue($query, $binds);
    }

}