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
 * Catalog product media gallery attribute backend model
 *
 * @category    Firebear
 * @package     Firebear_ImpovedImport
 */
class Firebear_ImprovedImport_Model_Catalog_Product_Attribute_Backend_Media extends Mage_Catalog_Model_Product_Attribute_Backend_Media
{
    /**
     * Add image to media gallery and return new filename
     *
     * @param Mage_Catalog_Model_Product $product
     * @param string                     $file              file path of image in file system
     * @param string|array               $mediaAttribute    code of attribute with type 'media_image',
     *                                                      leave blank if image should be only in gallery
     * @param boolean                    $move              if true, it will move source file
     * @param boolean                    $exclude           mark image as disabled in product page view
     * @return string
     */
    public function addImage(Mage_Catalog_Model_Product $product, $file,
                             $mediaAttribute = null, $move = false, $exclude = true)
    {

        /* modification for import main product images from URL begin */
        if (Mage::getStoreConfig('improvedimport/imagesoptions/main_images_url_enabled')){

            if (strpos($file,'http')){

                $image_url = str_replace(Mage::getBaseDir('media').'/import', '', $file);
                $image_type = substr(strrchr($image_url,"."),1);
                $dir = Mage::getBaseDir('media') . DS . 'import'. DS;
                $filename   = md5($image_url . $product->getSku()).'.'.$image_type;
                $filepath   = $dir . $filename;
                
                // Create directory if it doesn't exist.
                if (!file_exists($dir)) {
	                mkdir($dir, 0755);
                }
                
                // Upload image from external url to temporary folder.
                if (file_put_contents($filepath, file_get_contents(trim($image_url)))){
                    $file = $filepath;
                }else{
                    Mage::throwException(Mage::helper('catalog')->__("Can't get image from URL for product SKU: (".$product->getSku().")"));
                }
            }
        }
        /* modification for import main product images from URL end */

        $file = realpath($file);

        if (!$file || !file_exists($file)) {
            if(Mage::getStoreConfig('improvedimport/imagesoptions/image_sku_enabled')){
                Mage::throwException(Mage::helper('catalog')->__('Image does not exist for product SKU: ('.$product->getSku().')'));
            }else{
                Mage::throwException(Mage::helper('catalog')->__('Image does not exist'));
            }
        }

        Mage::dispatchEvent('catalog_product_media_add_image', array('product' => $product, 'image' => $file));

        $pathinfo = pathinfo($file);
        $imgExtensions = array('jpg','jpeg','gif','png');
        if (!isset($pathinfo['extension']) || !in_array(strtolower($pathinfo['extension']), $imgExtensions)) {
            if(Mage::getStoreConfig('improvedimport/imagesoptions/image_sku_enabled')){
                Mage::throwException(Mage::helper('catalog')->__('Invalid image file type for product SKU: ('.$product->getSku().')'));
            }else{
                Mage::throwException(Mage::helper('catalog')->__('Invalid image file type'));
            }
        }



        $fileName       = Mage_Core_Model_File_Uploader::getCorrectFileName($pathinfo['basename']);
        $dispretionPath = Mage_Core_Model_File_Uploader::getDispretionPath($fileName);
        $fileName       = $dispretionPath . DS . $fileName;

        $fileName = $this->_getNotDuplicatedFilename($fileName, $dispretionPath);

        $ioAdapter = new Varien_Io_File();
        $ioAdapter->setAllowCreateFolders(true);
        $distanationDirectory = dirname($this->_getConfig()->getTmpMediaPath($fileName));

        try {
            $ioAdapter->open(array(
                'path'=>$distanationDirectory
            ));

            /** @var $storageHelper Mage_Core_Helper_File_Storage_Database */
            $storageHelper = Mage::helper('core/file_storage_database');
            if ($move) {
                $ioAdapter->mv($file, $this->_getConfig()->getTmpMediaPath($fileName));

                //If this is used, filesystem should be configured properly
                $storageHelper->saveFile($this->_getConfig()->getTmpMediaShortUrl($fileName));
            } else {
                $ioAdapter->cp($file, $this->_getConfig()->getTmpMediaPath($fileName));

                $storageHelper->saveFile($this->_getConfig()->getTmpMediaShortUrl($fileName));
                $ioAdapter->chmod($this->_getConfig()->getTmpMediaPath($fileName), 0777);
            }
        }
        catch (Exception $e) {
            Mage::throwException(Mage::helper('catalog')->__('Failed to move file: %s', $e->getMessage()));
        }

        $fileName = str_replace(DS, '/', $fileName);

        $attrCode = $this->getAttribute()->getAttributeCode();
        $mediaGalleryData = $product->getData($attrCode);
        $position = 0;
        if (!is_array($mediaGalleryData)) {
            $mediaGalleryData = array(
                'images' => array()
            );
        }

        foreach ($mediaGalleryData['images'] as &$image) {
            if (isset($image['position']) && $image['position'] > $position) {
                $position = $image['position'];
            }
        }

        $position++;
        $mediaGalleryData['images'][] = array(
            'file'     => $fileName,
            'position' => $position,
            'label'    => '',
            'disabled' => (int) $exclude
        );

        $product->setData($attrCode, $mediaGalleryData);

        if (!is_null($mediaAttribute)) {
            $this->setMediaAttribute($product, $mediaAttribute, $fileName);
        }

        return $fileName;
    }
}