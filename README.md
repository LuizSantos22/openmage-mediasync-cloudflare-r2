# OpenMage Media Sync — Cloudflare R2

Synchronize OpenMage media files with a Cloudflare R2 bucket and serve them via CDN for improved performance.

---

## 🚀 Overview

This module syncs product and category images from your OpenMage installation to a Cloudflare R2 bucket. A Cloudflare Worker intercepts media requests and serves images directly from R2, while CSS, JS and other assets continue to be served from your origin server (also cached by Cloudflare CDN).

This approach avoids replacing Magento's native storage system and keeps local media intact.

---

## ✨ Features

- Sync product images on product save
- Sync category images on category save
- Sync CMS block/page media on save
- CLI script to bulk-sync all existing media files
- Compatible with Cloudflare R2 (S3-compatible API)
- Secure credential storage (encrypted in admin)
- Retry mechanism for uploads (3 attempts)
- Avoids unnecessary uploads (file size comparison)
- Skips cache, CSS and JS folders automatically

---

## 📦 Requirements

- OpenMage / Magento 1.9+
- PHP 8.1+
- Composer
- Cloudflare account with R2 enabled
- Domain managed by Cloudflare (for Worker custom domain)

---

## 📥 Installation

### Option 1 — Composer (recommended)

```bash
composer require ultradev/openmage-mediasync-cloudflare-r2
```

### Option 2 — Manual

Copy files into your Magento root:

```
app/code/community/UltraDev/MediaSync
app/etc/modules/UltraDev_MediaSync.xml
shell/ultradev_media_sync.php
```

Then clear cache:

```bash
rm -rf var/cache/* var/session/*
```

---

## ⚙️ Configuration

Go to:

```
System > Configuration > UltraDev > UltraDev Media Sync
```

Fill in:

| Field | Description |
|---|---|
| Enable | Enable/disable the module |
| Bucket | Your R2 bucket name (e.g. `my-store-media`) |
| Endpoint | R2 S3-compatible endpoint (e.g. `https://<ACCOUNT_ID>.r2.cloudflarestorage.com`) |
| Access Key | R2 API token Access Key ID |
| Secret Key | R2 API token Secret Access Key (stored encrypted) |

---

## ☁️ Cloudflare R2 Setup

### 1. Create the bucket

- Go to **R2 Object Storage** → **Create bucket**
- Note the **S3 API endpoint** from bucket Settings

### 2. Create API credentials

- Go to **R2 Object Storage** → **Manage R2 API Tokens**
- Click **Create Account API Token**
- Set **Permission**: `Object Read & Write`
- Set **Specify bucket**: your bucket name
- Copy the **Access Key ID** and **Secret Access Key** (secret shown only once)

> ⚠️ Do NOT use `public-read` ACL — R2 does not support it.

---

## 🔀 Cloudflare Worker Setup (required for CDN serving)

> **Important:** Simply setting Base Media URL to a custom domain linked directly to the R2 bucket will break your site's CSS/JS, because OpenMage uses the same base media URL for all assets including stylesheets. The correct approach is to use a Cloudflare Worker as a smart proxy.

### How it works

The Worker intercepts requests to your CDN subdomain:

- Requests to `/media/catalog/`, `/media/wysiwyg/`, `/media/header/` → served from R2
- OpenMage cache URLs (e.g. `/media/catalog/product/cache/1/image/600x/.../file.jpg`) → Worker strips the cache path and fetches the **original image** from R2
- Everything else (CSS, JS, skin files) → proxied from your origin server

This means images are served from R2 via Cloudflare CDN, while CSS/JS continue to work normally.

### Step 1 — Create the Worker

1. Go to **Workers & Pages** → **Create**
2. Select **Start with Hello World**
3. Name it (e.g. `ultradev-media-proxy`)
4. Replace the code with:

