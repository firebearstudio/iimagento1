<?php
/**
 * Firebear Improved Import Module
 *
 * @category    Firebear
 * @package     Firebear_ImprovedImport
 * @copyright   Copyright (c) 2013 Firebear
 * @author      biotech (Hlupko Viktor)
 */

/**
 * Product Import Adapter
 *
 * @category    Firebear
 * @package     Firebear_ImpovedImport
 */
class Firebear_ImprovedImport_Model_Catalog_Convert_Adapter_Product extends Mage_Catalog_Model_Convert_Adapter_Product
{

    /**
     * Tier prices prepare begin
     */
    private $_group_list = null;
    private $_tier_price_fields = null;

    public function load() {
        // load the group list
        $this->_group_list = Mage::getResourceModel('customer/group_collection')->setRealGroupsFilter()->loadData()->toOptionArray();

        return parent::load();
    }

    /**
     * Tier prices prepare end
     */

    /**
     * Save product (import)
     *
     * @param array $importData
     * @throws Mage_Core_Exception
     * @return bool
     */
    public function saveRow(array $importData)
    {
        $product = $this->getProductModel()
            ->reset();

        if (empty($importData['store'])) {
            if (!is_null($this->getBatchParams('store'))) {
                $store = $this->getStoreById($this->getBatchParams('store'));
            } else {
                $message = Mage::helper('catalog')->__(
                    'Skipping import row, required field "%s" is not defined.',
                    'store'
                );
                Mage::throwException($message);
            }
        }
        else {
            $store = $this->getStoreByCode($importData['store']);
        }

        if ($store === false) {
            $message = Mage::helper('catalog')->__(
                'Skipping import row, store "%s" field does not exist.',
                $importData['store']
            );
            Mage::throwException($message);
        }

        if (empty($importData['sku'])) {
            $message = Mage::helper('catalog')->__('Skipping import row, required field "%s" is not defined.', 'sku');
            Mage::throwException($message);
        }
        $product->setStoreId($store->getId());
        $productId = $product->getIdBySku($importData['sku']);

        if ($productId) {
            $product->load($productId);
        }
        else {
            $productTypes = $this->getProductTypes();
            $productAttributeSets = $this->getProductAttributeSets();

            /**
             * Check product define type
             */
            if (empty($importData['type']) || !isset($productTypes[strtolower($importData['type'])])) {
                $value = isset($importData['type']) ? $importData['type'] : '';
                $message = Mage::helper('catalog')->__(
                    'Skip import row, is not valid value "%s" for field "%s"',
                    $value,
                    'type'
                );
                Mage::throwException($message);
            }
            $product->setTypeId($productTypes[strtolower($importData['type'])]);
            /**
             * Check product define attribute set
             */
            if (empty($importData['attribute_set']) || !isset($productAttributeSets[$importData['attribute_set']])) {
                $value = isset($importData['attribute_set']) ? $importData['attribute_set'] : '';
                $message = Mage::helper('catalog')->__(
                    'Skip import row, the value "%s" is invalid for field "%s"',
                    $value,
                    'attribute_set'
                );
                Mage::throwException($message);
            }
            $product->setAttributeSetId($productAttributeSets[$importData['attribute_set']]);

            foreach ($this->_requiredFields as $field) {
                $attribute = $this->getAttribute($field);
                if (!isset($importData[$field]) && $attribute && $attribute->getIsRequired()) {
                    $message = Mage::helper('catalog')->__(
                        'Skipping import row, required field "%s" for new products is not defined.',
                        $field
                    );
                    Mage::throwException($message);
                }
            }
        }

        $this->setProductTypeInstance($product);

        if (isset($importData['category_ids'])) {
            $product->setCategoryIds($importData['category_ids']);
        }

        foreach ($this->_ignoreFields as $field) {
            if (isset($importData[$field])) {
                unset($importData[$field]);
            }
        }

        if ($store->getId() != 0) {
            $websiteIds = $product->getWebsiteIds();
            if (!is_array($websiteIds)) {
                $websiteIds = array();
            }
            if (!in_array($store->getWebsiteId(), $websiteIds)) {
                $websiteIds[] = $store->getWebsiteId();
            }
            $product->setWebsiteIds($websiteIds);
        }

        if (isset($importData['websites'])) {
            $websiteIds = $product->getWebsiteIds();
            if (!is_array($websiteIds) || !$store->getId()) {
                $websiteIds = array();
            }
            $websiteCodes = explode(',', $importData['websites']);
            foreach ($websiteCodes as $websiteCode) {
                try {
                    $website = Mage::app()->getWebsite(trim($websiteCode));
                    if (!in_array($website->getId(), $websiteIds)) {
                        $websiteIds[] = $website->getId();
                    }
                }
                catch (Exception $e) {}
            }
            $product->setWebsiteIds($websiteIds);
            unset($websiteIds);
        }

		$custom_options = array();

        foreach ($importData as $field => $value) {

            /**
             * Begin product custom options import code
             */
            if(Mage::getStoreConfig('improvedimport/customoptions/custom_options_enabled')){
                

                if(strpos($field,':')!==FALSE && strlen($value)) {
                    $values=explode('|',$value);

                    if(count($values)>0) {
                        @list($title,$type,$is_required,$sort_order) = explode(':',$field);
                        $title = ucfirst(str_replace('_',' ',$title));
                        $custom_options[] = array(
                            'is_delete'=>0,
                            'title'=>$title,
                            'previous_group'=>'',
                            'previous_type'=>'',
                            'type'=>$type,
                            'is_require'=>$is_required,
                            'sort_order'=>$sort_order,
                            'values'=>array()
                        );
                        foreach($values as $v) {
                            $parts = explode(':',$v);
                            $title = $parts[0];
                            if(count($parts)>1) {
                                $price_type = $parts[1];
                            } else {
                                $price_type = 'fixed';
                            }
                            if(count($parts)>2) {
                                $price = $parts[2];
                            } else {
                                $price =0;
                            }
                            if(count($parts)>3) {
                                $sku = $parts[3];
                            } else {
                                $sku='';
                            }
                            if(count($parts)>4) {
                                $sort_order = $parts[4];
                            } else {
                                $sort_order = 0;
                            }
                            if(count($parts) > 5) {
                                $filetypes = $parts[5];
                            } else {
                                $filetypes = "";
                            }
                            switch($type) {
                                case 'file':
                                    $custom_options[count($custom_options) - 1]['file_extension'] = $filetypes;
                                    break;

                                case 'field':
                                case 'area':
                                    $custom_options[count($custom_options) - 1]['max_characters'] = $sort_order;


                                case 'date':
                                case 'date_time':
                                case 'time':
                                    $custom_options[count($custom_options) - 1]['price_type'] = $price_type;
                                    $custom_options[count($custom_options) - 1]['price'] = $price;
                                    $custom_options[count($custom_options) - 1]['sku'] = $sku;
                                    break;

                                case 'drop_down':
                                case 'radio':
                                case 'checkbox':
                                case 'multiple':
                                default:
                                    $custom_options[count($custom_options) - 1]['values'][]=array(
                                        'is_delete'=>0,
                                        'title'=>$title,
                                        'option_type_id'=>-1,
                                        'price_type'=>$price_type,
                                        'price'=>$price,
                                        'sku'=>$sku,
                                        'sort_order'=>$sort_order,
                                    );
                                    break;
                            }
                        }
                    }
                }

            }

            /**
             * End product custom options import code
             */


            if (in_array($field, $this->_inventoryFields)) {
                continue;
            }
            if (is_null($value)) {
                continue;
            }

            $attribute = $this->getAttribute($field);
            if (!$attribute) {
                continue;
            }



            $isArray = false;
            $setValue = $value;

            if ($attribute->getFrontendInput() == 'multiselect') {
                $value = explode(self::MULTI_DELIMITER, $value);
                $isArray = true;
                $setValue = array();
            }

            if ($value && $attribute->getBackendType() == 'decimal') {
                $setValue = $this->getNumber($value);
            }

            if ($attribute->usesSource()) {
                $options = $attribute->getSource()->getAllOptions(false);

                if ($isArray) {
                    foreach ($options as $item) {
                        if (in_array($item['label'], $value)) {
                            $setValue[] = $item['value'];
                        }
                    }
                } else {
                    $setValue = false;
                    foreach ($options as $item) {
                        if ($item['label'] == $value) {
                            $setValue = $item['value'];
                        }
                    }
                }
            }

            $product->setData($field, $setValue);
        }

        if (!$product->getVisibility()) {
            $product->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE);
        }

