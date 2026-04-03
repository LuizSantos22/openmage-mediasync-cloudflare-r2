<?php
class UltraDev_MediaSync_Model_Observer
{
    private function getSync()
    {
        return Mage::getModel('ultradev_mediasync/sync');
    }

    private function isEnabled()
    {
        return Mage::helper('ultradev_mediasync')->isEnabled();
    }

    public function syncProductMedia(Varien_Event_Observer $observer)
    {
        if (!$this->isEnabled()) return;
        $product = $observer->getProduct();
        if (!$product || !$product->getId()) return;
        $mediaDir = Mage::getBaseDir('media') . '/catalog/product';
        $sync = $this->getSync();
        foreach ($product->getMediaGalleryImages() as $image) {
            $file = $image->getFile();
            $filePath = $mediaDir . $file;
            if (file_exists($filePath)) {
                $sync->upload($filePath, 'media/catalog/product' . $file);
            }
        }
    }

    public function syncCategoryMedia(Varien_Event_Observer $observer)
    {
        if (!$this->isEnabled()) return;
        $category = $observer->getCategory();
        if (!$category || !$category->getId()) return;
        $image = $category->getImage();
        if (!$image) return;
        $filePath = Mage::getBaseDir('media') . '/catalog/category/' . $image;
        if (file_exists($filePath)) {
            $this->getSync()->upload($filePath, 'media/catalog/category/' . $image);
        }
    }

    public function syncCmsMedia(Varien_Event_Observer $observer)
    {
        if (!$this->isEnabled()) return;
        $block = $observer->getObject();
        if (!$block || !$block->getId()) return;
        $content = $block->getContent();
        if (!$content) return;
        preg_match_all('/{{media url="([^"]+)"}}/', $content, $matches);
        if (empty($matches[1])) return;
        $sync = $this->getSync();
        $mediaDir = Mage::getBaseDir('media');
        foreach ($matches[1] as $relativePath) {
            $filePath = $mediaDir . '/' . $relativePath;
            if (file_exists($filePath)) {
                $sync->upload($filePath, 'media/' . $relativePath);
            }
        }
    }
}
