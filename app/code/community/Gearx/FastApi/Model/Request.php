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
    protected $fieldmap;


    /**
     * If passed a param other than false, attempt to load the specified
     * field mapping from etc/fieldmap.xml
     * @param $fieldmap_code
     * @throws Exception
     */
    public function setFieldMap($fieldmap_code)
    {
        if ($fieldmap_code === false) {
            $this->fieldmap = false;
        } else {
            $fieldmaps = '';
            $filepath = Mage::getBaseDir('app') . '/code/community/Gearx/FastApi/etc/fieldmap.xml';
            if (file_exists($filepath)) {
                $xmlfile = file_get_contents($filepath);
                $fieldmaps = simplexml_load_string($xmlfile);
            }
            if (property_exists($fieldmaps, $fieldmap_code)) {
                $this->fieldmap = (array) $fieldmaps->{$fieldmap_code};
            } else {
                throw new Exception("Fieldmap $fieldmap_code not defined");
            }
        }
    }

    /**
     * Get mapped field name if a fieldmap is defined
     * otherwise pass fieldname through unchanged
     * @param $fieldname
     * @return string|false
     */
    public function getMappedField($fieldname)
    {
        if ($this->fieldmap == false) {
            return $fieldname;
        } else {
            if (array_key_exists($fieldname, $this->fieldmap)) {
                return $this->fieldmap[$fieldname];
            } else {
                return false;
            }
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