        $stockData = array();
        $inventoryFields = isset($this->_inventoryFieldsProductTypes[$product->getTypeId()])
            ? $this->_inventoryFieldsProductTypes[$product->getTypeId()]
            : array();
        foreach ($inventoryFields as $field) {
            if (isset($importData[$field])) {
                if (in_array($field, $this->_toNumber)) {
                    $stockData[$field] = $this->getNumber($importData[$field]);
                }
                else {
                    $stockData[$field] = $importData[$field];
                }
            }
        }
        $product->setStockData($stockData);

        $mediaGalleryBackendModel = $this->getAttribute('media_gallery')->getBackend();

        $arrayToMassAdd = array();

        foreach ($product->getMediaAttributes() as $mediaAttributeCode => $mediaAttribute) {
            if (isset($importData[$mediaAttributeCode])) {
                $file = trim($importData[$mediaAttributeCode]);
                if (!empty($file) && !$mediaGalleryBackendModel->getImage($product, $file)) {
                    $arrayToMassAdd[] = array('file' => trim($file), 'mediaAttribute' => $mediaAttributeCode);
                }
            }
        }

       /**
       * Begin multiple images, multiple images from URL import & exclude main images code
       */

       if (Mage::getStoreConfig('improvedimport/general/enabled')){

           $_exclude = false;

           if(Mage::getStoreConfig('improvedimport/imagesoptions/image_exclude_enabled')){
               $_exclude = true;
           }else{
               $_exclude = false;
           }

           $addedFilesCorrespondence = $mediaGalleryBackendModel->addImagesWithDifferentMediaAttributes(
               $product,
               $arrayToMassAdd, Mage::getBaseDir('media') . DS . 'import',
               false,
               $_exclude
           );

           $arrayToMassAdd2 = array();
           $arrayToMassAdd3 = array();

           $_column = Mage::getStoreConfig('improvedimport/imagesoptions/multiple_image_column_name');

           /* import images from URL begin  */
           $_url_column = Mage::getStoreConfig('improvedimport/imagesoptions/multiple_image_url_column_name');

           if (!empty($importData[$_url_column])){

               $image_urls = explode(';',$importData[$_url_column]);

               if (!empty($image_urls)){

                   $i=0;
                   foreach ($image_urls as $image_url){
                       $i++;
                       $image_type[$i] = substr(strrchr($image_url,"."),1);
                       $filename[$i]   = md5($image_url . $importData['sku']).'.'.$image_type[$i];
                       $filepath[$i]   = Mage::getBaseDir('media') . DS . 'import'. DS . $filename[$i];
                       if (file_put_contents($filepath[$i], file_get_contents(trim($image_url)))){
                           $additionalUrlImages[$i] = '/'.$filename[$i];
                       }else{
                           Mage::throwException(Mage::helper('catalog')->__("Can't get image from URL for product SKU: (".$product->getSku().")"));
                       }

                   }

               }

               foreach ($additionalUrlImages as $image) {
                   if (!empty($image) && !$mediaGalleryBackendModel->getImage($product, $image)) {
                       $arrayToMassAdd3[] = array('file' => trim($image), 'mediaAttribute' => '');
                   }
               }

               $addedFilesCorrespondence = $mediaGalleryBackendModel->addImagesWithDifferentMediaAttributes(
                   $product,
                   $arrayToMassAdd3, Mage::getBaseDir('media') . DS . 'import',
                   false,
                   false
               );

           }

           /* import images from URL end */

           if (isset($importData[$_column])) {

               $additionalImages = explode(';',$importData[$_column]);

               foreach ($additionalImages as $image) {
                   if (!empty($image) && !$mediaGalleryBackendModel->getImage($product, $image)) {
                       $arrayToMassAdd2[] = array('file' => trim($image), 'mediaAttribute' => '');
                   }
               }

               $addedFilesCorrespondence = $mediaGalleryBackendModel->addImagesWithDifferentMediaAttributes(
                   $product,
                   $arrayToMassAdd2, Mage::getBaseDir('media') . DS . 'import',
                   false,
                   false
               );

           }

        }else{

           $addedFilesCorrespondence = $mediaGalleryBackendModel->addImagesWithDifferentMediaAttributes(
               $product,
               $arrayToMassAdd, Mage::getBaseDir('media') . DS . 'import',
               false,
               false
           );

        }

