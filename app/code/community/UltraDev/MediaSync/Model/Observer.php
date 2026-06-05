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

    private function isFbminifyActive()
    {
        return Mage::helper('core')->isModuleEnabled('Fballiano_CssjsMinify')
            && Mage::getStoreConfigFlag('ultradev_mediasync/fbminify/enabled');
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

    /**
     * Intercepta thumbnails gerados pelo OpenMage e faz upload para o R2.
     * Disparado pelo evento catalog_product_image_resize_after.
     * Assim qualquer página (sucesso, produto, carrinho) que gerar um resize
     * automaticamente sincroniza com o CDN sem precisar editar templates.
     */
    public function syncResizedImage(Varien_Event_Observer $observer)
    {
        if (!$this->isEnabled()) return;

        $imageModel = $observer->getObject();
        if (!$imageModel) return;

        $filePath = (string) $imageModel->getNewFile();
        if (!$filePath || !file_exists($filePath)) return;

        $mediaDir = Mage::getBaseDir('media') . DS;

        if (strpos($filePath, $mediaDir) === 0) {
            $key = 'media/' . substr($filePath, strlen($mediaDir));
        } else {
            $pos = strpos($filePath, '/media/');
            if ($pos === false) return;
            $key = substr($filePath, $pos + 1);
        }

        $this->getSync()->upload($filePath, $key);
    }

    public function syncFbminify(Varien_Event_Observer $observer)
    {
        if (!$this->isEnabled()) return;
        if (!$this->isFbminifyActive()) return;

        $mediaDir = Mage::getBaseDir('media');
        $fbminifyDir = $mediaDir . '/fbminify/';
        if (!is_dir($fbminifyDir)) return;

        $files = scandir($fbminifyDir);
        if ($files === false) return;

        $syncedFile = Mage::getBaseDir('var') . '/fbminify_synced.json';
        $synced = [];
        if (file_exists($syncedFile)) {
            $decoded = json_decode(file_get_contents($syncedFile), true);
            if (is_array($decoded)) $synced = $decoded;
        }

        $sync = $this->getSync();
        $changed = false;

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            $filePath = $fbminifyDir . $file;
            if (!is_file($filePath)) continue;
            if (!preg_match('/\.(js|css)$/', $file)) continue;

            $key = 'media/fbminify/' . $file;

            if (!isset($synced[$key])) {
                $sync->upload($filePath, $key);
                $synced[$key] = time();
                $changed = true;
            }
        }

        if ($changed) {
            file_put_contents($syncedFile, json_encode($synced));
        }
    }

    public function flushFbminifyFromR2(Varien_Event_Observer $observer)
    {
        if (!$this->isEnabled()) return;
        if (!$this->isFbminifyActive()) return;

        $this->getSync()->deleteFbminifyFolder();

        $syncedFile = Mage::getBaseDir('var') . '/fbminify_synced.json';
        if (file_exists($syncedFile)) {
            unlink($syncedFile);
        }
    }
}
