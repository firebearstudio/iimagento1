<?php

class Firebear_ImprovedImport_Model_Export_Entity_Order 
	extends Mage_ImportExport_Model_Export_Entity_Abstract
{
    /**
     * Permanent entity columns
     *
     * @var array
     */
    protected $_permanentAttributes = array(
		'increment_id',
		'store_id'
	);
	
    /**
     * Json entity columns
     *
     * @var array
     */
    protected $_jsonAttributes = array(
		'item:product_options',
		'item:weee_tax_applied',
		'invoice_item:weee_tax_applied',
		'creditmemo_item:weee_tax_applied',
		'gift_cards'
	);
	
    /**
     * Blob entity columns
     *
     * @var array
     */
    protected $_blobAttributes = array();
	
    /**
     * Order Status Labels
     *
     * @var array
     */
    protected $_status;
	
    /**
     * Prefix data
     *
     * @var array
     */    
    protected $_prefixData = array(
		'sales_flat_order_item' => 'item',
		'sales_flat_order_address' => 'address',
		'sales_flat_order_payment' => 'payment',
		'sales_payment_transaction' => 'transaction',
		'sales_flat_shipment' => 'shipment',
		'sales_flat_shipment_item' => 'shipment_item',
		'sales_flat_shipment_comment' => 'shipment_comment',
		'sales_flat_shipment_track' => 'shipment_track',
		'sales_flat_invoice' => 'invoice',
		'sales_flat_invoice_item' => 'invoice_item',
		'sales_flat_invoice_comment' => 'invoice_comment',
		'sales_flat_creditmemo' => 'creditmemo',		
		'sales_flat_creditmemo_item' => 'creditmemo_item',
		'sales_flat_creditmemo_comment' => 'creditmemo_comment',
		'sales_flat_order_status_history' => 'status_history',
		'sales_order_tax' => 'tax',
		'sales_order_tax_item' => 'tax_item',			
    );
	
    /**
     * Parent data
     *
     * @var array
     */    
    protected $_parentData = array(
		'sales_flat_order_item' => 'order_id',
		'sales_flat_order_address' => 'parent_id',
		'sales_flat_order_payment' => 'parent_id',
		'sales_payment_transaction' => 'order_id',
		'sales_flat_shipment' => 'order_id',
		'sales_flat_invoice' => 'order_id',
		'sales_flat_creditmemo' => 'order_id',		
		'sales_flat_order_status_history' => 'parent_id',
		'sales_order_tax' => 'order_id',		
    );
	
    /**
     * Child data
     *
     * @var array
     */    
    protected $_childData = array(
		'sales_flat_shipment' => array(
			'sales_flat_shipment_item',
			'sales_flat_shipment_comment',
			'sales_flat_shipment_track',		
		),
		'sales_flat_invoice' => array(
			'sales_flat_invoice_item',
			'sales_flat_invoice_comment',	
		),
		'sales_flat_creditmemo' => array(
			'sales_flat_creditmemo_item',
			'sales_flat_creditmemo_comment',		
		),
		'sales_order_tax' => array(
			'sales_order_tax_item',		
		)
    );
	
    /**
     * Export columns
     *
     * @var array
     */    
    protected $_columns;
	
    /**
     * Export process and return contents of temporary file
     *
     * @deprecated after ver 1.9.2.4 use $this->exportFile() instead
     *
     * @return string
     */
    public function export()
    {
        $this->_prepareExport();
        return $this->getWriter()->getContents();
    }

    /**
     * Export process and return temporary file through array
     *
     * @return array
     */
    public function exportFile()
    {
        $this->_prepareExport();        
        $writer = $this->getWriter();

        return array(
            'rows'  => $writer->getRowsCount(),
            'value' => $writer->getDestination()
        );
    }
    
    /**
     * Prepare data for export and write its to temporary file through writer.
     *
     * @return void
     */
    protected function _prepareExport()
    {
        $collection = Mage::getResourceModel('sales/order_collection');
        $writer = $this->getWriter();
        // create header columns
        $writer->setHeaderCols(array_merge(
            $this->_permanentAttributes, $this->getColumns()
        ));
        // create order data rows
        foreach ($collection as $order) {
			$count = array();
			$rows = array();
			
			$orderData = $order->toArray();
			$orderData['status_label'] = isset($orderData['status'])
				? $this->getStatusLabel($orderData['status'])
				: ''; 
				
			$row = array_merge($this->getColumns(), $orderData);
			unset($row['entity_id'], $row['store_name']);
			// add linked data
			foreach ($this->_parentData as $table => $fieldId) {
				$count[$table] = 0;
				list($count, $rows, $row) = $this->_prepareRow(
					$table, 
					$fieldId, 
					$order->getId(), 
					$count, 
					$rows, 
					$row
				);
			}
			$writer->writeRow($row);
			foreach ($rows as $row) {
				$writer->writeRow($row);
			}
        }
    }
    
    /**
     * Prepare rows for export
     *
     * @return array
     */
    protected function _prepareRow($table, $fieldId, $entityId, $count, $rows, $row)
    {
		$connection = $this->_connection;
		$prefix = $this->_prefixData[$table];
		// select linked data
		$select = $connection->select()->from(
			$this->getTableName($table)
		)->where($connection->quoteIdentifier($fieldId) . ' = ?', $entityId);
	
		foreach ($connection->fetchAll($select) as $data) {
			$addRow = array();
			// replace column names
			foreach ($data as $column => $value) {
				$columnName = $this->getColumnName($prefix, $column);
				$addRow[$columnName] = $this->getColumnValue($columnName, $value);
			}
			if (0 == $count[$table]) {
				// first row
				$row = array_merge($row, $addRow);
			} elseif (isset($rows[$count[$table]])) {
				// existing row
				$rows[$count[$table]] = array_merge($rows[$count[$table]], $addRow);
			} else {
				// new row
				$rows[$count[$table]] = array_merge($this->getColumns(), $addRow);
			}
			$count[$table]++;
			
			$linkId = 'parent_id';
			$entityId = 'entity_id';
			if ('sales_order_tax' == $table) {
				$linkId = $entityId = 'tax_id';
			}
	
			if (isset($this->_childData[$table]) && isset($data[$entityId])) {
				foreach ($this->_childData[$table] as $childTable) {
					$count[$childTable] = 0;	
					list($count, $rows, $row) = $this->_prepareRow(
						$childTable, 
						$linkId, 
						$data[$entityId], 
						$count, 
						$rows, 
						$row
					);				
				}
			}			
		}
		return array($count, $rows, $row);
    }
    
    /**
     * Retrieve columns array
     *
     * @return array
     */
    protected function getColumns()
    {
        if (null === $this->_columns) {
			$describe = $this->_connection->describeTable(
				$this->getTableName('sales_flat_order')
			);
			unset($describe['entity_id']);
			$this->_columns = array_keys($describe);
			$this->_columns[] = 'status_label';
			
			foreach ($this->_prefixData as $table => $prefix) {
				$describe = $this->_connection->describeTable(
					$this->getTableName($table)
				);
				foreach ($describe as $column => $info) {
					$columnName = $this->getColumnName($prefix, $column);
					$dataType = !empty($info['DATA_TYPE']) ? $info['DATA_TYPE'] : null;
					if (in_array($dataType, array('blob', 'mediumblob', 'tinyblob', 'longblob'))) {
						$this->_blobAttributes[] = $columnName;
					}					
					$this->_columns[] = $columnName;
				}
			}		
		}
		return $this->_columns;
    }
	
    /**
     * Retrieve order statuses
     *
     * @param string $status
     * @return string
     */
    protected function getStatusLabel($status)
    {
        if (null === $this->_status) {
            $this->_status = array();
            $collection = Mage::getResourceModel('sales/order_status_collection');
			foreach ($collection as $item) {
                $this->_status[$item->getStatus()] = $item->getLabel();
            }
        }
        return isset($this->_status[$status])
            ? $this->_status[$status]
            : '';
    }
	
    /**
     * Retrieve resource table name, validated by db adapter
     *
     * @param   string|array $modelEntity
     * @return  string
     */
    protected function getTableName($table)
	{
		return Mage::getSingleton('core/resource')->getTableName($table);
	}
	
    /**
     * Retrieve column name
     *
     * @param string $prefix
     * @param string $column	 
     * @return string
     */
    protected function getColumnName($prefix, $column)
    {
		return $prefix . ':' . $column;
    }
	
    /**
     * Retrieve column value
     *
     * @param string $columnName
     * @param string $value	 
     * @return string
     */
    protected function getColumnValue($columnName, $value)
    {
		// format serialize value
		if (in_array($columnName, $this->_jsonAttributes)) {
			$options = $value ? unserialize($value) : array();
			return (is_array($options) && 0 < count($options))
				? json_encode($options)
				: '';
		} 
		// format blob value
		if (in_array($columnName, $this->_blobAttributes) && $value) {
			return base64_encode($value);
		}
		return $value;
    }
  
    /**
     * Clean up already loaded attribute collection.
     *
     * @param Mage_Eav_Model_Resource_Entity_Attribute_Collection $collection
     * @return Mage_Eav_Model_Resource_Entity_Attribute_Collection
     */
    public function filterAttributeCollection(Mage_Eav_Model_Resource_Entity_Attribute_Collection $collection)
    {      
        return $collection;
    }

    /**
     * Entity attributes collection getter.
     *
     * @return Firebear_ImprovedImport_Model_Entity_Order_Attribute_Collection
     */
    public function getAttributeCollection()
    {
        return Mage::getResourceModel('improvedimport/order_attribute_collection');
    }

    /**
     * EAV entity type code getter.
     *
     * @return string
     */
    public function getEntityTypeCode()
    {
        return 'order';
    }
}
