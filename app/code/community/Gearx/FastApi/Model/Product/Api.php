<?php

/**
 * Created by PhpStorm.
 * User: zeke
 * Date: 3/1/16
 * Time: 12:34 PM
 */
class Gearx_FastApi_Model_Product_Api extends Mage_Catalog_Model_Api_Resource
{

//    $products = array( 
//        '5674-MD-BLUE' => array(
//            'special_price' => 39.95,
//            'price' => 49.95,
//            'qty' => 13,
//            'upc' => '543749281654',
//            'location' => 'XW4E2',
//        ),
//        '5674-SM-BLUE' => array(
//            'special_price' => 39.95,
//            'price' => 49.95,
//            'qty' => 8,
//            'upc' => '543749281653',
//            'location' => 'XW4E2',
//        ),
//        //etc
//    );
    public function update($products, $field_map_code = false)
    {
        $request = Mage::getSingleton('gxapi/request');
        try {
            $request->setFieldMap($field_map_code);
        } catch (Exception $e) {
            echo "Update Cancelled:  Field map \"$field_map_code\" not defined" . PHP_EOL;
            return;
        }

        foreach ($products as $sku => $fields) {
            try {
                $product = new Gearx_FastApi_Model_Product($sku);
                foreach ($fields as $code => $value) {
                    $mapped_code = $request->getMappedField($code);
                    if ($mapped_code) {
                        $product->updateField($mapped_code, $value);
                    }
                }
            } catch (Exception $e) {
                echo 'Skipping Product: ' . $e->getMessage() . PHP_EOL;
                //$this->_fault($e->getCode(), $e->getMessage());
            }
        }
        //Mage::getSingleton('gxapi/request')->reindexUpdatedProducts();
    }
    
    public function checkSkus($skus)
    {
        if (is_array($skus) && count($skus) > 0 ) {
            $database = Mage::getSingleton('gxapi/database');
            $bind_set = str_repeat('?,', count($skus) - 1) . '?';
            $cpe = $database->table('catalog_product_entity');
            $query = "SELECT sku, type_id FROM $cpe WHERE sku IN ($bind_set) ;";
            $results = $database->fetchAll($query, $skus);
            
            $new_array = array_fill_keys($skus, false);
            foreach ($results as $result) {
                $sku = $result['sku'];
                if (array_key_exists($sku, $new_array)) {
                    $new_array[$sku] = $result['type_id'];
                }
            }
            return $new_array;
        } else {
            $this->_fault(101, "Skus not specified properly");
            return "No skus specified";
        }

    }

}
