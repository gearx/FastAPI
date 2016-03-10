<?php

/**
 * Created by PhpStorm.
 * User: zeke
 * Date: 2/26/16
 * Time: 4:41 PM
 */
class Gearx_FastApi_Model_Product
{

    protected static $db_read;
    protected static $db_write;

    // Array of cached attribute info in the following form
    // $attribute_cache = array(
    //     'price' => array( 'id' => 60,  'type' => 'price' ),
    //     'upc'   => array( 'id' => 109, 'type' => 'varchar' ),
    //     'color' => array(
    //          'id' => 492,
    //          'type' => 'select',
    //          'option_ids' => array(
    //              'purple' => 7,
    //              'green'  => 11,
    //          ),
    //      ),
    // );
    protected static $attribute_cache = array();
    protected static $updated_products = array();

    protected $sku;
    protected $entity_id;
    protected $has_parents;
    protected $parent_ids;



    public function __construct($sku)
    {
        if (!isset(self::$db_read)) {
            $resource = Mage::getSingleton('core/resource');
            self::$db_read  = $resource->getConnection('core/read');
            self::$db_write = $resource->getConnection('core/write');
        }
        $this->sku = $sku;
        $this->loadEntityID($sku);
        
        if (is_null($this->entity_id)) {
            throw new Exception("SKU $sku not found", 101);
        } else {
            self::$updated_products[] = $this->entity_id;
        }
    }

    /**
     * Get full table name including possible prefix
     * @param $table_name
     * @return string
     */
    protected function table($table_name)
    {
        return Mage::getSingleton('core/resource')->getTableName($table_name);
    }

    /**
     * Load entity_id from database
     * @param $sku
     */
    protected function loadEntityID($sku)
    {
        $cpe = $this->table('catalog_product_entity');
        $query = "SELECT entity_id FROM $cpe WHERE sku = :sku ;";
        $binds = array('sku' => $sku);
        $this->entity_id = self::$db_read->query($query, $binds)->fetchObject()->entity_id;
    }

    protected function getAttributeId($code)
    {
        return $this->getAttributeData($code, 'id');
    }
    
    protected function getAttributeData($code, $key)
    {
        if (!array_key_exists($code, self::$attribute_cache)) {
            $this->cacheAttributeData($code);
        }
        if (self::$attribute_cache[$code] === false) {
            return false;
        } else {
            return self::$attribute_cache[$code][$key];
        }
    }

    /**
     * Load attribute id and type from the database
     * then cache the values for other products to use
     * @param $code   string   attribute_code
     * @throws Exception
     */
    protected function cacheAttributeData($code)
    {
        $table = $this->table('eav_attribute');
        $binds = array('attribute_code' => $code);
        $query = "SELECT attribute_id, frontend_input, backend_type FROM $table WHERE attribute_code = :attribute_code";
        $result = self::$db_read->query($query, $binds)->fetch();

        if (is_array($result)) {
            $data['id']   = $result['attribute_id'];
            $data['type'] = $result['frontend_input'];
            $data['backend'] = $result['backend_type'];
        } else {
            $data = false;
        }
        self::$attribute_cache[$code] = $data;
    }

    /**
     * Choose appropriate update action based on attribute type
     * @param $code   string   attribute code
     * @param $value  mixed    attribute value
     */
    public function updateAttribute($code, $value)
    {
        try {
            $type = $this->getAttributeData($code, 'type');
            switch ($type) {
                case 'date':
                    $this->updateBackendDatetime($code, $value);
                    break;
                case 'price':
                case 'weight':
                    $this->updateBackendDecimal($code, $value);
                    break;
                case 'select':
                    $this->updateFrontendSelect($code, $value);
                    break;
                case 'text':
                case 'textarea':
                    $this->updateFrontendText($code, $value);
                    break;
                case false;
                    throw new Exception("Attribute Code Not found", 102);
                default:
                    throw new Exception("Attribute Type $type not supported", 103);
            }
        } catch (Exception $e) {
            echo "SKU $this->sku - skipping attribute $code:  " . $e->getMessage() . PHP_EOL;
        }
        

    }

    /**
     * Update value for attribute with frontend type of select
     * @param $code   string
     * @param $value  string
     * @throws Exception
     */
    protected function updateFrontendSelect($code, $value)
    {
        $backend = $this->getAttributeData($code, 'backend');
        if ( $backend != 'int') {
            throw new Exception("backend_type $backend not supported for fronted_input select");
        }
        $attribute_id = $this->getAttributeId($code);
        $option_id = $this->getAttributeOptionId($code, $value);
        $this->updateBackendInt($attribute_id, $option_id);
    }
    
    protected function updateFrontendText($code, $value)
    {
        $backend = $this->getAttributeData($code, 'backend');
        if ($backend == 'varchar') {
            $this->updateBackendVarchar($code, $value);
        } elseif ($backend == 'text') {
            $this->updateBackendText($code, $value);
        } else {
            throw new Exception("backend_type $backend not supported for fronted_input text/textarea");
        }
    }
    

