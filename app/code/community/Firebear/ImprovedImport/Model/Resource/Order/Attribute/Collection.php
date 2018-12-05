<?php

class Firebear_ImprovedImport_Model_Resource_Order_Attribute_Collection
    extends Mage_Eav_Model_Resource_Entity_Attribute_Collection
{
    /**
     * Collection constructor
     *
     * @param Mage_Core_Model_Resource_Db_Abstract $resource
     */
    public function __construct($resource = null)
    {
        $this->_construct();
        $this->_resource = $resource;
        $this->setConnection($this->getResource()->getReadConnection());
    }
    
    /**
     * Load data
     *
     * @param   bool $printQuery
     * @param   bool $logQuery
     *
     * @return  Varien_Data_Collection_Db
     */
    public function load($printQuery = false, $logQuery = false)
    {
        if ($this->isLoaded()) {
            return $this;
        }
		foreach ($this->getData() as $row) {
			$item = $this->getNewEmptyItem();
			$item->addData($row);
			$this->addItem($item);
			//print_r(get_class($item));
		}
        
        $this->_setIsLoaded();
        
        //echo '<pre>';
        //print_r($this->getData());
        //echo '</pre>';
        //exit;
        
        return $this;
    } 
    
    public function getData()
    {
        if ($this->_data === null) {
			$this->_data = array(
				1 => array(
					'attribute_id' => 1,
					'entity_type_id' => 5,
					'attribute_code' => 'state',
					'frontend_label' => 'State',
					//'backend_model',
					'backend_type' => 'static',
					'frontend_input' => 'text',
					//'source_model',			
					)
			); 
        }
        return $this->_data;
    }
    
    /**
     * Reset loaded for collection data array
     *
     * @return Varien_Data_Collection_Db
     */
    public function resetData()
    {
        $this->_data = array();
        return $this;
    }
    
    /**
     * Get Zend_Db_Select instance and applies fields to select if needed
     *
     * @return Varien_Db_Select
     */
    public function getSelect()
    {
        return null;
    }
    
    /**
     * Get collection size
     *
     * @return int
     */
    public function getSize()
    {
        return count($this->_data);
    }
    
    /**
     * Get SQL for get record count
     *
     * @return Varien_Db_Select
     */
    public function getSelectCountSql()
    {
        return null;
    } 
    /**
     * Fetch collection data
     *
     * @param   Zend_Db_Select $select
     * @return  array
     */
    protected function _fetchAll($select)
    {
        $data = array();
        return $data;
    }   
}
