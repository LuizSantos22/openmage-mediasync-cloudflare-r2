OpenMage Media Sync — Cloudflare R2
Synchronize OpenMage media files with a Cloudflare R2 bucket and serve them via CDN for improved performance.
---
🚀 Overview
This module syncs product and category images from your OpenMage installation to a Cloudflare R2 bucket. A Cloudflare Worker intercepts media requests and serves images directly from R2, while CSS, JS and other assets continue to be served from your origin server (also cached by Cloudflare CDN).
This approach avoids replacing Magento's native storage system and keeps local media intact.
---
✨ Features
Sync product images on product save
Sync category images on category save
Sync CMS block/page media on save
Sync minified JS/CSS bundles from `fballiano/openmage-cssjs-minify` to R2 automatically
CLI script to bulk-sync all existing media files
Compatible with Cloudflare R2 (S3-compatible API)
Secure credential storage (encrypted in admin)
Retry mechanism for uploads (3 attempts)
Avoids unnecessary uploads (local control file — zero R2 API calls on normal requests)
Skips cache, CSS, JS, and other unnecessary folders automatically
Blocks accidental upload of server config files (`.htaccess`, `.env`, etc.)
---
📦 Requirements
OpenMage / Magento 1.9+
PHP 8.1+
Composer
Cloudflare account with R2 enabled
Domain managed by Cloudflare (for Worker custom domain)
---
📥 Installation
Option 1 — Composer (recommended)
```bash
composer require ultradev/openmage-mediasync-cloudflare-r2
```
Option 2 — Manual
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
⚙️ Configuration
Go to:
```
System > Configuration > UltraDev > UltraDev Media Sync
```
Fill in:
Field	Description
Enable	Enable/disable the module
Bucket	Your R2 bucket name (e.g. `my-store-media`)
Endpoint	R2 S3-compatible endpoint (e.g. `https://<ACCOUNT_ID>.r2.cloudflarestorage.com`)
Access Key	R2 API token Access Key ID
Secret Key	R2 API token Secret Access Key (stored encrypted)
Sync FBMinify bundles to R2	Enable automatic sync of `media/fbminify/` to R2 (requires `fballiano/openmage-cssjs-minify`)
---
⚡ Optional: Full JS/CSS Optimization via CDN (fbminify + OpenMage merge)
This module natively integrates with `fballiano/openmage-cssjs-minify` to serve minified JS/CSS bundles directly from Cloudflare R2, dramatically reducing render-blocking requests.
How it works — the full chain
```
OpenMage native merge (Developer settings)
        ↓
  media/js/*.js  +  media/css/*.css
  (consolidated bundles — many files merged into few)
        ↓
fballiano/openmage-cssjs-minify (fbminify)
  intercepts HTML before it's sent to the browser,
  minifies each bundle and saves to media/fbminify/
        ↓
  media/fbminify/*.js  +  media/fbminify/*.css
  (minified, production-ready bundles)
        ↓
UltraDev MediaSync (this module)
  detects new files in media/fbminify/ and uploads to R2
        ↓
  cdn.yourdomain.com/media/fbminify/*.js
  cdn.yourdomain.com/media/fbminify/*.css
  (served via Cloudflare CDN with long cache)
```
Why enable OpenMage native merge with fbminify?
The fbminify author recommends not enabling OpenMage's native JS/CSS merge because, without a CDN, consolidating into a few large files can be slower than many small files loaded in parallel via HTTP/2.
However, when this R2 module is active, the equation changes significantly:
Scenario	Files in `<head>`	Served from	Result
No merge, no CDN	60+ individual files	Origin server	Slow
Merge only, no CDN	2–4 large bundles	Origin server	Medium
No merge, with CDN	60+ individual files	Cloudflare	Medium
Merge + CDN (this setup)	2–4 minified bundles	Cloudflare	Fast
Combining few files with a CDN is the optimal scenario: the browser makes very few requests, all answered by Cloudflare's edge with minimal latency.
Setup
Step 1 — Install fbminify
```bash
composer require fballiano/openmage-cssjs-minify
```
Step 2 — Enable OpenMage native merge
```
Admin → System → Configuration → Developer
  → JavaScript Settings → Merge JavaScript Files: Yes
  → CSS Settings → Merge CSS Files: Yes
```
OpenMage will consolidate all JS files into bundles saved at:
```
media/js/*.js
```
And CSS files into:
```
media/css/*.css
```
> These files in `media/js/` and `media/css/` are the **input** for fbminify — do not delete them.
Step 3 — Enable FBMinify sync in this module
```
Admin → System → Configuration → UltraDev → UltraDev Media Sync
  → FBMinify Sync → Sync FBMinify bundles to R2: Yes
```
Step 4 — Trigger first sync
Visit your store in an incognito window. The fbminify module will generate the minified bundles in `media/fbminify/` and this module will automatically upload them to R2.
Confirm in the log:
```bash
tail -f var/log/ultradev_mediasync.log
```
You should see entries like:
```
R2 Uploaded: media/fbminify/abc123-1780264396.js
R2 Uploaded: media/fbminify/def456-1780264396.css
```
And in the page source you should see:
```html
<link rel="stylesheet" href="https://cdn.yourdomain.com/media/fbminify/def456-1780264396.css">
<script src="https://cdn.yourdomain.com/media/fbminify/abc123-1780264396.js"></script>
```
How the sync control works
To avoid making R2 API calls on every page request, the module maintains a local control file at:
```
var/fbminify_synced.json
```
This file records which files have already been uploaded. On each request, only a local file read is performed — no R2 API calls unless a new file is detected.
When you flush the OpenMage cache from admin, the module automatically:
Deletes all `media/fbminify/` files from R2
Deletes `var/fbminify_synced.json`
Re-sync happens automatically on the next store visit
Manual re-sync (if needed)
Only required if you manually deleted the `media/fbminify/` folder from R2 without going through the admin cache flush:
```bash
rm -f var/fbminify_synced.json
```
Then visit the store in an incognito window.
---
☁️ Cloudflare R2 Setup
1. Create the bucket
Go to R2 Object Storage → Create bucket
Note the S3 API endpoint from bucket Settings
2. Create API credentials
Go to R2 Object Storage → Manage R2 API Tokens
Click Create Account API Token
Set Permission: `Object Read & Write`
Set Specify bucket: your bucket name
Copy the Access Key ID and Secret Access Key (secret shown only once)
> ⚠️ Do NOT use `public-read` ACL — R2 does not support it.
---
🔀 Cloudflare Worker Setup (required for CDN serving)
> **Important:** Simply setting Base Media URL to a custom domain linked directly to the R2 bucket will break your site's CSS/JS, because OpenMage uses the same base media URL for all assets including stylesheets. The correct approach is to use a Cloudflare Worker as a smart proxy.
How it works
The Worker intercepts requests to your CDN subdomain:
Requests to `/media/catalog/`, `/media/wysiwyg/`, `/media/header/`, `/media/fbminify/` → served from R2
OpenMage cache URLs (e.g. `/media/catalog/product/cache/1/image/600x/.../file.jpg`) → Worker strips the cache path and fetches the original image from R2
Everything else (CSS, JS, skin files) → proxied from your origin server
Step 1 — Create the Worker
Go to Workers & Pages → Create
Select Start with Hello World
Name it (e.g. `ultradev-media-proxy`)
Replace the code with:
```javascript
export default {
  async fetch(request, env, ctx) {
    const url = new URL(request.url);
    const path = url.pathname;

    if (
      path.startsWith('/media/catalog/') ||
      path.startsWith('/media/wysiwyg/') ||
      path.startsWith('/media/header/') ||
      path.startsWith('/media/fbminify/')
    ) {
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

    // Proxy everything else from origin
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
Click Deploy
Step 2 — Bind R2 bucket to the Worker
Go to Worker Settings → Domains & Routes → + Add → R2 bucket
Set Variable name: `R2_BUCKET`
Select your bucket
Click Deploy
Step 3 — Add custom domain to the Worker
Go to Worker Settings → Domains & Routes → + Add → Custom domain
Enter your CDN subdomain (e.g. `cdn.yourdomain.com`)
Confirm
> If you get "domain already in use", go to your R2 bucket → **Settings** → **Custom Domains** → remove the domain there first, then add it to the Worker.
---
🌐 Configure OpenMage Base Media URL
After setting up the Worker, update the media URL in OpenMage:
```
System > Configuration > General > Web
```
Set both Base URL for Media Files (HTTP and HTTPS) to:
```
https://cdn.yourdomain.com/media/
```
> Note the `/media/` at the end — required because the sync script uploads files with the `media/` prefix as the R2 object key.
> **Base URL for Skin Files** should remain pointing to your origin server (`{{unsecure_base_url}}skin/`) — skin files are not synced to R2.
---
🔄 Usage
Automatic Sync
Triggered automatically on:
Product save (`catalog_product_save_after`)
Category save (`catalog_category_save_after`)
CMS block save (`cms_block_save_after`)
CMS page save (`cms_page_save_after`)
Every frontend request — detects new fbminify bundles and uploads them (`http_response_send_before`)
Admin cache flush — removes fbminify files from R2 and resets sync control (`adminhtml_cache_flush_all`, `adminhtml_cache_flush_system`)
Manual Bulk Sync (CLI)
To sync all existing media files (first-time setup):
```bash
php shell/ultradev_media_sync.php
```
Folders skipped automatically
Folder	Reason
`cache/`	Regenerated dynamically by OpenMage
`css/` and `css_secure/`	Intermediate CSS — fbminify handles this
`js/`	Intermediate JS bundles — input for fbminify, not served directly
`tmp/`	Temporary uploads
`customer/`	Customer avatars
`downloadable/`	Digital download files
`xmlconnect/`	Legacy mobile app assets
`theme/`	Theme files
`header/`	Admin logo
Files skipped by name
`.htaccess`, `.htpasswd`, `php.ini`, `.env` — server config files that must never be exposed via CDN.
---
⚠️ Important Notes
This module does not replace Magento's native storage system
OpenMage still reads from local `/media` — R2 is used only for CDN delivery
The `media/js/` and `media/css/` folders are used internally by OpenMage and fbminify — do not delete them and do not add them to R2
When clearing OpenMage cache, also purge Cloudflare cache (Caching → Purge Everything) so CDN fetches fresh assets from origin
---
🛠 Troubleshooting
Files not uploading
```bash
tail -f var/log/ultradev_mediasync.log
```
Verify credentials and endpoint in admin configuration.
fbminify bundles not appearing in R2
Delete the sync control file and visit the store in an incognito window:
```bash
rm -f var/fbminify_synced.json
```
Class not found (AWS SDK)
```bash
composer install
```
Images showing 404 after setup
Confirm files were synced to R2 (check bucket objects)
Confirm R2 Binding `R2_BUCKET` is set in Worker settings
Confirm Worker custom domain matches the Base Media URL configured in OpenMage
Site CSS broken after changing Base Media URL
You likely set the CDN URL directly on the R2 bucket custom domain instead of using the Worker. Follow the Worker setup instructions above.
CORS errors for fonts
The Worker automatically adds `Access-Control-Allow-Origin: *` to all responses. If you still see CORS errors, redeploy the Worker with the latest code above.
---
📄 License
Proprietary — UltraDev
---
👨‍💻 Author
UltraDev
