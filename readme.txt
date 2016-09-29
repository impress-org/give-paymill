=== Give - Paymill Gateway ===
Contributors: wordimpress, dlocc, webdevmattcrom, mordauk, ramiy
Tags: donations, donation, ecommerce, e-commerce, fundraising, fundraiser, paymill, gateway
Requires at least: 3.8
Tested up to: 4.6
Stable tag: 1.1
License: GPLv3
License URI: https://opensource.org/licenses/GPL-3.0

Paymill Gateway Add-on for Give

== Description ==

This plugin requires the Give plugin activated to function properly. When activated, it adds a payment gateway for Paymill.

== Installation ==

= Minimum Requirements =

* WordPress 3.8 or greater
* PHP version 5.3 or greater
* MySQL version 5.0 or greater
* Some payment gateways require fsockopen support (for IPN access)

= Automatic installation =

Automatic installation is the easiest option as WordPress handles the file transfers itself and you don't need to leave your web browser. To do an automatic install of Give, log in to your WordPress dashboard, navigate to the Plugins menu and click Add New.

In the search field type "Give" and click Search Plugins. Once you have found the plugin you can view details about it such as the the point release, rating and description. Most importantly of course, you can install it by simply clicking "Install Now".

= Manual installation =

The manual installation method involves downloading our donation plugin and uploading it to your server via your favorite FTP application. The WordPress codex contains [instructions on how to do this here](http://codex.wordpress.org/Managing_Plugins#Manual_Plugin_Installation).

= Updating =

Automatic updates should work like a charm; as always though, ensure you backup your site just in case.


== Changelog ==

= 1.1 =
* Support for Recurring Donations Add-on

= 1.0.2 =
* Fix: 3-D Secure Payments passing incorrect amount_int value which caused transactions to fail (missing * 100 for cents amount) thanks @revolutionfrance https://wordpress.org/support/topic/paymill-bug

= 1.0.1 =
* Fix: Resolved PHP notices if the user enables the gateway but does not enter API keys

= 1.0 =
* Initial plugin release. Yippee!

