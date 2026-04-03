# OpenMage Media Sync - Cloudflare R2

Synchronize Magento/OpenMage media files with a Cloudflare R2 bucket and serve them via CDN for improved performance.

---

## 🚀 Overview

This module provides a lightweight solution to **sync media files** (primarily product images) from your OpenMage installation to a Cloudflare R2 bucket.

Instead of replacing Magento’s storage system, it **keeps local media and syncs it to R2**, allowing you to serve assets through Cloudflare CDN.

---

## ✨ Features

* Sync product images on product save
* CLI script to sync all existing media files
* Compatible with Cloudflare R2 (S3 API)
* Secure credential storage (encrypted in admin)
* Retry mechanism for uploads
* Avoids unnecessary uploads (file size comparison)

---

## 📦 Requirements

* OpenMage / Magento 1.9+
* PHP 8.1+
* Composer
* Cloudflare R2 bucket

---

## 📥 Installation

### Option 1 — Composer (recommended)

Add the OpenMage installer:

```bash
composer require ultradev/openmage-mediasync-cloudflare-r2

---

### Option 2 — Manual

Copy files into your Magento root:

```text
app/code/local/UltraDev/MediaSync
app/etc/modules/UltraDev_MediaSync.xml
shell/ultradev_media_sync.php
```

Then clear cache.

---

## ⚙️ Configuration

Go to:

```text
System > Configuration > UltraDev Media Sync
```

Fill in:

* **Bucket** – Your R2 bucket name
* **Endpoint** – R2 endpoint URL
* **Access Key**
* **Secret Key**

---

## ☁️ Cloudflare R2 Notes

* Do NOT use ACL (`public-read`) — R2 does not support it
* Use **Custom Domain / CDN** via Cloudflare
* Ensure bucket access is properly configured

---

## 🔄 Usage

### Automatic Sync

Triggered on:

* Product save (`catalog_product_save_after`)

---

### Manual Sync (CLI)

Run:

```bash
php shell/ultradev_media_sync.php
```

This will sync all files inside `/media`.

---

## ⚠️ Important Notes

* This module **does NOT replace Magento storage**
* It only syncs files to R2
* Magento still reads from local `/media`
* For CDN usage, configure your media base URL to point to Cloudflare

---

## 🧠 Best Practice

After syncing, set:

```text
System > Configuration > Web > Base URLs
```

Change:

```text
Base Media URL
```

To your Cloudflare CDN URL.

---

## 🛠 Troubleshooting

### Files not uploading

* Check logs:

```text
var/log/ultradev_mediasync.log
```

* Verify credentials and endpoint

---

### Class not found (AWS SDK)

Run:

```bash
composer install
```

---

## 📄 License

Proprietary — UltraDev

---

## 👨‍💻 Author

UltraDev

---

