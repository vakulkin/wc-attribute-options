<?php
/**
 * Plugin Name: WooCommerce Attribute Options
 * Description: Add custom options with price, title, and image for WooCommerce product attributes
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define('WC_ATTR_OPTIONS_VERSION', '1.0.0');
define('WC_ATTR_OPTIONS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WC_ATTR_OPTIONS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WC_ATTR_OPTIONS_JSON_FILE', WC_ATTR_OPTIONS_PLUGIN_DIR . 'attribute-options.json');

class WC_Attribute_Options
{
    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // Check if WooCommerce is active
        if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }

        $this->init_hooks();
    }

    private function init_hooks()
    {
        // Declare WooCommerce feature compatibility
        add_action('before_woocommerce_init', array($this, 'declare_compatibility'));

        // ACF fields
        add_action('acf/init', array($this, 'register_acf_fields'));

        // Frontend hooks
        add_action('wp_enqueue_scripts', array($this, 'frontend_enqueue_scripts'));
        add_filter('woocommerce_available_variation', array($this, 'add_options_to_variation'), 10, 3);
        add_action('woocommerce_before_add_to_cart_button', array($this, 'display_attribute_options'));
        add_action('woocommerce_after_add_to_cart_button', array($this, 'display_product_relation_buttons'));

        // Cart hooks
        add_filter('woocommerce_add_cart_item_data', array($this, 'add_cart_item_data'), 10, 3);
        add_filter('woocommerce_get_cart_item_from_session', array($this, 'get_cart_item_from_session'), 10, 2);
        add_filter('woocommerce_before_calculate_totals', array($this, 'adjust_cart_item_price'), 10, 1);
        add_filter('woocommerce_get_item_data', array($this, 'display_cart_item_options'), 10, 2);
    }

    public function register_acf_fields()
    {
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }

        acf_add_local_field_group(array(
            'key' => 'group_product_relations',
            'title' => 'Product Relations',
            'fields' => array(
                array(
                    'key' => 'field_minimum_volume_product',
                    'label' => 'Доступний у мінімальному обємі',
                    'name' => 'minimum_volume_product',
                    'type' => 'relationship',
                    'post_type' => array('product'),
                    'return_format' => 'id',
                    'max' => 1,
                ),
                array(
                    'key' => 'field_full_bottles_product',
                    'label' => 'Доступні повноцінні флакони',
                    'name' => 'full_bottles_product',
                    'type' => 'relationship',
                    'post_type' => array('product'),
                    'return_format' => 'id',
                    'max' => 1,
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'product',
                    ),
                ),
            ),
        ));
    }

    public function declare_compatibility()
    {
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
        }
    }

    public function woocommerce_missing_notice()
    {
        ?>
<div class="error">
	<p><?php _e('WooCommerce Attribute Options requires WooCommerce to be installed and active.', 'wc-attribute-options'); ?>
	</p>
</div>
<?php
    }

    public function frontend_enqueue_scripts()
    {
        if (is_product()) {
            wp_enqueue_style('wc-attr-options-frontend', WC_ATTR_OPTIONS_PLUGIN_URL . 'assets/css/frontend.css', array(), WC_ATTR_OPTIONS_VERSION);
            wp_enqueue_script('wc-attr-options-frontend', WC_ATTR_OPTIONS_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), WC_ATTR_OPTIONS_VERSION, true);
        }
    }

    public function add_options_to_variation($variation_data, $product, $variation)
    {
        $attributes = $variation->get_attributes();
        $matching_options = $this->get_matching_options($attributes);

        if (!empty($matching_options)) {
            $variation_data['attribute_options'] = array('options' => $matching_options);
        }

        return $variation_data;
    }

    public function display_attribute_options()
    {
        global $product;

        if (!$product->is_type('variable')) {
            return;
        }

        $options_data = $this->load_json_file();

        if (empty($options_data)) {
            return;
        }

        // Check if any variation has matching options
        $has_options = false;
        $variations = $product->get_available_variations();
        foreach ($variations as $variation) {
            $attributes = $variation['attributes'];
            if (!empty($this->get_matching_options($attributes))) {
                $has_options = true;
                break;
            }
        }

        if ($has_options) {
            echo '<div class="wc-attribute-options-container"></div>';
        }
    }

    public function display_product_relation_buttons()
    {
        global $product;

        if (!function_exists('get_field')) {
            return;
        }

        $minimum_volume_product = get_field('minimum_volume_product', $product->get_id());
        $full_bottles_product = get_field('full_bottles_product', $product->get_id());

        if (!empty($minimum_volume_product) || !empty($full_bottles_product)) {
            echo '<div class="product-relation-buttons">';
            echo '<div class="product-relation-or-text">' . esc_html__('АБО', 'wc-attribute-options') . '</div>';

            if (!empty($minimum_volume_product)) {
                $min_product = wc_get_product($minimum_volume_product[0]);
                if ($min_product) {
                    $min_url = get_permalink($min_product->get_id());
                    $min_price = $this->get_product_min_price($min_product);
                    $button_text = esc_html__('Доступний у мінімальному обємі', 'wc-attribute-options');
                    if ($min_price) {
                        $button_text .= ' - ' . strip_tags(wc_price($min_price));
                    }
                    echo '<a href="' . esc_url($min_url) . '" class="product-relation-button minimum-volume-button">' . $button_text . '</a>';
                }
            }

            if (!empty($full_bottles_product)) {
                $full_product = wc_get_product($full_bottles_product[0]);
                if ($full_product) {
                    $full_url = get_permalink($full_product->get_id());
                    $full_price = $this->get_product_min_price($full_product);
                    $button_text = esc_html__('Доступні повноцінні флакони', 'wc-attribute-options');
                    if ($full_price) {
                        $button_text .= ' - ' . strip_tags(wc_price($full_price));
                    }
                    echo '<a href="' . esc_url($full_url) . '" class="product-relation-button full-bottles-button">' . $button_text . '</a>';
                }
            }

            echo '</div>';
        }
    }

    private function get_product_min_price($product)
    {
        if ($product->is_type('variable')) {
            $min_price = $product->get_variation_price('min');
            return $min_price ? $min_price : $product->get_price();
        } else {
            return $product->get_price();
        }
    }

    private function load_json_file()
    {
        if (!file_exists(WC_ATTR_OPTIONS_JSON_FILE)) {
            return array();
        }

        $json_content = file_get_contents(WC_ATTR_OPTIONS_JSON_FILE);
        $data = json_decode($json_content, true);

        return is_array($data) ? $data : array();
    }

    private function save_json_file($data)
    {
        $json_content = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        return file_put_contents(WC_ATTR_OPTIONS_JSON_FILE, $json_content) !== false;
    }

    public static function get_options_data()
    {
        if (!file_exists(WC_ATTR_OPTIONS_JSON_FILE)) {
            return array();
        }

        $json_content = file_get_contents(WC_ATTR_OPTIONS_JSON_FILE);
        $data = json_decode($json_content, true);

        return is_array($data) ? $data : array();
    }

    public function add_cart_item_data($cart_item_data, $product_id, $variation_id)
    {
        if (isset($_POST['wc_attr_selected_options'])) {
            $selected_options = json_decode(stripslashes($_POST['wc_attr_selected_options']), true);
            if (!empty($selected_options)) {
                $cart_item_data['wc_attr_options'] = $selected_options;
            }
        }

        return $cart_item_data;
    }

    public function get_cart_item_from_session($cart_item, $values)
    {
        if (isset($values['wc_attr_options'])) {
            $cart_item['wc_attr_options'] = $values['wc_attr_options'];
        }

        return $cart_item;
    }

    public function adjust_cart_item_price($cart)
    {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            // Check if options were already selected
            if (!isset($cart_item['wc_attr_options'])) {
                // Check if this variation has available options
                $available_options = $this->get_available_options_for_cart_item($cart_item);

                if (!empty($available_options)) {
                    // Auto-select the first option
                    $first_option = $available_options[0];
                    $cart_item['wc_attr_options'] = array($first_option);

                    // Update cart item data to persist the selection
                    WC()->cart->cart_contents[$cart_item_key]['wc_attr_options'] = array($first_option);
                }
            }

            // Apply price adjustment for selected options
            if (isset($cart_item['wc_attr_options'])) {
                $additional_price = 0;

                foreach ($cart_item['wc_attr_options'] as $option) {
                    if (isset($option['price'])) {
                        $additional_price += floatval($option['price']);
                    }
                }

                if ($additional_price > 0) {
                    $cart_item['data']->set_price($cart_item['data']->get_price() + $additional_price);
                }
            }
        }
    }

    private function get_available_options_for_cart_item($cart_item)
    {
        // Only process variable products
        if (!isset($cart_item['variation_id']) || empty($cart_item['variation_id'])) {
            return array();
        }

        $variation = wc_get_product($cart_item['variation_id']);
        if (!$variation) {
            return array();
        }

        return $this->get_matching_options($variation->get_attributes());
    }

    private function get_matching_options($attributes)
    {
        $options_data = $this->load_json_file();

        if (empty($options_data['options'])) {
            return array();
        }

        $matching_options = array();

        // Filter options that match current variation attributes
        foreach ($options_data['options'] as $option) {
            if (!isset($option['attributes']) || empty($option['attributes'])) {
                continue;
            }

            $matches = true;

            // Check if option matches all variation attributes
            foreach ($attributes as $attr_name => $attr_value) {
                $attr_slug = str_replace('attribute_', '', $attr_name);

                if (isset($option['attributes'][$attr_slug])) {
                    if (!in_array($attr_value, $option['attributes'][$attr_slug])) {
                        $matches = false;
                        break;
                    }
                } else {
                    // If option doesn't specify this attribute, it matches all values
                    continue;
                }
            }

            if ($matches) {
                $matching_options[] = array(
                    'title' => $option['title'],
                    'price' => $option['price'],
                    'image' => $option['image']
                );
            }
        }

        return $matching_options;
    }

    public function display_cart_item_options($item_data, $cart_item)
    {
        if (isset($cart_item['wc_attr_options'])) {
            // Add base product price with "Товар" label
            $base_price = $this->get_cart_item_base_price($cart_item);

            $item_data[] = array(
                'name' => __('Товар', 'wc-attribute-options'),
                'value' => wc_price($base_price)
            );

            // Add selected options
            foreach ($cart_item['wc_attr_options'] as $option) {
                $price_text = isset($option['price']) ? wc_price($option['price']) : '';

                $item_data[] = array(
                    'name' => $option['title'],
                    'value' => $price_text
                );
            }
        }

        return $item_data;
    }

    private function get_cart_item_base_price($cart_item)
    {
        if (isset($cart_item['variation_id']) && !empty($cart_item['variation_id'])) {
            $variation = wc_get_product($cart_item['variation_id']);
            if ($variation) {
                return $variation->get_price();
            }
        } elseif (isset($cart_item['product_id']) && !empty($cart_item['product_id'])) {
            $product = wc_get_product($cart_item['product_id']);
            if ($product) {
                return $product->get_price();
            }
        }

        return 0;
    }
}

// Initialize the plugin
function wc_attribute_options_init()
{
    return WC_Attribute_Options::get_instance();
}

add_action('plugins_loaded', 'wc_attribute_options_init');
?>