<?php
/**
 * Created by PhpStorm.
 * User: zeke
 * Date: 3/11/16
 * Time: 5:24 PM
 */

class Gearx_FastApi_Model_Attribute
{
    protected $database;
    
    protected $id;
    protected $code;
    protected $type;
    protected $backend_type;
    
    protected $supported;
    protected $error_message;
    
    protected $options = array();
    
    /**
     * Gearx_FastApi_Model_Attribute constructor.
     * Sets the code, id, type, and backend_type if attribute is found
     * otherwise sets supported to false
     * @param $code
     */
    public function __construct($code)
    {
        $this->code = $code;
        $this->database = Mage::getSingleton('gxapi/database');
        $entTypeTable = $this->database->table('eav_entity_type');
        $table = $this->database->table('eav_attribute');
        $binds = array('attribute_code' => $code, 'entity_type_code' => 'catalog_product');
        $query = "SELECT $table.attribute_id, $table.frontend_input, $table.backend_type FROM $table LEFT JOIN $entTypeTable ON $entTypeTable.entity_type_id = $table.entity_type_id WHERE $table.attribute_code = :attribute_code AND $entTypeTable.entity_type_code = :entity_type_code";
        $result = $this->database->fetchRecord($query, $binds);

        if (is_null($result['attribute_id'])) {
            $this->supported = false;
            $this->error_message = "Attribute code does not exist";
        } else {
            $this->id    = $result['attribute_id'];
            $this->type  = $result['frontend_input'];
            $this->backend_type  = $result['backend_type'];
        }
    }
    
    /**
     * @return bool
     */
    public function isSupported()
    {
        if (!isset($this->supported)) {
            $this->checkForSupport();
        }
        return $this->supported;
    }

    /**
     * Check if frontend/backend type combination is supported
     * @return bool
     * @throws Exception
     */
    protected function checkForSupport()
    {
        // supported data type combinations
        // format:  'frontend_input' => array('backend_type', 'backend_type)
        $supported_types = array(
            'date'     => array('datetime'),
            'price'    => array('decimal'),
            'weight'   => array('decimal'),
            'select'   => array('int'),
            'text'     => array('varchar', 'text'),
            'textarea' => array('varchar', 'text'),
        );
        $type = $this->getType();
        $backend = $this->getBackendType();

        if (array_key_exists($type, $supported_types)) {
            $supported_backends = $supported_types[$type];
            if (in_array($backend, $supported_backends)) {
                $this->supported = true;
            } else {
                $this->supported = false;
                $this->error_message = "backend_type $backend not supported for attribute type $type";
            }
        } else {
            $this->supported = false;
            $this->error_message = "Attribute type $type not supported";
        }
    }
    
    public function getErrorMessage()
    {
        return $this->error_message;
    }

    public function getId()
    {
        return $this->id;
    }
    
    public function getType()
    {
        return $this->type;
    }

    public function getBackendType()
    {
        return $this->backend_type;
    }

    /**
     * Assign appropriate backend value, then write it to the database for the given product_id
     * catalog_product_entity_datatype tables have the same field names
     * @param $product_id
     * @param $value
     * @throws Exception
     */
    public function updateValue($product_id, $value)
    {
        switch ($this->getType()) {
            case 'price':
            case 'weight':
                $backend_value = $this->validateNumber($value);
                break;
            case 'date':
                $backend_value = $this->validateDate($value);
                break;
            case 'select':
                if ($this->code === 'tax_class_id') {
                    $backend_value = $this->getTaxClassId($value);
                } else {
                    $backend_value = $this->getOptionId($value);
                }
                break;
            default:
                $backend_value = $value;
        }
        $table = $this->database->table('catalog_product_entity_' . $this->getBackendType());
        $binds = array(
            'ent_id' => $product_id,
            'att_id' => $this->getId(),
            'att_value' => $backend_value,
        );
        $query = "INSERT INTO $table 
            SET entity_type_id = 4, attribute_id = :att_id, store_id = 0, entity_id = :ent_id, value = :att_value
            ON DUPLICATE KEY UPDATE value = :att_value";
        $this->database->write($query, $binds);
    }

    /**
     * Ensure positive numeric value
     * @param $value
     * @return number      set to 0 if value is negative
     * @throws Exception   if value is non-numeric
     */
    protected function validateNumber($value)
    {
        if (is_numeric($value)) {
            return ($value > 0) ? $value : 0;
        } elseif ($value === '' || is_null($value)) {
            return null;
        } else {
            throw new Exception("Numeric attribute cannot be be set to non-numeric value \"$value\"");
        }
    }

    /**
     * No validation currently other than setting blank to NULL
     * @TODO add actual date string validation
     * @param $value
     * @return null
     */
    protected function validateDate($value)
    {
        if ($value === '' || is_null($value)) {
            return null;
        } else {
            return $value;
        }
    }

    /**
     * Validates tax class name or tax class id, and then returns tax class id
     * @param $value  string|int
     * @return int
     * @throws Exception
     */
    protected function getTaxClassId($value)
    {
        if (count($this->options) == 0) {
            $this->loadTaxClasses();
        }
        if ($value === '' || is_null($value))  {
            return 0;
        }
        if (array_key_exists($value, $this->options)) {
            return $this->options[$value];
        } else {
            throw new Exception("tax_class_id \"$value\" not found");
        }
    }

    /**
     * Load tax classes from the database
     */
    protected function loadTaxClasses()
    {
        $this->options['None'] = 0;
        $this->options[0] = 'None';
        $table = $this->database->table('tax_class');
        $query = "SELECT class_id, class_name from $table where class_type = 'PRODUCT'";
        $results = $this->database->fetchAll($query);
        foreach ($results as $result) {
            $this->options[$result['class_name']] = $result['class_id'];
            $this->options[$result['class_id']]   = $result['class_id'];
        }
    }

    /**
     * Get select attribute option id corresponding to given value
     * @param $value   string   attribute value
     * @return integer
     * @throws Exception
     */
    protected function getOptionId($value)
    {
        if ($this->getType() != 'select') {
            throw new Exception("Can't get option_id for non-select attribute " . $this->code);
        }
        if ($value === '' || is_null($value)) {
            return null;
        }
        if (!array_key_exists($value, $this->options)) {
            $this->loadOptionId($value);
        }
        $option_id = $this->options[$value];
        if ($option_id === false) {
            throw new Exception("option_id not found for \"$value\"");
        }
        return $option_id;
    }

    /**
     * Load option id for a given value from the database
     * then cache it for other products to use
     * @param $value   string   attribute value
     */
    protected function loadOptionId($value)
    {
        $eao =  $this->database->table('eav_attribute_option');
        $eaov = $this->database->table('eav_attribute_option_value');
        $binds = array(
            'att_id' => $this->getId(),
            'att_value' => $value,
        );
        $query = "SELECT $eao.option_id FROM $eao JOIN $eaov ON $eaov.option_id = $eao.option_id 
                  WHERE $eaov.store_id = 0 AND $eao.attribute_id = :att_id AND value = :att_value";
        $result = $this->database->fetchValue($query, $binds);

        if(is_null($result)) {
            $this->options[$value] = false;
        } else {
            $this->options[$value] = $result;
        }
    }

}
