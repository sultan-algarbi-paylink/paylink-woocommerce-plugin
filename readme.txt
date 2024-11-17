=== PayLink Payment Gateway ===
Contributors: hasanayoub, algarbisultan
Requires at least: 5.5.1
Tested up to: 6.6.2
Requires PHP: 7.0
Stable tag: 3.0.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Accept popular payment methods in Saudi Arabia with PayLink Payment Gateway.

== Description ==

The PayLink Payment Gateway plugin allows you to accept popular payment methods in the Kingdom of Saudi Arabia, including mada, Visa/Mastercard, American Express, Tabby, Tamara, STC Pay, and URPay.

For more information, visit [PayLink Developer Documentation](https://developer.paylink.sa/docs/woocommerce-plugin).

== Frequently Asked Questions ==

= How do I set up the PayLink Payment Gateway? =

Please refer to the [installation guide](https://developer.paylink.sa/docs/woocommerce-plugin) for detailed instructions on setting up the PayLink Payment Gateway.

= What payment methods are supported? =

The plugin supports mada, Visa/Mastercard, American Express, Tabby, Tamara, STC Pay, URPay, and more.

== Changelog ==

= 3.0.4 =
* Revised the readme file to comply with WordPress.org guidelines, including adding missing sections such as Short Description, Frequently Asked Questions, and adjusted contributor details and included links to documentation.

= 3.0.3 =
* Simplified Configuration: Removed `callback_url` and `card_brands` from `init_form_fields`, streamlining the setup process by reducing the number of required fields.

= 3.0.2 =
* Commented out `SupportedCardBrands`.

= 3.0.1 =
* Updated the Supported Card Brands Image.
* Updated the label of Live Credentials.

= 3.0.0 =
* No need to input linking keys in the testing environment.
* If linking keys are not entered in the live environment, the payment gateway is automatically disabled.
* Error and warning messages have been improved for clarity.
* Guidance messages have been added before redirection to the payment gateway.
* An error message has been added in case of any issues during the payment process.
* Simplified transition between the testing and live environments.
* Resolved conflict issue between activating the payment gateway and WooCommerce.
* Fixed the issue of the payment gateway not appearing on the checkout page.
* Our available payment methods are displayed on the checkout page in WordPress.
* Added a notification on the checkout page if the user is in the testing environment.
* Other features related to using WordPress and WooCommerce tools more effectively and up-to-date.

== Upgrade Notice ==

= 3.0.3 =
This release simplifies the configuration process by removing unnecessary fields. It is recommended to update to this version for a smoother setup.

== Screenshots ==

1. [Plugin settings page](https://www.youtube.com/watch?v=rbshNPwPD74)
2. [Checkout page with PayLink payment options](https://www.youtube.com/watch?v=Kg_U9V0QvhM)