```javascript
export default {
  async fetch(request, env, ctx) {
    const url = new URL(request.url);
    const path = url.pathname;

    if (
      path.startsWith('/media/catalog/') ||
      path.startsWith('/media/wysiwyg/') ||
      path.startsWith('/media/header/')
    ) {
      // Strip cache path and fetch original image from R2
      // e.g. /media/catalog/product/cache/1/image/600x/.../a/b/img.jpg
      //   -> media/catalog/product/a/b/img.jpg
      let key = path.replace(/^\/media\//, 'media/');

      const cacheMatch = path.match(
        /\/media\/catalog\/product\/cache\/[^/]+\/[^/]+\/[^/]+\/[^/]+\/(.+)$/
      );
      if (cacheMatch) {
        key = 'media/catalog/product/' + cacheMatch[1];
      }

      const object = await env.R2_BUCKET.get(key);

      if (!object) {
        return new Response('Not found', { status: 404 });
      }

      const headers = new Headers();
      object.writeHttpMetadata(headers);
      headers.set('Cache-Control', 'public, max-age=31536000');
      headers.set('Access-Control-Allow-Origin', '*');
      return new Response(object.body, { headers });
    }

    // Proxy everything else from origin, adding CORS headers for fonts
    const response = await fetch(
      'https://yourdomain.com' + path + url.search,
      request
    );
    const newHeaders = new Headers(response.headers);
    newHeaders.set('Access-Control-Allow-Origin', '*');
    return new Response(response.body, {
      status: response.status,
      headers: newHeaders,
    });
  }
};
```

> Replace `yourdomain.com` with your actual store domain.

5. Click **Deploy**

### Step 2 — Bind R2 bucket to the Worker

1. Go to Worker **Settings** → **Domains & Routes** → **+ Add** → **R2 bucket**
2. Set **Variable name**: `R2_BUCKET`
3. Select your bucket
4. Click **Deploy**

### Step 3 — Add custom domain to the Worker

1. Go to Worker **Settings** → **Domains & Routes** → **+ Add** → **Custom domain**
2. Enter your CDN subdomain (e.g. `cdn.yourdomain.com`)
3. Confirm

> If you get "domain already in use", go to your R2 bucket → **Settings** → **Custom Domains** → remove the domain there first, then add it to the Worker.

---

## 🌐 Configure OpenMage Base Media URL

After setting up the Worker, update the media URL in OpenMage:

```
System > Configuration > General > Web
```

Set both **Base URL for Media Files** (HTTP and HTTPS) to:

```
https://cdn.yourdomain.com/media/
```

> Note the `/media/` at the end — required because the sync script uploads files with the `media/` prefix as the R2 object key.

Or via CLI:

```bash
php -r "
require_once 'app/Mage.php';
Mage::app();
Mage::getConfig()->saveConfig('web/unsecure/base_media_url', 'https://cdn.yourdomain.com/media/');
Mage::getConfig()->saveConfig('web/secure/base_media_url', 'https://cdn.yourdomain.com/media/');
Mage::app()->getConfig()->reinit();
"
rm -rf var/cache/* var/session/*
```

---

## 🔄 Usage

### Automatic Sync

Triggered automatically on:

- Product save (`catalog_product_save_after`)
- Category save (`catalog_category_save_after`)
- CMS block save (`cms_block_save_after`)
- CMS page save (`cms_page_save_after`)

### Manual Bulk Sync (CLI)

To sync all existing media files (first-time setup):

```bash
php shell/ultradev_media_sync.php
```

The following folders are **skipped** automatically:

- `cache/` — regenerated dynamically by OpenMage
- `css_secure/` — compiled CSS
- `css/` — compiled CSS
- `js/` — compiled JS
- `tmp/` — temporary uploads

---

## ⚠️ Important Notes

- This module does **not** replace Magento's native storage system
- Magento still reads from local `/media` — R2 is used only for CDN delivery
- After clearing OpenMage cache (`var/cache/*`), images continue to be served from R2 unchanged — only CSS/JS are regenerated locally
- When clearing cache, also purge Cloudflare cache (**Caching → Purge Everything**) so CDN fetches fresh CSS/JS from origin

---

## 🛠 Troubleshooting

### Files not uploading

Check the log:

```bash
tail -f var/log/ultradev_mediasync.log
```

Verify credentials and endpoint in admin configuration.

### Class not found (AWS SDK)

```bash
composer install
```

### Images showing 404 after setup

- Confirm files were synced to R2 (check bucket objects)
- Confirm R2 Binding `R2_BUCKET` is set in Worker settings
- Confirm Worker custom domain matches the Base Media URL configured in OpenMage

### Site CSS broken after changing Base Media URL

You likely set the CDN URL directly on the R2 bucket custom domain instead of using the Worker. Follow the Worker setup instructions above — the Worker correctly routes CSS/JS to origin while serving images from R2.

### CORS errors for fonts

The Worker automatically adds `Access-Control-Allow-Origin: *` to all responses. If you still see CORS errors, redeploy the Worker with the latest code above.

---

## 📄 License

Proprietary — UltraDev

---

## 👨‍💻 Author

UltraDev
