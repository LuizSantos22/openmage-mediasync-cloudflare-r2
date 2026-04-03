<?php
class UltraDev_MediaSync_Helper_Data extends Mage_Core_Helper_Abstract
{
    public function isEnabled()
    {
        return Mage::getStoreConfigFlag('ultradev_mediasync/general/enabled');
    }

    public function getSkippedFolders()
    {
        return ['cache', 'css_secure', 'css', 'js', 'tmp'];
    }
}