        /**
         * End multiple images import & exclude main images code
         */




        foreach ($product->getMediaAttributes() as $mediaAttributeCode => $mediaAttribute) {
            $addedFile = '';
            if (isset($importData[$mediaAttributeCode . '_label'])) {
                $fileLabel = trim($importData[$mediaAttributeCode . '_label']);
                if (isset($importData[$mediaAttributeCode])) {
                    $keyInAddedFile = array_search($importData[$mediaAttributeCode],
                        $addedFilesCorrespondence['alreadyAddedFiles']);
                    if ($keyInAddedFile !== false) {
                        if (isset($addedFilesCorrespondence['alreadyAddedFilesNames'][$keyInAddedFile])){
                        $addedFile = $addedFilesCorrespondence['alreadyAddedFilesNames'][$keyInAddedFile];
                        }
                    }
                }

                if (!$addedFile) {
                    $addedFile = $product->getData($mediaAttributeCode);
                }
                if ($fileLabel && $addedFile) {
                    $mediaGalleryBackendModel->updateImage($product, $addedFile, array('label' => $fileLabel));
                }
            }
        }

        $product->setIsMassupdate(true);
        $product->setExcludeUrlRewrite(true);

        $product->save();

        /**
         * Tier prices processing begin
         */

        if (!is_array($this->_group_list)) {
            $this->_group_list = Mage::getResourceModel('customer/group_collection')->setRealGroupsFilter()->loadData()->toOptionArray();
        }

