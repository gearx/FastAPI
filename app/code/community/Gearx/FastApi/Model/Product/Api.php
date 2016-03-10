<?php

/**
 * Created by PhpStorm.
 * User: zeke
 * Date: 3/1/16
 * Time: 12:34 PM
 */
class Gearx_FastApi_Model_Product_Api extends Mage_Catalog_Model_Api_Resource
{

//    $sku = '5674-MD-BLUE';
//    $attributes = array(
//        'special_price' => 39.95,
//        'price' => 49.95,
//        'qty' => 13,
//        'upc' => '543749281654',
//        'location' => 'XW4E2',
//    );
    public function update($sku, $product_data)
    {
        $this->_update($sku, $product_data);
        //Gearx_FastApi_Model_Product::reindexUpdated();
    }

    protected function _update($sku, $product_data)
    {
        
        try {
            $product = new Gearx_FastApi_Model_Product($sku);
            foreach ($product_data as $code => $value) {
                if($code == 'qty') {
                    $product->updateStock($value);
                } else {
                    $product->updateAttribute($code, $value);
                }
            }
            //return "$sku updated";
        } catch (Exception $e) {
            //return 'Skipping Product: ' . $e->getMessage() . PHP_EOL;
            $this->_fault($e->getCode(), $e->getMessage());
        }
        
    }

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
    public function bulkUpdate($products) 
    {
        
        foreach ($products as $sku => $attributes) {
            //$response[$sku] = $attributes;
            $this->_update($sku, $attributes);
        }
        //Gearx_FastApi_Model_Product::reindexUpdated();
        //return $response;
    }

}
