# WooCommerce Attribute Options

A WordPress/WooCommerce plugin that allows you to add custom options with price, title, and image for product attributes.

## Features

- **Custom Attribute Options**: Add multiple options for each attribute value (e.g., for pa_obyem: 3ml, 5ml, 10ml)
- **Rich Option Data**: Each option can have:
  - Custom title
  - Additional price
  - Image/icon
- **Dynamic Swatches**: Options display as interactive swatches on product pages
- **Variant Integration**: Options update automatically when customers change product variants
- **Admin Interface**: Easy-to-use admin panel to configure options for all attributes

## Installation

1. Upload the `wc-attribute-options` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Make sure WooCommerce is installed and activated

## Usage

### Admin Configuration

1. Go to **WooCommerce > Attribute Options** in the WordPress admin
2. Select the attribute tab you want to configure
3. For each attribute value, click "Add Option" to create new options
4. Fill in:
   - **Title**: Name of the option (e.g., "Gift Box", "Premium Packaging")
   - **Price**: Additional cost for this option
   - **Image**: Upload an image representing this option
5. Click "Save All Options"

### Frontend Display

- Options automatically appear on variable product pages
- When a customer selects a variant, the corresponding options display as swatches
- Customers can select multiple options
- Selected options show with a checkmark and highlighted border
- Additional prices are calculated and displayed

## Example Use Case

For a perfume product with attribute `pa_obyem` (volume), you can create options that apply to multiple sizes:

```json
{
  "options": [
    {
      "title": "Travel Case",
      "price": "5.00",
      "image": "",
      "attributes": {
        "pa_obyem": ["3ml", "5ml"]
      }
    },
    {
      "title": "Premium Gift Box",
      "price": "15.00",
      "image": "",
      "attributes": {
        "pa_obyem": ["10ml", "15ml", "flakon-100ml"]
      }
    }
  ]
}
```

Each option defines which attribute values it should appear for. When a customer selects a volume, they'll see only the relevant packaging options.

## Requirements

- WordPress 5.8 or higher
- WooCommerce 5.0 or higher
- PHP 7.4 or higher

## Support

For issues and feature requests, please contact support.

## License

GPL v2 or later