    /**
     * Get select attribute option id corresponding to give attribute code and value
     * @param $code    string   attribute code
     * @param $value   string  
     * @return integer
     * @throws Exception
     */
    protected function getAttributeOptionId($code, $value)
    {
        if ($this->getAttributeData($code, 'type') != 'select') {
            return false;
        }
        if (!array_key_exists($code, self::$attribute_cache[$code]['option_ids'][$value])) {
            $this->cacheAttributeOptionId($code, $value);
        }
        $option_id = self::$attribute_cache[$code]['option_ids'][$value];
        if ($option_id === false) {
            throw new Exception("Option ID not found for \"$value\"", 102);
        }
        return $option_id;
    }

    /**
     * Load attribute option id and from the database
     * then cache it for other products to use
     * @param $code    string   attribute code
     * @param $value   string   attribute value
     */
    protected function cacheAttributeOptionId($code, $value)
    {
        $eao =  $this->table('eav_attribute_option');
        $eaov = $this->table('eav_attribute_option_value');
        $binds = array(
            'att_id' => $this->getAttributeId($code),
            'att_value' => $value,
        );
        $query = "SELECT $eao.option_id FROM $eao JOIN $eaov ON $eaov.option_id = $eao.option_id 
                  WHERE $eaov.store_id = 0 AND $eao.attribute_id = :att_id AND value = :att_value";
        $result = self::$db_read->query($query, $binds)->fetch();
        
        if (is_array($result)) {
            self::$attribute_cache[$code]['option_ids'][$value] = $result['option_id'];
        } else {
            self::$attribute_cache[$code]['option_ids'][$value] = false;
        }
    }


    /**
     * Update value for attribute with backend type of datetime
     * @param $code   string
     * @param $value  decimal
     */
    protected function updateBackendDatetime($code, $value)
    {
        $table  = $this->table('catalog_product_entity_datetime');
        $binds = array(
            'ent_id' => $this->entity_id,
            'att_id' => $this->getAttributeId($code),
            'att_value' => $value,
        );
        $query = "INSERT INTO $table 
            SET entity_type_id = 4, attribute_id = :att_id, store_id = 0, entity_id = :ent_id, value = :att_value
            ON DUPLICATE KEY UPDATE value = :att_value";
        self::$db_write->query($query, $binds);
    }
    
    /**
     * Update value for attribute with backend type of decimal
     * @param $code   string
     * @param $value  decimal
     */
    protected function updateBackendDecimal($code, $value)
    {
        $table  = $this->table('catalog_product_entity_decimal');
        $binds = array(
            'ent_id' => $this->entity_id,
            'att_id' => $this->getAttributeId($code),
            'att_value' => $value,
        );
        $query = "INSERT INTO $table 
            SET entity_type_id = 4, attribute_id = :att_id, store_id = 0, entity_id = :ent_id, value = :att_value
            ON DUPLICATE KEY UPDATE value = :att_value";
        self::$db_write->query($query, $binds);
    }

    /**
     * Update value for attribute with backend type of integer
     * @param $attribute_id   integer
     * @param $value      integer
     */
    protected function updateBackendInt($attribute_id, $value)
    {
        $table  = $this->table('catalog_product_entity_int');
        $binds = array(
            'ent_id' => $this->entity_id,
            'att_id' => $attribute_id,
            'att_value' => $value,
        );
        $query = "INSERT INTO $table 
            SET entity_type_id = 4, attribute_id = :att_id, store_id = 0, entity_id = :ent_id, value = :att_value
            ON DUPLICATE KEY UPDATE value = :att_value";
        self::$db_write->query($query, $binds);
    }

    /**
     * Update value for attribute with backend type of text
     * @param $code   string
     * @param $value  string
     */
    protected function updateBackendText($code, $value)
    {
        $table  = $this->table('catalog_product_entity_text');
        $binds = array(
            'ent_id' => $this->entity_id,
            'att_id' => $this->getAttributeId($code),
            'att_value' => $value,
        );
        $query = "INSERT INTO $table 
            SET entity_type_id = 4, attribute_id = :att_id, store_id = 0, entity_id = :ent_id, value = :att_value 
            ON DUPLICATE KEY UPDATE value = :att_value";
        self::$db_write->query($query, $binds);
    }
    
    
    /**
     * Update value for attribute with backend type of varchar
     * @param $code   string
     * @param $value  string
     */
    protected function updateBackendVarchar($code, $value)
    {
        $table  = $this->table('catalog_product_entity_varchar');
        $binds = array(
            'ent_id' => $this->entity_id,
            'att_id' => $this->getAttributeId($code),
            'att_value' => $value,
        );
        $query = "INSERT INTO $table 
            SET entity_type_id = 4, attribute_id = :att_id, store_id = 0, entity_id = :ent_id, value = :att_value 
            ON DUPLICATE KEY UPDATE value = :att_value";
        self::$db_write->query($query, $binds);
    }
    
