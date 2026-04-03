<?php

class UltraDev_MediaSync_Model_Observer
{
    public function syncProductMedia(Varien_Event_Observer $observer)
    {
        if (!Mage::helper('ultradev_mediasync')->isEnabled()) {
            return;
        }

        $product = $observer->getProduct();

        if (!$product || !$product->getId()) {
            return;
        }

        $mediaDir = Mage::getBaseDir('media') . '/catalog/product';

        $sync = Mage::getModel('ultradev_mediasync/sync');

        foreach ($product->getMediaGalleryImages() as $image) {
            $file = $image->getFile();
            $filePath = $mediaDir . $file;

            if (file_exists($filePath)) {
                $sync->upload($filePath, 'media/catalog/product' . $file);
            }
        }
    }
}
