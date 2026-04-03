<?php
use Aws\S3\S3Client;
class UltraDev_MediaSync_Model_Sync
{
    protected $client;

    protected function getClient()
    {
        if (!$this->client) {
            require_once Mage::getBaseDir() . '/vendor/autoload.php';
            $this->client = new S3Client([
                'version'  => 'latest',
                'region'   => 'auto',
                'endpoint' => Mage::getStoreConfig('ultradev_mediasync/general/endpoint'),
                'credentials' => [
                    'key'    => Mage::getStoreConfig('ultradev_mediasync/general/access_key'),
                    'secret' => Mage::helper('core')->decrypt(
                        Mage::getStoreConfig('ultradev_mediasync/general/secret_key')
                    ),
                ],
                'use_path_style_endpoint' => true,
                'http' => ['timeout' => 30],
            ]);
        }
        return $this->client;
    }

    protected function getBucket()
    {
        return Mage::getStoreConfig('ultradev_mediasync/general/bucket');
    }

    public function isSkipped($relativePath)
    {
        $skipped = Mage::helper('ultradev_mediasync')->getSkippedFolders();
        foreach ($skipped as $folder) {
            if (strpos($relativePath, $folder . '/') === 0 || strpos($relativePath, '/' . $folder . '/') !== false) {
                return true;
            }
        }
        return false;
    }

    public function upload($filePath, $key)
    {
        if (!file_exists($filePath)) return;
        $relative = str_replace('media/', '', $key);
        if ($this->isSkipped($relative)) return;
        try {
            $result = $this->getClient()->headObject([
                'Bucket' => $this->getBucket(),
                'Key'    => $key,
            ]);
            if ((int)$result['ContentLength'] === filesize($filePath)) return;
        } catch (Exception $e) {
            // object does not exist, proceed
        }
        for ($i = 0; $i < 3; $i++) {
            try {
                $this->getClient()->putObject([
                    'Bucket'     => $this->getBucket(),
                    'Key'        => $key,
                    'SourceFile' => $filePath,
                ]);
                Mage::log("R2 Uploaded: $key", null, 'ultradev_mediasync.log');
                return;
            } catch (Exception $e) {
                Mage::logException($e);
                sleep(1);
            }
        }
    }
}
