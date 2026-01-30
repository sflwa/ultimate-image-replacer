=== Ultimate Image & ID Replacer ===
Contributors: sflwa
Donate link: https://github.com/sponsors/sflwa
Tags: images, search and replace, media, database, elementor, json
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Recursively replaces multiple source image URLs and Attachment IDs with a single target replacement. Safe for Serialized PHP and JSON data.

== Description ==

The Ultimate Image & ID Replacer is a developer-centric tool designed to handle complex media migrations within a WordPress database. Unlike standard search-and-replace tools, this plugin specifically targets Media IDs and URLs stored in nested, escaped, or serialized formats.

Key features:
* **JSON Aware:** Safely unpacks and repacks JSON strings (essential for modern page builders like Elementor).
* **Escape Safe:** Handles escaped slashes in URLs (e.g., https:\/\/domain.com) often found in database-stored scripts.
* **ID Swapping:** Replaces the database Attachment ID, ensuring dynamic image links are updated.
* **Media Library Integration:** Native media picker for selecting source and target assets.

== Installation ==

1. Zip the plugin folder (ensure `ultimate-image-replacer.php` is inside).
2. Go to **Plugins > Add New** in your WordPress dashboard.
3. Click **Upload Plugin** and select your ZIP file.
4. Activate the plugin.
5. Access the tool via **Tools > Image Replacer**.

== Frequently Asked Questions ==

= Does this work with Elementor? =
Yes. Elementor stores much of its layout data in JSON-encoded strings within the post meta. This plugin recursively enters those strings to perform the replacement without breaking the JSON structure.

= Should I back up my database? =
Yes. This plugin performs direct database updates. Always back up your data before performing a bulk search-and-replace.

== Changelog ==

= 2.1 =
* Standardized plugin headers and added GPL licensing.
* Added standard ZIP upload installation instructions.
* Added explicit PHP 7.4 requirement.

= 2.0 =
* Added JSON decoding/encoding logic.
* Added support for escaped forward slashes in URLs.
