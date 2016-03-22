Gearx Fast API for Magento
==========================

This module aims to provide fast API methods for keeping basic product data in sync
between Magento and an inventory control or ERP system.  The same SOAP or XML-RPC
endpoints as the default Magento API are used, but the methods themselves handle large
numbers of records much more efficiently. If you are unfamiliar with the Magento API, have
a look at the documentation first:

* **[Magento API Documentation](http://devdocs.magento.com/guides/m1x/api/soap/introduction.html)**

### *Warning & Disclaimer*
**_While these methods do some basic validation, not all bases are covered.  I'm aiming to
make them fast, not necessarily foolproof.  Use at your own risk, and test everything in a
staging environment first._**


## Method: gxproduct.update

An alternative to the default product.update method that accepts multiple products instead
 of just one.  

##### Example PHP Usage:

```php
<?php
$client = new SoapClient('http://example.com/api/soap/?wsdl=1');
$session  = $client->login(api_user, api_key);

$product_data = array( ... );
$response = $client->call($session, 'gxproduct.update', [ $product_data ];

$field_map_code = 'example';
$response = $client->call($session, 'gxproduct.update', [ $product_data, $fieldmap_code ];
```


#### Argument1: product_data - array
Associative array with skus as keys and arrays of fields to update as values.  Example:

```php
<?php
$product_data = [
    'sku1' => [
        'attribute_code_1' => 'Attribute Value 1',
        'attribute_code_2' => 'Attribute Value 2'
    ],
    'sku2' => [
        'attribute_code_1' => 'Attribute Value 1',
        'attribute_code_2' => 'Attribute Value 2'
    ]
];
$product_data = [
    '55A47-001' => [
        'price' => 99.95,
        'qty' => 14
    ],
    '55A47-002' => [
        'price' => 99.95,
        'qty' => 19
    ]
];
```


#### Argument2: fieldmap_code - string (optional)
This argument may be optionally included to specify a field mapping.  To define a field
mapping, make a copy of `app/code/community/Gearx/FastApi/etc/fieldmap.xml.example` and
rename it `fieldmap.xml`.  Then replace the `<example>` node with the field map code you'd
like to use and define the mapped fields as sub-nodes like this:

```xml
<fieldmap_code>
    <external_field_1> magento_field_1 </external_field_1>
    <external_field_2> magento_field_2 </external_field_2>
</fieldmap_code>
```
    
To use a field mapping with the update method, pass it's code as the second argument.

#### Response - array
The update method returns an array containing the following: 

| Key        | Value                        | Condition          |
|------------|------------------------------|--------------------|
| status     | (string) Status message      | always returned    |
| *fieldmap* | (array) of the fieldmap used | if fieldmap used   |
| *errors*   | (array) of error messages    | if errors occurred |




## Method: gxproduct.checkSkus

This method takes an array of skus and returns an associative array with the skus as keys
and their product types (simple, configurable, grouped, etc) as values, or a FALSE value
for skus that don't exist in Magento.

#### Argument1: skus - array

```php
<?php
$skus = [ 'sku1', 'sku2', 'sku3', 'sku4' ];
```

#### Response - array

```php
<?php
$skus = [
    'sku1' => 'simple',
    'sku2' => 'simple',
    'sku3' =>  false, 
    'sku4' => 'configurable'
];
```



## Testing

To try things out, modify the tester.php file along with the included small csv files exported from 
a Magento demo store with sample data.


## Reindexing

By default the indexes will be rebuilt as part of the update api call.  This slows things down though.
If you don't need the updates to be immediately reflected on the front end and you are rebuilding indexes 
on a schedule or are using Magento Enterprise, then this is unnecessary.  There is a setting at
System > Configuration > Magento Core API > Gearx FastAPI which you can turn off to speed things up.