        // is there a tier price field? (check this only the first time)

        if(Mage::getStoreConfig('improvedimport/tierprices/tier_prices_enabled')){

            if (!is_array($this->_tier_price_fields)) {

                $this->_tier_price_fields = array();

                foreach ($importData as $k=>$v) {
                    $matches = array(); 
                    if (preg_match('/^price\_([^_]+)\_?([0-9]+)?$/', $k, $matches)) {
                        // found a valid field. Check the group name and quantity
                        
                        //print_r($matches); echo '123'; die();					

                        $foundvalid = false;
                        foreach ($this->_group_list as $group) {                        	
                            if (strtolower($group['label']) == strtolower($matches[1])) {
                                $foundvalid = true;
                                if (isset($matches[2])) $q = (int)$matches[2]; else $q = 1;
                                $this->_tier_price_fields[$k] = array('id_group'=>$group['value'], 'quantity'=>$q);
                                break;
                            }
                        }

                        if (!$foundvalid) {
                            $message = Mage::helper('catalog')->__('Customer group "%s" for tier price not found', $matches[1]);
                            Mage::throwException($message, Varien_Convert_Exception::NOTICE);
                        }

                    } /* end if */
                    
                    /* fix for NOT LOGGED IN*/
                    
                    if (strstr($k, 'price_not_logged_in')){
	                    $g = split('_', $k);
	                    $qty = $g[4];
	                    $id = 0;
	                    
	                    $this->_tier_price_fields[$k] = array('id_group'=>$id, 'quantity'=>$qty);
                    }
                }

            }

        


			//if (!count($this->_tier_price_fields)) return true; // no tier prices found
		}

        // fetch the store object
        if (empty($importData['store'])) {
            if (!is_null($this->getBatchParams('store'))) {
                $store = $this->getStoreById($this->getBatchParams('store'));
            } else {
                $message = Mage::helper('catalog')->__('Skip import row, required field "%s" not defined', 'store');
                Mage::throwException($message);
            }
        }	else {
            $store = $this->getStoreByCode($importData['store']);
        }

        // create the product object
        $product = $this->getProductModel()->reset();
        $product->setStoreId($store->getId());
        $productId = $product->getIdBySku($importData['sku']);
        $storeId = $store->getId();

        if ($productId) {
            $product->load($productId);

            $tierPrices = $product->tier_price;

            foreach ($this->_tier_price_fields as $tier_key=>$imported_tier_price) {
                // should i update an existing tier price?
                foreach ($tierPrices as $ktp=>$tp) {
                    if ($tp['website_id'] != $storeId) continue;
                    if ($tp['cust_group'] != $imported_tier_price['id_group']) continue;
                    if ($tp['all_groups'] != 0) continue;
                    if ($tp['price_qty'] != $imported_tier_price['quantity']) continue;

                    // it matches this existing tier price. I remove it
                    unset($tierPrices[$ktp]);
                }

                // now i add the imported tier_price
                if ($importData[$tier_key]) {
                    $tierPrices[] = array(
                        'website_id'  => $storeId,
                        'cust_group'  => $imported_tier_price['id_group'],
                        'all_groups'  => 0,
                        'price_qty'   => number_format($imported_tier_price['quantity'], 4, '.', ''),
                        'price'       => number_format($importData[$tier_key], 4, '.','')
                    );
                }


            }

            $product->tier_price = $tierPrices;

            // Save you product with all tier prices
            $product->save();
        }

        /**
         * Tier prices processing end
         */

        /**
         * Product custom options import additional end code
         */
        if(Mage::getStoreConfig('improvedimport/customoptions/custom_options_enabled')){
            foreach ($product->getOptions() as $o) {
                $o->getValueInstance()->deleteValue($o->getId());
                $o->deletePrices($o->getId());
                $o->deleteTitles($o->getId());
                $o->delete();
            }

            /* Add the custom options specified in the CSV import file */
            if(count($custom_options)) {
                foreach($custom_options as $option) {
                    try {
                        $opt = Mage::getModel('catalog/product_option');
                        $opt->setProduct($product);
                        $opt->addOption($option);
                        $opt->saveOptions();
                    }
                    catch (Exception $e) {}
                }
            }
        }

        return true;
    }
}