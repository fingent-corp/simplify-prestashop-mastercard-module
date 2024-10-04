# Simplify Commerce Payment module for PrestaShop

This plugin adds Simplify Commerce as a payment option on your PrestaShop checkout page.

The Simplify Commerce module is not bundled in the PrestaShop download.

## System Requirements

The latest release of the plugin has the following system requirements:

- PHP version 8.0 or higher is mandatory.
- PrestaShop version 8.0.4 or later is required. However, we strongly recommend using the latest available version of PrestaShop for optimal performance.

## Documentation

Click here to access the documentation: https://mpgs.fingent.wiki/simplify-commerce/simplify-commerce-payment-module-for-prestashop/installation/

## Support

For customer support:https://mpgsfgs.atlassian.net/servicedesk/customer/portals

## Compatibility
Versions v1.0.0 to v1.0.12
- Compatible with PrestaShop versions up to 1.4 - 1.6.

Versions v1.1.0
- Compatible with PrestaShop 1.7+.

Version v2.0.0
- Major release to remove the standard payment form integration. Only hosted payments can now be made from the plugin.

Version v2.0.1
- Fixing some plugin links.

Version v2.1.0
- Authorization + Capture modes in addition to Payment.
- Capture and Reverse (void) operation in admin.
- Updated Simplify SDK.
- Refactored dist creation.
- Cleanup.
- Making changes to make the plugin function with non ISO-8859-1 Characters (Greek, Arabic...) by processing Hosted payment fields through transit.

Version v2.2.0
- Branding Update.
- Add Embedded Payment Option.

Version v2.2.1
- The installation process always indicated failure the first time while the module was installed.
- The Tokenization option is visible for Guests on Storefront.
- Payments fail if the 3DSecure authorization is required.

Version v2.3.0
- Text update and information section removal on the plugin Configuration page.

Version v2.4.0
- Enhanced Compatibility: Compatible with PHP 8.1 & PrestaShop 8.0.x (tested with v8.0.4).
- Updated Simplify SDK Version to 1.7.0.
- Full Refund and Partial Refund Operation in Admin Panel.

Version v2.4.1
- User will be notified, if there is a new plugin version available on Git Hub.

Version v2.4.2
- Customer details are populated in transaction details in Simplify Gateway.

Version v2.4.3
- Implemented a feature to enable or disable debug logging. All communication data is encrypted and stored in a log file.
- Added void details to the order page.

## Installation
1. Make a backup of your site before applying new mods etc.
2. Download the .zip file of the latest release of the extension from https://github.com/simplifycom/simplify-prestashop-module/releases/latest
3. Go to Admin → Modules → Module Manager → Upload a Module and upload the downloaded .zip file.
4. After uploading the PrestaShop Module, click the Configure button. 

## Configuration
Please proceed with the following actions to configure the payment method:

1. Log in to your PrestaShop administration application.
2. Navigate to Modules > Module Manager.
3. Locate the ‘Mastercard Payment Gateway Services – Simplify’ extension and click on the Configure button.
4. Enter your public and private API keys into the appropriate fields for the Live and Test mode. Refer to https://www.simplify.com/commerce/docs/misc/index for details on obtaining API keys. NOTE: When using Hosted Payments, ensure to activate an API key pair specifically for Hosted Payments.
5. Select the Enable Payment Method to ‘Yes’.
6. Save Customer Details: Enable customers to save their card details securely on Simplify servers for future transactions.
7. Provide a Payment Method Title, which will be displayed to users during checkout.
8. Choose between Payment and Authorize in Transaction Mode.  
9. Select between Embedded Payment Form and Modal Payment Window in the Hosted Payment Method.
10. Map the Successful and Declined status to suit your workflow. This does not affect the Simplify Commerce configuration.
11. If multiple Payment Providers are used, adjust the Sort Order to determine their display sequence on the checkout form.
12. Option to choose Button color: Please select your preferred color from the options provided.
13. Enable/Disable the option to log data into ./var/logs/mastercard_simplify.log. All communications with the Simplify Mastercard Gateway are securely encrypted. You can also download the decrypted log file directly from the plugin configuration page.
14. Remember to enable the extension to activate the changes.

![Configuration](docs/images/configuration.png "Configuration")

## Transaction Mode

### Payment

In “Payment” mode, the customer is charged immediately when the payment is completed.

![Payment Mode](docs/images/payment-mode.png "Payment Mode")

### Authorize

In “Authorize” mode, the transaction is only authorized and the capturing of funds is a manual process that you do using the PrestaShop admin panel.

![Authorize Mode](docs/images/authorize-mode.png "Authorize Mode")

## Hosted Payment Method

### Embedded Payment Form

A secure payment form will be presented right on the checkout page. The customer can input their card details into that form and submit it to place an order.

![Embedded Payment Form](docs/images/embedded-payment-form.png "Embedded Payment Form")

### Modal Payment Window

Clicking the 'Confirm Order' button triggers the secure payment form, where customers can safely enter their card information.

![Modal Payment Window](docs/images/modal-payment-window.png "Modal Payment Window")

## Back-office Operations

### Capture Transaction

To capture the order, go to the order details page and click the "Capture Payment" button provided.

![Capture](docs/images/capture.png "Capture")

### Void Authorization

Void Authorization is used to cancel the order if the merchant finds any fraud/suspect in that order.

![Void Authorization](docs/images/void-authorization.png "Void Authorization")

### Refund / Partial Refund Transactions

When the order has been captured successfully, the "Full Refund" and "Partial Refund" buttons will be visible to the admin on the order details page.

![Refund](docs/images/refund-image.png "Refund")

