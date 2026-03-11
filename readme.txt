=== Client-Side Media Experiments ===
Contributors: adamsilverstein
Tags: media, performance, cross-origin, wasm
Requires at least: 6.8
Tested up to: 6.8
Stable tag: 0.1.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Enables client-side media processing on Firefox and Safari via COEP/COOP cross-origin isolation headers, and optional HEIC upload support.

== Description ==

WordPress 7.0 and Gutenberg include client-side media processing powered by WebAssembly (wasm-vips). This requires cross-origin isolation, which is achieved via Document-Isolation-Policy (DIP) on Chrome 137+.

However, Firefox and Safari do not yet support DIP, so client-side media processing is disabled on those browsers.

This plugin restores support for Firefox and Safari by sending the older COEP/COOP headers (Cross-Origin-Embedder-Policy / Cross-Origin-Opener-Policy) on browsers where DIP is not available.

**What this plugin does:**

* Sends `Cross-Origin-Opener-Policy: same-origin` and `Cross-Origin-Embedder-Policy: credentialless` (or `require-corp` on Safari) headers in the block editor.
* Adds `crossorigin="anonymous"` attributes to cross-origin resources.
* Adds `credentialless` attribute to iframes so they continue working under COEP.
* Filters embed previews for providers that do not support credentialless iframes (Facebook, SmugMug).
* **Optional HEIC support:** Enables uploading HEIC/HEIF images (e.g. from iPhones) by converting them to JPEG on the client side.

**HEIC Support:**

HEIC support was removed from WordPress core due to patent restrictions that conflict with the GPL license. This plugin re-enables HEIC upload support as an opt-in feature:

* When enabled, HEIC/HEIF images are automatically converted to JPEG in the browser before upload.
* The conversion uses the [heic2any](https://github.com/nicktomlin/heic2any) library, which is loaded dynamically from an external CDN only when a HEIC file is detected.
* heic2any uses [libheif](https://github.com/strukturag/libheif) (LGPL-3.0 licensed) for decoding. Since the library is loaded at runtime from a CDN rather than bundled with the plugin, it is treated as a separate work and does not affect the plugin's GPL-2.0-or-later license.
* The CDN URL is filterable via the `csme_heic_library_url` filter for self-hosting or version changes.

**Requirements:**

* WordPress 6.8+ with the Gutenberg plugin (which provides the client-side media processing feature), or WordPress 7.0+.
* The client-side media processing feature must be enabled.

== Installation ==

1. Upload the `client-side-media-experiments` folder to `/wp-content/plugins/`.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. The plugin activates automatically on browsers that need COEP/COOP headers.

== Frequently Asked Questions ==

= Do I need this plugin on Chrome? =

No. Chrome 137+ uses Document-Isolation-Policy, which is handled by WordPress core / Gutenberg. This plugin only activates on browsers that do not support DIP.

= Will this break my site? =

The COEP/COOP headers are only sent on block editor admin pages, not on the front-end. Some third-party plugins that use iframes in the editor may be affected. If you experience issues, you can deactivate the plugin.

= Can I disable the COEP/COOP behavior? =

Yes. Use the `csme_use_coep_coop` filter:

`add_filter( 'csme_use_coep_coop', '__return_false' );`

= How does HEIC support work? =

When enabled in Settings > Media, the plugin converts HEIC/HEIF images to JPEG directly in the browser before uploading them to WordPress. The conversion library (heic2any) is loaded from an external CDN only when a HEIC file is detected, so there is no impact on normal uploads.

= What about the HEIC license? =

The heic2any library uses libheif (LGPL-3.0) for HEIC decoding. Since the library is loaded at runtime from a CDN and not bundled with the plugin, it does not affect the plugin's GPL-2.0-or-later license. HEIC support is disabled by default and must be explicitly enabled.

= Can I self-host the HEIC conversion library? =

Yes. Use the `csme_heic_library_url` filter to point to your own hosted copy:

`add_filter( 'csme_heic_library_url', function() { return 'https://example.com/heic2any.min.js'; } );`

== Changelog ==

= 0.2.0 =
* Added optional HEIC/HEIF upload support with client-side conversion to JPEG.
* HEIC conversion library loaded dynamically from CDN (heic2any / libheif).
* New setting under Settings > Media to enable HEIC support.
* Filterable CDN URL via `csme_heic_library_url`.

= 0.1.0 =
* Initial release.
* COEP/COOP header support for Firefox and Safari.
* Credentialless iframe handling.
* Embed preview filtering for incompatible providers.
