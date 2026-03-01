=== Client-Side Media Experiments ===
Contributors: adamsilverstein
Tags: media, performance, cross-origin, wasm
Requires at least: 6.8
Tested up to: 6.8
Stable tag: 0.1.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Enables client-side media processing on Firefox and Safari via COEP/COOP cross-origin isolation headers.

== Description ==

WordPress 7.0 and Gutenberg include client-side media processing powered by WebAssembly (wasm-vips). This requires cross-origin isolation, which is achieved via Document-Isolation-Policy (DIP) on Chrome 137+.

However, Firefox and Safari do not yet support DIP, so client-side media processing is disabled on those browsers.

This plugin restores support for Firefox and Safari by sending the older COEP/COOP headers (Cross-Origin-Embedder-Policy / Cross-Origin-Opener-Policy) on browsers where DIP is not available.

**What this plugin does:**

* Sends `Cross-Origin-Opener-Policy: same-origin` and `Cross-Origin-Embedder-Policy: credentialless` (or `require-corp` on Safari) headers in the block editor.
* Adds `crossorigin="anonymous"` attributes to cross-origin resources.
* Adds `credentialless` attribute to iframes so they continue working under COEP.
* Filters embed previews for providers that do not support credentialless iframes (Facebook, SmugMug).

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

== Changelog ==

= 0.1.0 =
* Initial release.
* COEP/COOP header support for Firefox and Safari.
* Credentialless iframe handling.
* Embed preview filtering for incompatible providers.
