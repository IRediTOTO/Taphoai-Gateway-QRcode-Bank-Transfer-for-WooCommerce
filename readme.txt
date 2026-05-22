=== Taphoai Gateway - QRcode Bank Transfer for WooCommerce ===
Contributors: Taphoai
Tags: woocommerce, payment gateway, vietqr, bank transfer, payment
Requires at least: 6.8
Tested up to: 7.0
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 1.0.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

QR code bank transfer payment gateway for WooCommerce with VietQR payment instructions and authenticated webhook payment confirmation.

== Description ==

Taphoai Gateway adds a QR code bank transfer payment method to WooCommerce. Customers see bank transfer details and a VietQR image on the order received page. The store can receive authenticated webhook notifications, match the transfer content to an order, validate the amount, and update the WooCommerce order status automatically.

Main features:

* WooCommerce bank transfer payment method.
* VietQR payment instructions on the order received page.
* Authenticated REST webhook using `Authorization: Bearer <api_key>`.
* Payment-code pool mode for natural transfer descriptions.
* Automatic order status update when payment is confirmed.
* Local WooCommerce debug logging when enabled by the merchant.
* WooCommerce Checkout Blocks integration.

Important: Configure and test the webhook on a staging site before using the gateway for real payments.

= External Services =

This plugin uses SePay image services to render VietQR images and bank logo images on the customer payment instruction page.

The VietQR image is loaded from `https://qr.sepay.vn/img`.

Data sent to this service is included in the QR image URL query string and may include:

* Bank BIN code.
* Merchant bank account number.
* Order total.
* Transfer description/payment code.

Bank logo images are loaded from `https://my.sepay.vn/assets/images/banklogo/{bank-short-name}.png`.

Data sent to this service is limited to the selected bank short name in the image URL path.

These services are used only after the merchant enables and configures this payment gateway and a customer reaches the payment instruction page. The plugin does not send debug logs or admin data to any external service.

Service documentation: https://docs.sepay.vn/woocommerce.html
Service website: https://sepay.vn/
Terms of service: https://sepay.vn/terms-of-service.html
Privacy policy: https://sepay.vn/en/privacy-policy.html

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/taphoai-gateway`, or install the plugin through the WordPress plugins screen.
2. Activate the plugin through the Plugins screen in WordPress.
3. Make sure WooCommerce is installed and active.
4. Go to WooCommerce > Settings > Payments > Bank Transfer.
5. Configure bank, account number, account holder, webhook API key, and order status.
6. If using natural payment codes, import enough available codes before enabling the gateway.
7. Configure your forwarding app/service to send bank notifications to the webhook URL shown in settings.

== Frequently Asked Questions ==

= How is the webhook authenticated? =

Webhook requests must include the configured API key in the `Authorization` header:

`Authorization: Bearer <api_key>`

= Does the plugin send debug logs to the developer? =

No. Debug logs stay in the WooCommerce log system. The plugin does not upload debug logs to an external server.

= Why does the order status polling endpoint allow guest requests? =

The order received page can be viewed by guest checkout customers. The public AJAX endpoint requires the order ID, order key, and nonce before returning order status or downloadable item information.

== Changelog ==

= 1.0.0 =

* Initial release.
* Authenticated webhook payment confirmation.
* VietQR payment instructions on the order received page.
* Payment-code pool mode for natural transfer descriptions.
* WooCommerce Checkout Blocks integration.
* Public order status polling protected with nonce and order key validation.
* Local WooCommerce logging when enabled by the merchant.
