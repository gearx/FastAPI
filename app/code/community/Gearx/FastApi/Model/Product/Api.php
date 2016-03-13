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
    protected function update($products, $field_map_code = false)
    {
        $request = Mage::getSingleton('gxapi/request');
        $request->setFieldMap($field_map_code);

        foreach ($products as $sku => $fields) {
            try {
                $product = new Gearx_FastApi_Model_Product($sku);
                foreach ($fields as $code => $value) {
                    $product->updateField($code, $value);
                }
            } catch (Exception $e) {
                //return 'Skipping Product: ' . $e->getMessage() . PHP_EOL;
                $this->_fault($e->getCode(), $e->getMessage());
            }
        }
        //Mage::getSingleton('gxapi/request')->reindexUpdatedProducts();
    }

}