    protected function updateAttributeBackend($table, $attribute_id, $value)
    {
        $binds = array(
            'ent_id' => $this->entity_id,
            'att_id' => $attribute_id,
            'att_value' => $value,
        );
        $query = "INSERT INTO $table 
            SET entity_type_id = 4, attribute_id = :att_id, store_id = 0, entity_id = :ent_id, value = :att_value
            ON DUPLICATE KEY UPDATE value = :att_value";
        self::$db_write->query($query, $binds);
    }
    
    
    /**
     * Update qty and stock status
     */
    public function updateStock($qty) 
    {
        $csi  = $this->table('cataloginventory_stock_item');
        $css  = $this->table('cataloginventory_stock_status');
        $cssi = $this->table('cataloginventory_stock_status_idx');
        $binds = array(
            'id' => $this->entity_id,
            'qty'       => $qty,
            'stat' => ($qty > 0)? 1: 0,
        );
        $queries = array (
            "UPDATE $csi  SET qty = :qty, is_in_stock  = :stat WHERE product_id = :id; ",
            "UPDATE $css  SET qty = :qty, stock_status = :stat WHERE product_id = :id; ",
            "UPDATE $cssi SET qty = :qty, stock_status = :stat WHERE product_id = :id; ",
        );
        foreach ($queries as $query) {
            self::$db_write->query($query, $binds);
        }
        
        if ($this->hasParents()) {
            $this->updateParentStockStatus();
        }
    }

    /**
     * Update stock status of a parent item based on total qty of it's children
     */
    protected function updateParentStockStatus()
    {
        $csi  = $this->table('cataloginventory_stock_item');
        $css  = $this->table('cataloginventory_stock_status');
        $cssi = $this->table('cataloginventory_stock_status_idx');

        foreach ($this->getParentIds() as $parent_id) {
            $qty = $this->getParentQty($parent_id);
            $binds = array(
                'id' => $parent_id,
                'stat' => ($qty > 0)? 1: 0,
            );
            $queries = array(
                "UPDATE $csi  SET is_in_stock  = :stat WHERE product_id = :id; ",
                "UPDATE $css  SET stock_status = :stat WHERE product_id = :id; ",
                "UPDATE $cssi SET stock_status = :stat WHERE product_id = :id; ",
            );
            foreach ($queries as $query) {
                self::$db_write->query($query, $binds);
            }
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
        $table = $this->table('catalog_product_super_link');
        $query = "SELECT parent_id from $table where product_id = :entity_id;";
        $binds = array('entity_id' => $this->entity_id);
        
        $results = self::$db_read->query($query, $binds)->fetchAll();
        
        if (is_null($results[0])) {
            $this->has_parents = false;
            $this->parent_ids = false;
        } else {
            $this->has_parents = true;
            $this->parent_ids = $results;
        }
    }

    /**
     * Get the total stock quantity of a given parent's child products
     * @param $parent_id
     * @return mixed
     */
    protected function getParentQty($parent_id)
    {
        $ciss = $this->table('cataloginventory_stock_status');
        $cpsl = $this->table('catalog_product_super_link');
        $binds = array('parent_id' => $parent_id);
        
        $query = "SELECT $cpsl.parent_id, round(sum($ciss.qty)) as total
                  FROM $ciss LEFT JOIN $cpsl ON ($cpsl.product_id = $ciss.product_id)
                  WHERE $cpsl.parent_id = :parent_id GROUP BY $cpsl.parent_id";
        
        return self::$db_read->query($query, $binds)->fetch();
    }


    /**
     * datetime
     */

    
    /**
     * text
     */

    public static function reindexUpdated()
    {
        if (Mage::helper('core')->isModuleEnabled('Enterprise_Index')) {
            // Enterprise will automatically reindex updated products on cron 
            // so no action is necessary unless immediate reindexing is required
            // Mage::getSingleton('enterprise_index/observer')->refreshIndex(Mage::getModel('cron/schedule'));
        } else {
            $entity_ids = self::$updated_products;
            /*
             * Generate a fake mass update event that we pass to our indexers.
             */
            $event = Mage::getModel('index/event');
            $event->setNewData(array(
                'reindex_price_product_ids' => &$entity_ids, // for product_indexer_price
                'reindex_stock_product_ids' => &$entity_ids, // for indexer_stock
                'product_ids'               => &$entity_ids, // for category_indexer_product
                'reindex_eav_product_ids'   => &$entity_ids  // for product_indexer_eav
            ));
            // Index our product entities.
            Mage::getResourceSingleton('cataloginventory/indexer_stock')->catalogProductMassAction($event);
            Mage::getResourceSingleton('catalog/product_indexer_price')->catalogProductMassAction($event);
            Mage::getResourceSingleton('catalog/category_indexer_product')->catalogProductMassAction($event);
            Mage::getResourceSingleton('catalog/product_indexer_eav')->catalogProductMassAction($event);
            Mage::getResourceSingleton('catalogsearch/fulltext')->rebuildIndex(null, $entity_ids);

            if (Mage::helper('catalog/product_flat')->isEnabled()) {
                Mage::getSingleton('catalog/product_flat_indexer')->saveProduct($entity_ids);
            }
        }
    }
    
}