<?php

class Firebear_ImprovedImport_Model_Export_Entity_Order extends Mage_ImportExport_Model_Export_Entity_Abstract
{
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

        // create export file
        $writer->setHeaderCols(array_merge(
            $this->_permanentAttributes, array('entity_id', 'state')
        ));
        
        foreach ($collection as $order) {
            $row = array('entity_id' => 1, 'state' => 2);
            $addRow = array();

            $writeRow = array_merge($row, $addRow);
            $writer->writeRow($writeRow);
        }
    }
    
    /**
     * Clean up already loaded attribute collection.
     *
     * @param Mage_Eav_Model_Resource_Entity_Attribute_Collection $collection
     * @return Mage_Eav_Model_Resource_Entity_Attribute_Collection
     */
    public function filterAttributeCollection(Mage_Eav_Model_Resource_Entity_Attribute_Collection $collection)
    {
        //$collection->load();
        //foreach ($collection as $attribute) {
			//$collection->removeItemByKey($attribute->getId());
        //}        
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
