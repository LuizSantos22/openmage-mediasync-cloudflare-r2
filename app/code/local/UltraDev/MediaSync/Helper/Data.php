<?php

class UltraDev_MediaSync_Helper_Data extends Mage_Core_Helper_Abstract
{
    public function isEnabled()
    {
        return Mage::getStoreConfigFlag('ultradev_mediasync/general/enabled');
    }
}
