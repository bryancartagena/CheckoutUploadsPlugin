# WooCommerce Image Upload for Checkout

This plugin allows customers to attach images during the WooCommerce checkout process for specific product categories, and displays these images in order emails and the admin dashboard.

## Description

The "WooCommerce Image Upload for Checkout" plugin adds image upload fields to the checkout page for products belonging to specific categories. This is especially useful for businesses that need their customers to provide images related to the products they're purchasing, such as:

- Custom designs for printing services
- Medical prescriptions for pharmaceutical products
- Visual references for customized services
- Any product that requires additional visual information

## Features

- **Checkout Image Upload**: Customers can upload images during the checkout process
- **Category Specific**: Define which product categories require images
- **File Validation**: Control which file types are allowed (JPG, JPEG, PNG, etc.)
- **Size Limit**: Set the maximum allowed file size
- **One Image Per Product**: Intuitive interface that allows one image per product
- **Order Display**: Uploaded images are displayed in the admin order panel
- **Email Integration**: Images are included in order confirmation emails
- **Automatic Cleanup**: Cleanup system that removes orphaned files after 24 hours

## Requirements

- WordPress 6.0 or higher (Tested up to 6.7.2)
- WooCommerce 3.0.0 or higher (Tested up to 9.7.1)
- PHP 7.4 or higher

## Installation

1. Upload the `adjuntar-imagen-woocommerce` folder to the `/wp-content/plugins/` directory of your WordPress site
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to WooCommerce → WooCommerce Image Upload to configure the plugin

## Configuration

### General Settings

1. **Product Categories**: Select which product categories will require image uploads
2. **Allowed File Types**: Select which image formats are allowed (JPG, JPEG, PNG, etc.)
3. **Maximum File Size**: Set the maximum size in MB for uploaded images

### Label Customization

- You can customize the text displayed on the checkout page
- The plugin texts are available for translation

## Usage

### For Customers

1. Customers add products to their cart as normal
2. During checkout, if the product requires an image, they will see a file upload area
3. They can drag and drop or click to select an image
4. The image is automatically saved with the order

### For Administrators

1. Attached images appear in the order details in the admin dashboard
2. They are also included in order notification emails
3. Images can be downloaded or viewed directly from the orders panel

## Security

- File type validation to prevent malicious uploads
- Size limits to avoid server issues
- Automatic cleanup system to prevent accumulation of unused files

## Support

For support or inquiries about the plugin, contact:
- [Aplicaciones Web](https://aplicacionesweb.cl)

## License

This plugin is licensed under GPL v2 or later.

## Credits

Developed by Aplicaciones Web.

---

This README was generated on April 3, 2025.
