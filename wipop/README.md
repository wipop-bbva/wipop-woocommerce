# Wipop

A WooCommmerce Extension inspired by [Create Woo Extension](https://github.com/woocommerce/woocommerce/blob/trunk/packages/js/create-woo-extension/README.md).

## Getting Started

### Prerequisites

-   [NPM](https://www.npmjs.com/)
-   [Composer](https://getcomposer.org/download/)
-   [wp-env](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/)

### Installation and Build

```
npm install
npm run build
wp-env start
```

Visit the added page at http://localhost:8888/wp-admin/admin.php?page=wc-admin&path=%2Fexample.

### ZIP Installation (Direct from Repository)

#### Prerequisites

- **WooCommerce**: Must be installed and activated in your WordPress setup.
Get WooCommerce [here](https://wordpress.org/plugins/woocommerce/).

#### Steps

1. Download the latest `wipop.zip` from the repository.
2. In your WordPress dashboard, go to **Plugins → Add New → Upload Plugin**.
3. Select `wipop.zip`, click **Install Now**, and then **Activate**.

###  Wipop Plugin Settings

Once the plugin is installed and activated, you can access its settings from the WordPress admin panel:

**Path:** `WooCommerce > Wipop`

| Option       | Description                                                             |
|--------------|-------------------------------------------------------------------------|
| Merchant ID  | Unique identifier provided by BBVA for your store.                     |
| Environment  | Choose between Sandbox (test mode) and Production (live mode).         |
| Public Key   | Public key provided by BBVA to sign transactions.                      |
| Private Key  | Private key provided by BBVA to authenticate communications.           |

###  Sandbox Mode

To test the integration without processing real payments:

1. Set the **Environment** to **Sandbox**.
2. Enter the test credentials provided by BBVA.
3. Place test orders to verify the payment flow.

###  Production Mode

When you're ready to go live:

1. Set the **Environment** to **Production**.
2. Enter your live credentials from BBVA.
3. Ensure your site uses **HTTPS** to protect customer data.

### Enabling Payment Methods (GPay, Cards, Bizum)

To enable payment gateways such as **Google Pay**, **Credit/Debit Cards**, or **Bizum**, follow these steps:

1. In your WordPress dashboard, go to:  
   `WooCommerce > Payments`

2. Locate the payment method you want to enable (e.g. **Google Pay**, **Credit Card**, **Bizum**).

3. Click the **"Enable"** toggle or the **"Manage"** button next to the method.

4. In the settings screen, check the box labeled **"Enable this payment method"**.

5. Click **"Save changes"** at the bottom of the page.

###  Disabling Payment Methods

To disable any payment method (e.g. Google Pay, Credit Card, Bizum) from the WooCommerce dashboard:

1. Go to:  
   `WooCommerce > Payments`

2. Locate the payment method you want to disable.

3. Click the **three-dot menu (⋮)** next to the payment method.

4. Select **"Disable"** from the dropdown menu.

> The payment method will no longer appear at checkout for your customers.

### Enabling Recurring Payments for a Product

To set up a product with recurring billing in WooCommerce:

1. Go to:  
   `WooCommerce > Products`

2. You can either:
   - Click **Add New** to create a new product, or  
   - Click **Edit** on an existing product.

3. In the **Product Data** section, look at the left-hand menu and select **Recurring Payment**.

4. Choose the **billing frequency**:
   - **Monthly**
   - **Annually**

5. Click **Publish** or **Update** to save the changes.
