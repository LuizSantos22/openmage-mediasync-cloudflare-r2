<?php
class UltraDev_MediaSync_Helper_Data extends Mage_Core_Helper_Abstract
{
    public function isEnabled()
    {
        return Mage::getStoreConfigFlag('ultradev_mediasync/general/enabled');
    }

    public function getSkippedFolders()
    {
        return ['cache', 'css_secure', 'css', 'js', 'tmp', 'customer', 'downloadable', 'xmlconnect', 'theme', 'header'];
    }

    public function isSkippedFile($relativePath)
    {
        $skippedFiles = ['.htaccess', '.htpasswd', 'php.ini', '.env'];
        $filename = basename($relativePath);
        return in_array($filename, $skippedFiles);
    }
}
