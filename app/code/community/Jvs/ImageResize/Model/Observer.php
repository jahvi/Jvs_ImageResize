<?php
/**
 * Wysiwyg images upload observer
 *
 * @category    Jvs
 * @package     Jvs_ImageResize
 * @author      Javier Villanueva <javiervd@gmail.com>
 */
class Jvs_ImageResize_Model_Observer
{
    /**
     * Resize images after upload
     *
     * @param  Varien_Event_Observer $observer Event observer
     * @return void
     */
    public function imageUploadHandler(Varien_Event_Observer $observer)
    {
        $responseBody = $observer->getEvent()->getControllerAction()->getResponse()->getBody();
        $response = Mage::helper('core')->jsonDecode($responseBody);

        // Resize images if no errors during upload
        if (!$response['error']) {
            $this->_resizeImage($response['path'] . DS . $response['file']);
        }
    }

    /**
     * Remove resized images after original has been deleted
     *
     * @param  Varien_Event_Observer $observer Event observer
     * @return void
     */
    public function imageDeleteHandler(Varien_Event_Observer $observer)
    {
        $controllerAction = $observer->getEvent()->getControllerAction();

        $filesParam = $controllerAction->getRequest()->getParam('files');
        $files = Mage::helper('core')->jsonDecode($filesParam);

        $this->_deleteImages($files, $controllerAction);
    }

    /**
     * Resize image and save it to original directory
     *
     * @param  string    $source Image path to be resized
     * @param  bool      $keepRation Keep aspect ratio or not
     * @return bool|void Void or false if errors were occurred
     */
    protected function _resizeImage($source, $keepRation = true)
    {
        if (!is_file($source) || !is_readable($source)) {
            return false;
        }

        $imageSizes = $this->_getImageSizes();

        foreach ($imageSizes as $key => $size) {
            $image = Varien_Image_Adapter::factory('GD2');
            $image->open($source);
            $width = $size['width'];
            $height = $size['height'];
            $image->keepAspectRatio($keepRation);

            // Don't resize if image is smaller than target size
            if ($image->getOriginalWidth() > $size['width'] ||
                $image->getOriginalHeight() > $size['height']
            ) {
                $image->resize($width, $height);

                $targetDir = pathinfo($source, PATHINFO_DIRNAME);
                $imageName = pathinfo($source, PATHINFO_FILENAME);
                $resizedName = "{$imageName}-{$key}." . pathinfo($source, PATHINFO_EXTENSION);

                $dest = $targetDir . DS . $resizedName;
                $image->save($dest);
            }
        }
    }

    /**
     * Delete resized images when original is removed
     * @param  array                              $files            Original images
     * @param  Mage_Core_Controller_Varien_Action $controllerAction Controller Action
     * @return void
     */
    protected function _deleteImages(array $files, Mage_Core_Controller_Varien_Action $controllerAction)
    {
        $imageSizes = $this->_getImageSizes();

        /** @var $helper Mage_Cms_Helper_Wysiwyg_Images */
        $helper = Mage::helper('cms/wysiwyg_images');
        $path = $controllerAction->getStorage()->getSession()->getCurrentPath();

        foreach ($files as $file) {
            $file = $helper->idDecode($file);
            $imageName = pathinfo($file, PATHINFO_FILENAME);

            foreach ($imageSizes as $key => $size) {
                $resizedName = "{$imageName}-{$key}." . pathinfo($file, PATHINFO_EXTENSION);
                $_filePath = realpath($path . DS . $resizedName);

                if (strpos($_filePath, realpath($path)) === 0 &&
                    strpos($_filePath, realpath($helper->getStorageRoot())) === 0
                ) {
                    $controllerAction->getStorage()->deleteFile($path . DS . $resizedName);
                }
            }
        }
    }

    /**
     * Get image sizes from config file
     *
     * @return array Formatted image sizes
     */
    protected function _getImageSizes()
    {
        $config = Mage::getStoreConfig('cms/imageresize');
        $imageSizes = array();

        foreach ($config as $key => $size) {
            $sizes = preg_split('/ ?x ?/', $size);

            $imageSizes[$key]['width'] = $sizes[0];
            $imageSizes[$key]['height'] = $sizes[1];
        }

        return $imageSizes;
    }
}
