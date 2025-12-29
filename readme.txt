=== SVG Support by thisismyurl.com ===
Contributors: thisismyurl
Author: thisismyurl
Author URI: https://thisismyurl.com/
Donate link: https://thisismyurl.com/donate/
Support Link: https://thisismyurl.com/contact/
Tags: svg, media, uploader, image, permissions
Requires at least: 5.0
Tested up to: 6.9
Stable tag: 1.251229
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
GitHub Plugin URI: https://github.com/thisismyurl/thisismyurl-svg-support/
Primary Branch: main

An educational and administrative tool to safely enable SVG uploads and management in the Media Library.

== Description ==

SVG Support by thisismyurl.com is a lightweight, high-performance tool designed to bridge the gap between vector design and WordPress web development. By default, WordPress restricts SVG uploads due to their XML-based nature; this plugin provides a secure, "Non-Destructive" framework to enable these assets.

The plugin is designed with a **Professional Showcase** architecture, ensuring it is active immediately upon installation while remaining completely clean upon uninstallation.

= Key Features =
* **Active by Default:** Enables SVG uploads immediately upon activation with no complex setup.
* **Thumbnail Fix:** Injects custom CSS to ensure SVGs render perfectly in the Media Library grid and list views.
* **Developer Focused:** Built using clean, Object-Oriented PHP logic.
* **Zero Technical Debt:** Includes an automated uninstaller to purge database options if the plugin is removed.
* **GitHub Integration:** Compatible with the FWO GitHub Updater for seamless background updates.

== Installation ==

1. Upload the `thisismyurl-svg-support` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. (Optional) Visit 'Tools > SVG Support' to toggle the upload functionality.

== Frequently Asked Questions ==

= Is this plugin secure? =
While this plugin enables the upload of SVG files, it is recommended that only trusted administrators be given upload permissions, as SVGs are XML-based and can theoretically contain scripts.

= Will this slow down my site? =
No. The plugin uses minimal hooks and only runs the CSS "fix" within the administrative dashboard, ensuring zero impact on your front-end global authority or conversion rates.

== Screenshots ==

1. The SVG Support settings page located under the Tools menu.
2. SVGs rendering correctly in the WordPress Media Library grid view.

== Changelog ==

= 1.251230 =
* Improved state management to ensure the "Active by Default" logic doesn't overwrite user-saved preferences.

= 1.251229 =
* Initial release with metabox-holder administrative UI.
* Added support for automated GitHub updates.
* Implemented register_activation_hook for default settings.

== Upgrade Notice ==

= 1.251230 =
This update improves the reliability of the settings dashboard and is recommended for all users.