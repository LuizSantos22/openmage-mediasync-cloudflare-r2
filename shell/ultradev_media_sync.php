<?php

require_once 'abstract.php';

class UltraDev_MediaSync_Shell extends Mage_Shell_Abstract
{
    public function run()
    {
        $sync = Mage::getModel('ultradev_mediasync/sync');
        $mediaDir = Mage::getBaseDir('media');

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($mediaDir)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) continue;

            $relative = str_replace($mediaDir . '/', '', $file->getPathname());

            $sync->upload($file->getPathname(), 'media/' . $relative);

            echo "✔ $relative\n";
        }
    }
}

$shell = new UltraDev_MediaSync_Shell();
$shell->run();
