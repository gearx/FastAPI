Gearx Fast API for Magento
==========================

This module aims to provide fast API methods for keeping products in sync
between Magento and an inventory control or ERP system.  The default Magento 
API methods are quite slow when dealing with large numbers of records. 

### [Magento API Documentation](http://devdocs.magento.com/guides/m1x/api/soap/introduction.html)

This module simply adds new methods to the existing API.


### Added Methods


| Method               | Arguments        | 
|----------------------|------------------|
| gxproduct.update     | SKU, ProductData | 
| gxproduct.bulkUpdate | ProductData      | 


#### Argument Examples
Intentionally in pseudo code.  See above for detailed Magento API documentation.

##### gxproduct.update

	SKU = 'sku value'
	ProductData = [
		'attribute_code_1': 'Attribute Value 1'
		'attribute_code_2': 'Attribute Value 2'
		// etc ...
	]
	
	SKU = '55A47-001'
	ProductData = [
		'name':  'Marmot Precip Jacket - Large - Blue'
		'price': 99.95
		'special_price': 79.95
		'qty': 14
	]
	

##### gxproduct.bulkUpdate

	ProductData = [
		'sku1': [
			'attribute_code_1': 'Attribute Value 1'
			'attribute_code_2': 'Attribute Value 2'
		]
		'sku2': [
			'attribute_code_1': 'Attribute Value 1'
			'attribute_code_2': 'Attribute Value 2'
		]
	]

	ProductData = [
		'55A47-001': [
			'price': 99.95
			'qty':   14
		]
		'55A47-002': [
			'price': 99.95
			'qty':   19
		]
	]

