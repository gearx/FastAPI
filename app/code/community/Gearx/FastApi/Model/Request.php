<?php
/**
 * Created by PhpStorm.
 * User: zeke
 * Date: 3/11/16
 * Time: 4:35 PM
 */

class Gearx_FastApi_Model_Request 
{
    protected $field_map_code;
    
    protected $attributes = array();
    protected $products = array();
    protected $field_maps = array(
        'test' => array(
            'line'  => false,
            'style' => false,
            'dept'  => 'posim_misc1',
            'collections' => false,
            'mfg'   => false,
            'website_ids' => false,
            'row'   => false,
            'misc4' => false,
            'misc5' => false,
            'column' => false,
            'notes_warranty' => false,
            'mfg_sku' => false,
            'brand' => 'manufacturer',
            'misc1' => 'posim_misc2',
            'misc3' => 'discount_type_posim',
        ),
    );

    public function setFieldMap($code)
    {
        if (array_key_exists($code, $this->field_maps)) {
            $this->field_map_code = $code;
        } else {
            $this->field_map_code = false;
        }
    }
    
    public function getFieldMap()
    {
        $code = $this->field_map_code;
        if (array_key_exists($code, $this->field_maps)) {
            return $this->field_maps[$code];
        } else {
            return false;
        }
    }
    
    /**
     * Get attribute for a given code
     * @param $code
     * @return Gearx_FastApi_Model_Attribute
     * @throws Exception
     */
    public function getAttribute($code)
    {
        if (!isset($this->attributes[$code])) {
            $this->attributes[$code] = new Gearx_FastApi_Model_Attribute($code);
        }
        /* @var $attribute Gearx_FastApi_Model_Attribute */
        $attribute = $this->attributes[$code];
        if ($attribute->isSupported()) {
            return $attribute;
        } else {
            throw new Exception($attribute->getErrorMessage());
        }
    }


    public function addProduct($product_id)
    {
        $this->products[] = $product_id;
    }
    
    public function getProducts()
    {
        $unique_ids = array_unique($this->products);
        $this->products = $unique_ids;
        return $this->products;
    }
    
    public function reindexUpdatedProducts()
    {
        if (Mage::helper('core')->isModuleEnabled('Enterprise_Index')) {
            // Enterprise will automatically reindex updated products on cron 
            // so no action is necessary unless immediate reindexing is required
            // Mage::getSingleton('enterprise_index/observer')->refreshIndex(Mage::getModel('cron/schedule'));
        } else {
            $entity_ids = $this->getProducts();
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