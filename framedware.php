<?php
/**
 * Plugin Name: FramedWare
 * Description: Framing plugin.
 * Author:
 * Version: 2.0.9.6
 */

define('FRAMEDWARE_ORDER_PLUGIN_VERSION', '2.0.9.6');

ini_set('xdebug.var_display_max_depth', '10');
ini_set('xdebug.var_display_max_children', '1000');
ini_set('xdebug.var_display_max_data', '1024');

set_time_limit(0);
error_reporting(E_ALL & ~(E_STRICT|E_NOTICE));

date_default_timezone_set('America/New_York');

$user_guid = null;
$order_guid = null;
$item_guid = null;
$productJSON = null;
$userInfo = null;
$support_email = 'support@frameshops.com';

use Automattic\WooCommerce\Client as WooCommerceClient;
use Automattic\WooCommerce\HttpClient\HttpClientException;
use Filestack\FilestackClient;
use Filestack\filelink;
use Filestack\FilestackSecurity;
use Intervention\Image\ImageManager;
use Intervention\Image\ImageManagerStatic as Image;
use Carbon\Carbon;

include 'adobe.php';


define( 'PLUGINPATH', WP_PLUGIN_DIR . '/framedware/' );
define( 'AJAXADMIN', admin_url( "admin-ajax.php" ) );
define( 'DOAJAXADMIN', home_url(). "/wp-content/plugins/do-ajax.php"  );

define('FRAMEDWARE_UPLOAD_PATH', ABSPATH . '/uploadhandler/uploads/');
define('FRAMEDWARE_UPLOAD_URL', get_site_url() . '/uploadhandler/uploads/');
define('FRAMEDWARE_ORDER_PATH', ABSPATH . '/uploadhandler/orders/');
define('FRAMEDWARE_SITE_URL', get_site_url());
define('FRAMEDWARE_PLUGIN_URL', plugin_dir_url( __FILE__ ));
define ('FRAMEDWARE_MIGRATIONS_PATH', WP_PLUGIN_DIR .'/framedware/database/migrations/');

// READ DATABASE
global $wpdb;
$config = $wpdb->get_var( 'SELECT `data` FROM `fware_config`');
$config = json_decode($config, true);
define ('FRAMEDWARE_LOWRES_TITLE', $config['lowres_title']);
define ('FRAMEDWARE_LOWRES_MESSAGE', $config['lowres_message']);

add_action( 'woocommerce_init', 'wc_init' );
function wc_init(){ // initialize woocommerce
    define('FRAMEDWARE_CURRENCY', get_woocommerce_currency());
    define('FRAMEDWARE_CURRENCY_SYMBOL', get_woocommerce_currency_symbol());
    define('FRAMEDWARE_UNIT_WEIGHT', get_woo_weight_unit()); // <- custom function
    define('FRAMEDWARE_UNIT_DIMENSION', get_woo_dimension_unit()); // <- custom function
}

if ( ! file_exists(FRAMEDWARE_UPLOAD_PATH)) {
    mkdir(FRAMEDWARE_UPLOAD_PATH, 0755, true);
}
if ( ! file_exists(FRAMEDWARE_ORDER_PATH)) {
    mkdir(FRAMEDWARE_ORDER_PATH, 0755, true);
}

require_once('vendor/autoload.php');

require('config.php');

// PLUGIN-UPDATE-CHECKER
$myUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
    'https://frameshops.com/framedware.json?' . time() ,
    __FILE__,
    'framedware'
);


function generateRandomString($length = 10)
{
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++)
    {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

/**
 * PRODUCT META DATA: CUSTOM PRODUCT FIELD [FRAME NUMBER] ... [START]
 */

/**
 * Display the custom text field in admin
 * @since 1.0.0
 */
function cfwc_create_custom_field() {
    $args = array(
        'id'            => 'frame_number', // custom field text field id
        'label'         => __( 'Frame number', 'cfwc' ), // custom field
        'class'			=> 'cfwc_frame_number',
        'desc_tip'      => true,
        'description'   => __( 'Frame number.', 'ctwc' ),
    );
    woocommerce_wp_text_input( $args );
}
add_action( 'woocommerce_product_options_general_product_data', 'cfwc_create_custom_field' );

/**
 * Save the custom field
 * @since 1.0.0
 */
function cfwc_save_custom_field( $post_id ) {
    $product = wc_get_product( $post_id );
    $title = isset( $_POST['frame_number'] ) ? $_POST['frame_number'] : '';
    $product->update_meta_data( 'frame_number', sanitize_text_field( $title ) );
    $product->save();
}
add_action( 'woocommerce_process_product_meta', 'cfwc_save_custom_field' );

/**
 * Display custom field on the front end
 * @since 1.0.0
 */
/*
function cfwc_display_custom_field() {

    global $post;
    // Check for the custom field value
    $product = wc_get_product( $post->ID );
    $title = $product->get_meta( 'frame_number' );
    if( $title ) {
        // Only display our field if we've got a value for the field title
        printf(
            '<div class="cfwc_frame_number-wrapper"><label for="cfwc-title-field">%s</label><input type="text" id="cfwc-title-field" name="cfwc-title-field" value=""></div>',
            esc_html( $title )
        );
    }

}
add_action( 'woocommerce_before_add_to_cart_button', 'cfwc_display_custom_field' );
*/

/**
 * Validate the text field
 * @since 1.0.0
 * @param Array 		$passed					Validation status.
 * @param Integer   $product_id     Product ID.
 * @param Boolean  	$quantity   		Quantity
 */
/*
function cfwc_validate_custom_field( $passed, $product_id, $quantity )
{
    if( empty( $_POST['cfwc-title-field'] ) ) {
        // Fails validation
        $passed = false;
        wc_add_notice( __( 'Please enter a value into the text field', 'cfwc' ), 'error' );
    }
    return $passed;
}
add_filter( 'woocommerce_add_to_cart_validation', 'cfwc_validate_custom_field', 10, 3 );
*/

/**
 * Add the text field as item data to the cart object
 * @since 1.0.0
 * @param Array 		$cart_item_data Cart item meta data.
 * @param Integer   $product_id     Product ID.
 * @param Integer   $variation_id   Variation ID.
 * @param Boolean  	$quantity   		Quantity
 */
function cfwc_add_custom_field_item_data( $cart_item_data, $product_id, $variation_id, $quantity )
{
    $product = wc_get_product( $product_id );
    $cart_item_data['frame_number'] = $product->get_meta('frame_number');
    return $cart_item_data;
}
add_filter( 'woocommerce_add_cart_item_data', 'cfwc_add_custom_field_item_data', 10, 4 );

/**
 * Update the price in the cart
 * @since 1.0.0
 */
/*
function cfwc_before_calculate_totals( $cart_obj ) {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
        return;
    }
    // Iterate through each cart item
    foreach( $cart_obj->get_cart() as $key=>$value ) {
        if( isset( $value['total_price'] ) ) {
            $price = $value['total_price'];
            $value['data']->set_price( ( $price ) );
        }
    }
}
add_action( 'woocommerce_before_calculate_totals', 'cfwc_before_calculate_totals', 10, 1 );
*/

/**
 * Display the custom field value in the cart
 * @since 1.0.0
 */
/*
function cfwc_cart_item_name( $name, $cart_item, $cart_item_key ) {

    if( isset( $cart_item['frame_number'] ) ) {
        $name .= sprintf(
            '<p>%s</p>',
            esc_html( $cart_item['frame_number'] )
        );
    }
    return $name;

}
add_filter( 'woocommerce_cart_item_name', 'cfwc_cart_item_name', 10, 3 );
*/
/**
 * DISPLAY
 * Add custom field to order object
 */
function cfwc_add_custom_data_to_order( $item, $cart_item_key, $values, $order )
{
    foreach( $item as $cart_item_key => $values ) {
        if( isset( $values['frame_number'] ) ) {
            $item->add_meta_data( __( 'Frame number', 'cfwc' ), $values['frame_number'], true );
        }
    }
}
add_action( 'woocommerce_checkout_create_order_line_item', 'cfwc_add_custom_data_to_order', 10, 4 );

/**
 * PRODUCT META DATA: CUSTOM PRODUCT FIELD [FRAME NUMBER] ... [END]
 */


/**
 * Add order item meta data
 */
add_action('woocommerce_add_order_item_meta','my_meta',1,2);
if( ! function_exists('my_meta'))
{
    function my_meta($item_id, $values)
    {
        global $woocommerce, $wpdb;
        if (isset($values['product_id'])) {
            $product = new WC_Product($values['product_id']);
            wc_add_order_item_meta($item_id, 'description', $product->get_description());
        }
    }
}

add_action( 'wp_enqueue_scripts', 'theme_enqueue_styles', PHP_INT_MAX);
function theme_enqueue_styles() {
	wp_register_style('framedware-default-css', plugin_dir_url(__FILE__) . 'assets/css/public.css' , [], FRAMEDWARE_ORDER_PLUGIN_VERSION);
	wp_enqueue_style('framedware-default-css');
}

//* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
//  Add these essential CSS style sheets at the beginning of each page
add_action('wp_enqueue_scripts', 'add_my_stylesheets');
function add_my_stylesheets() {
	wp_register_style('bootstrap-sliders-css', plugin_dir_url(__FILE__) . 'assets/css/bootstrap-sliders.css', '');
	wp_enqueue_style('bootstrap-sliders-css');

	wp_register_style('bootstrap-custom-css', plugin_dir_url(__FILE__) . 'assets/css/bootstrapcustom.css', '');
    wp_enqueue_style('bootstrap-custom-css');

	wp_register_style('bootstrap-min-css', plugin_dir_url(__FILE__) . 'assets/css/bootstrap.min.css', '');
	wp_enqueue_style('bootstrap-min-css');

	wp_register_style('bootstrap-datepicker-css', plugin_dir_url(__FILE__) . 'assets/css/bootstrap-datepicker.min.css', '');
	wp_enqueue_style('bootstrap-datepicker-css');

	wp_register_style('select-picker', plugin_dir_url(__FILE__) . 'assets/css/bootstrap-select.min.css');
	wp_enqueue_style('select-picker');

	wp_register_style('style-cropper-js-css', plugin_dir_url(__FILE__) . 'assets/css/cropper.min.css', '');
	wp_enqueue_style('style-cropper-js-css');

	wp_register_style('bootstrap-toggle-css', plugin_dir_url(__FILE__) . 'assets/css/bootstrap-toggle.min.css', '');
	wp_enqueue_style('bootstrap-toggle-css');

    wp_register_style('bootstrap-toggle-css', plugin_dir_url(__FILE__) . 'assets/css/bootstrap-toggle.min.css', '');
    wp_enqueue_style('bootstrap-toggle-css');

    wp_register_style('croppr-css', plugin_dir_url(__FILE__) . 'assets/css/croppr.min.css' , '');
    wp_enqueue_style('croppr-css');

    // Jovany admin style 

    // wp_register_style('admin-framestyle', plugin_dir_url(__FILE__) . 'assets/css/framestyle.css', '');
    // wp_enqueue_style('admin-framestyle');
}

//* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
// Add essential Javascript libraries

function my_ajax()
{
    $my_ajax = [
        'ajaxurl'      => admin_url('admin-ajax.php'),
        'do_ajax'      => DOAJAXADMIN,
        'woocommerce_cart_redirect_after_add' => get_option( 'woocommerce_cart_redirect_after_add' ),
        'woocommerce_cart_url' => wc_get_cart_url(),
        'plugin_url' => PLUGIN_URL,
    ];
    return $my_ajax;
}

add_action('admin_enqueue_scripts', 'admin_javascripts', 10);
function admin_javascripts()
{
    wp_register_script('config-js',plugin_dir_url(__FILE__) . 'config.js', ['jquery'], time(), false);
    wp_enqueue_script('config-js');
    wp_localize_script('config-js', 'myAjax', my_ajax());
    wp_enqueue_script('php-config-public', framedware_config_public()); // JS generated from database

    wp_register_script('filestack-js', '//static.filestackapi.com/filestack-js/3.x.x/filestack.min.js' , [], FRAMEDWARE_ORDER_PLUGIN_VERSION, false);
    wp_enqueue_script('filestack-js');
}

add_action('wp_enqueue_scripts', 'add_my_javascripts', 10);
function add_my_javascripts()
{
    wp_register_script('filestack-js', '//static.filestackapi.com/filestack-js/3.x.x/filestack.min.js' , [], FRAMEDWARE_ORDER_PLUGIN_VERSION, false);
    wp_enqueue_script('filestack-js');

    wp_register_script('croppr-js', plugin_dir_url(__FILE__) . 'assets/js/croppr.min.js', [], FRAMEDWARE_ORDER_PLUGIN_VERSION, false);
    wp_enqueue_script('croppr-js');

	wp_register_script('mobile-detect-js', plugin_dir_url(__FILE__) . 'assets/js/mobile-detect.min.js', [], '2.5.0', false);
	wp_enqueue_script('mobile-detect-js');

	wp_register_script('jquery-validate-js', plugin_dir_url(__FILE__) . 'assets/js/jquery.validate.min.js', ['jquery'], '1.19.0', false);
	wp_enqueue_script('jquery-validate-js');

	wp_register_script('jquery-validate-additional-methods-js', plugin_dir_url(__FILE__) . 'assets/js/additional-methods.min.js', ['jquery'], '1.19.0', false);
	wp_enqueue_script('jquery-validate-additional-methods-js');

	wp_register_script('loading-overlay-js', plugin_dir_url(__FILE__) . 'assets/js/loadingoverlay.js', ['jquery'], '2.1.6', false);
	wp_enqueue_script('loading-overlay-js');

	wp_register_script('bootstrap-datepicker-js', plugin_dir_url(__FILE__) . 'assets/js/bootstrap-datepicker.min.js' , ['jquery'], '0.10.2', false);
	wp_enqueue_script('bootstrap-datepicker-js');

    wp_register_script('bootstrap-bundle-min-js',plugin_dir_url(__FILE__) . 'assets/js/bootstrap.bundle.min.js' , ['jquery'], '1.0', false);
    wp_enqueue_script('bootstrap-bundle-min-js');

    wp_register_script('config-js',plugin_dir_url(__FILE__) . 'config.js', ['jquery'], time(), false);
    wp_enqueue_script('config-js');
    wp_localize_script('config-js', 'myAjax', my_ajax());
    wp_enqueue_script('php-config-public', framedware_config_public()); // JS generated from database

	wp_register_script('exif-js', plugin_dir_url(__FILE__) . 'assets/js/exif.min.js' , [], '2.5.0', false);
	wp_enqueue_script( 'exif-js' );

	wp_register_script('cropper-js',plugin_dir_url(__FILE__) . 'assets/js/cropper.min.js' , [], '1.0', false);
	wp_enqueue_script('cropper-js');

    // PUBLIC
    wp_register_script('framedware_public', plugin_dir_url(__FILE__) . 'assets/js/public.js', ['jquery'], FRAMEDWARE_ORDER_PLUGIN_VERSION, false);
    wp_enqueue_script('framedware_public');

	wp_register_script('js-bootstrap-toggle-js',plugin_dir_url(__FILE__) . 'assets/js/bootstrap-toggle.min.js', [], '2.2.2', false);
	wp_enqueue_script('js-bootstrap-toggle-js');

	wp_register_script('js-bootstrap-select-js',plugin_dir_url(__FILE__) . 'assets/js/bootstrap-select.min.js', [], '1.13.2', false);
	wp_enqueue_script('js-bootstrap-select-js');
}

function framedware_custom_content_after_body_open_tag()
{
    ?>
    <div class="loader-overlay">
        <span class="loader-animation"></span>
    </div>
    <?php
}
add_action('wp_body_open', 'framedware_custom_content_after_body_open_tag');

function migrate_sql($name)
{
    $sql_file = FRAMEDWARE_MIGRATIONS_PATH . $name . '.sql';
    error_log('FRAMEDWARE MIGRATION (' . $name . '): ' . $sql_file);
    error_log('FRAMEDWARE MIGRATION (' . $name . '): RUNNING ' . $name);

    if (file_exists($sql_file)) {
        error_log('FRAMEDWARE MIGRATION (' . $name . '): SQL FILE EXIST');
        $command = "mysql --user=" . DB_USER . " --password='" . DB_PASSWORD . "' -h " . DB_HOST . " -D " . DB_NAME . " < " . $sql_file;
        //error_log($command);
        exec($command, $output);
        //var_dump($output);
        error_log('FRAMEDWARE MIGRATION (' . $name . ')' . json_encode($output));
        //@unlink($sql_file);
        unset($output);
    } else {
        error_log('FRAMEDWARE MIGRATION (' . $name . '): SQL FILE *DOES NOT* EXIST');
    }
}

function migrate()
{
    global $wpdb;
    if (is_dir(FRAMEDWARE_MIGRATIONS_PATH)) {
        // Create recursive directory iterator
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(FRAMEDWARE_MIGRATIONS_PATH), RecursiveIteratorIterator::LEAVES_ONLY);
        foreach ($iterator as $name => $file) {
            // Skip directories (they would be added automatically)
            if ( ! $file->isDir()) {
                // Get real and relative path for current file
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen(FRAMEDWARE_MIGRATIONS_PATH));

                $filetypes = ['php'];
                $filetype = pathinfo($relativePath, PATHINFO_EXTENSION);
                $name = pathinfo($relativePath, PATHINFO_FILENAME);
                if (in_array(strtolower($filetype), $filetypes)) {
                    //error_log('FRAMEDWARE MIGRATION (' . $name . '): LOOKUP ' . $name);
                    //echo $filePath . '<br>' . $relativePath . '<br>' . $name . '<br>';
                    $migration = $wpdb->get_results('SELECT * FROM `fware_migrations` WHERE `migration` = "' . $name . '" LIMIT 1;');
                    //var_dump($migration);
                    if (empty($migration)) {
                        //error_log('FRAMEDWARE MIGRATION (' . $name . '): *DOES NOT* EXIST');
                        // store migration name
                        $wpdb->insert(
                            'fware_migrations',
                            [
                                'migration' => $name,
                            ],
                            ['%s']
                        );

                        // run migration
                        include $filePath;
                    } else {
                        //error_log('FRAMEDWARE MIGRATION (' . $name . '): ALREADY EXIST');
                    }
                }
            }
        }
    }
}

add_shortcode('framedeware_single_pricing', function()
{
    ob_start();
    require('views/public_single_pricing.php');
    return ob_get_clean();
});

function view_single($attributes, $content, $shortcode_name)
{
    $instance = 'filestack';

    ob_start();
    global $wpdb;
    $frames = $wpdb->get_results($wpdb->prepare("SELECT * FROM `fware_frame` LIMIT 6;"));
    $config = $wpdb->get_var( 'SELECT `data` FROM `fware_config`');
    $config = json_decode($config, true);
    require('views/public_single.php');
    return ob_get_clean();
}

add_shortcode('framedeware_single', 'view_single');
add_shortcode('framedeware_uploader', 'view_single'); // old shortcode, retained for compatibility
add_shortcode('framedeware_adobe_stock', function ($attributes, $content, $shortcode_name) // <-
{
    $instance = 'adobe';

    ob_start();
    global $wpdb;
    $frames = $wpdb->get_results($wpdb->prepare("SELECT * FROM `fware_frame` LIMIT 6;"));
    $adobe_stock = true;

    require('views/public_single.php');
    return ob_get_clean();
});

add_shortcode('framedeware_gallery_wall_1x3', function()
{
    ob_start();
    require('views/public_gallery_wall_1x3.php');
    return ob_get_clean();
});

add_shortcode('framedeware_gallery_wall_2x4', function()
{
    ob_start();
    require('views/public_gallery_wall_2x4.php');
    return ob_get_clean();
});

add_shortcode('framedeware_gallery_wall_3x3', function()
{
    ob_start();
    require('views/public_gallery_wall_3x3.php');
    return ob_get_clean();
});

add_shortcode('framedeware_gallery_wall_4x3', function()
{
    ob_start();
    require('views/public_gallery_wall_4x3.php');
    return ob_get_clean();
});

add_shortcode('framedeware_gallery_wall_stairway', function()
{
    ob_start();
    require('views/public_gallery_wall_stairway.php');
    return ob_get_clean();
});

add_shortcode('framedeware_framepro', function()
{
    ob_start();
    require('views/public_framepro.php');
    return ob_get_clean();
});

// Register the menu (Admin)
add_action( 'admin_menu', 'framedware_plugin_menu_func' );
function framedware_plugin_menu_func()
{
    global $submenu; // <--

    // /wp-admin/admin.php?page=framedware
    add_menu_page(
        'FrameShops',                   // Page title
        'FrameShops',                   // Menu title
        'manage_options',               // Minimum capability (manage_options is an easy way to target Admins)
        'framedware',                   // Menu slug
        'framedware_plugin_options',    // Callback that prints the markup
        plugin_dir_url( __FILE__ ) . 'assets/img/icon.png'
    );

    // /wp-admin/admin.php?page=framedware
    add_submenu_page(
        'framedware',
        'Options',
        'Options',
        'manage_options',
        'framedware'
    );

    // /wp-admin/admin.php?page=framedware_prices
    add_submenu_page(
        'framedware',
        'Adjust Prices',
        'Adjust Prices',
        'manage_options',
        'framedware_prices',
        'framedware_prices_function'
    );

    // /wp-admin/admin.php?page=framedware_report
    add_submenu_page(
        'framedware',
        'Reports',
        'Reports',
        'manage_options',
        'framedware_report',
        'framedware_report_function'
    );

    // /wp-admin/admin.php?page=framedware_art_input
    add_submenu_page(
        'framedware',
        'Art Upload',
        'Art Upload',
        'manage_options',
        'framedware_art_input',
        'framedware_art_input_function'
    );

    // /wp-admin/admin.php?page=framedware_art_list
    add_submenu_page(
        'framedware',
        'Art',
        'Art',
        'manage_options',
        'framedware_art_list',
        'framedware_art_list_function'
    );

    // /wp-admin/admin.php?page=framedware_pricelist_input
    add_submenu_page(
        'framedware',
        'Art Price List Input',
        'Art Price List Input',
        'manage_options',
        'framedware_pricelist_input',
        'framedware_pricelist_input_function'
    );

    // /wp-admin/admin.php?page=framedware_pricelist_list
    add_submenu_page(
        'framedware',
        'Art Price List',
        'Art Price List',
        'manage_options',
        'framedware_pricelist_list',
        'framedware_pricelist_list_function'
    );
}

/**
 * Admin Head
 */
add_action( 'admin_head', function() {
    //remove_submenu_page( 'framedware', 'framedware' );
    //remove_submenu_page( 'framedware', 'framedware_prices' );
    //remove_submenu_page( 'framedware', 'framedware_report' );
    remove_submenu_page( 'framedware', 'framedware_art_input' );
    remove_submenu_page( 'framedware', 'framedware_pricelist_input' );
});

// Admin Page
function framedware_plugin_options()
{
    require('views/admin_options.php');
}

function plugin_add_settings_link( $links )
{
    $settings_link = '<a href="admin.php?page=framedware">' . __( 'Settings' ) . '</a>';
    array_unshift( $links, $settings_link );
    return $links;
}
$plugin = plugin_basename( __FILE__ );
add_filter( "plugin_action_links_" . $plugin, 'plugin_add_settings_link' );

/**
 * Get default config data (from config.php)
 * @return array
 */
function get_default_config_data()
{
    return [
        //'currency' => FRAMEDWARE_CURRENCY,
        //'currency_symbol' => FRAMEDWARE_CURRENCY_SYMBOL,
        //'unit_weight' => FRAMEDWARE_UNIT_WEIGHT,
        //'unit_dimension' => FRAMEDWARE_UNIT_DIMENSION,
        'default_min_print_res' => FRAMEDWARE_DEFAULT_MIN_PRINT_RES,
        'minimum_print_length' => FRAMEDWARE_MINIMUM_PRINT_LENGTH,
        'frame_weight_factor' => FRAMEDWARE_FRAME_WEIGHT_FACTOR,
        'frame_size_padding' => FRAMEDWARE_FRAME_SIZE_PADDING,
        'wall_image_width' => FRAMEDWARE_WALL_IMAGE_WIDTH,
        'ui' => FRAMEDWARE_UI,
        'wall_config' => FRAMEDWARE_WALL,
        'paper' => FRAMEDWARE_PAPER,
        'mat_size' => FRAMEDWARE_MAT_SIZE,
        'skip_crop' => FRAMEDWARE_SKIP_CROP,
//        'lowres_title' => FRAMEDWARE_LOWRES_TITLE,
//        'lowres_message' => FRAMEDWARE_LOWRES_MESSAGE,
    ];
}

/**
 * Create `migrations` database table
 */
function create_migrations_table()
{
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $table_name = 'fware_migrations';
    $r = $wpdb->query("
        SELECT *
        FROM information_schema.tables
        WHERE table_schema = '" . DB_NAME . "'
        AND table_name = '" . $table_name .  "'
        LIMIT 1;");
    if ($r != 1) { // if table does not exist, create it
        $sql = "CREATE TABLE `$table_name` (
	        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	        `migration` VARCHAR(255) NULL DEFAULT NULL, 
	        PRIMARY KEY (`id`)
            )
            $charset_collate;";
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }
}

/**
 * Create `config` database table
 */
function create_config_table()
{
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    // CONFIG
    $config_data = get_default_config_data();
    $table_name = 'fware_config';
    $r = $wpdb->query("
        SELECT *
        FROM information_schema.tables
        WHERE table_schema = '" . DB_NAME . "'
        AND table_name = '" . $table_name .  "'
        LIMIT 1;");
    if ($r != 1) { // if table does not exist, create it
        $sql = "CREATE TABLE `$table_name` (data text) $charset_collate;";
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
        $wpdb->insert(
            $table_name,
            [
                'data' => json_encode($config_data),
            ],
            ['%s']
        );
    }
}

/**
 * Create `cart` database table
 */
function create_cart_table()
{
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $table_name = 'fware_cart';
    $r = $wpdb->query("
        SELECT *
        FROM information_schema.tables
        WHERE table_schema = '" . DB_NAME . "'
        AND table_name = '" . $table_name .  "'
        LIMIT 1;");
    if ($r != 1) { // if table does not exist, create it
        $sql = "CREATE TABLE `$table_name` (
	        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	        `sku` VARCHAR(255) NULL DEFAULT NULL,
	        `type` VARCHAR(255) NULL DEFAULT NULL,
	        `data` TEXT NULL,
	        `created_at` TIMESTAMP NULL,
	        PRIMARY KEY (`id`)
            )
            $charset_collate;";
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }
}

/**
 * Create `frame` database table
 */
function create_frame_table()
{
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $table_name = 'fware_frame';
    $r = $wpdb->query("
        SELECT *
        FROM information_schema.tables
        WHERE table_schema = '" . DB_NAME . "'
        AND table_name = '" . $table_name .  "'
        LIMIT 1;");
    if ($r != 1) { // if table does not exist, create it
        $sql = "CREATE TABLE `$table_name` (
	        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	        `number` VARCHAR(255) NULL DEFAULT NULL,
	        `label` VARCHAR(255) NULL DEFAULT NULL,
	        `description` VARCHAR(255) NULL DEFAULT NULL,	        
	        `width_in` DECIMAL(10,2) UNSIGNED NULL DEFAULT NULL,
	        `width_mm` DECIMAL(10,2) UNSIGNED NULL DEFAULT NULL,
	        `height_in` DECIMAL(10,2) UNSIGNED NULL DEFAULT NULL,
	        `height_mm` DECIMAL(10,2) UNSIGNED NULL DEFAULT NULL,	        	        
	        `image_box` TEXT NULL,
	        `image_width` TEXT NULL,
	        `image_height` TEXT NULL,	        
	        `image_detail_1` TEXT NULL,
	        `image_detail_2` TEXT NULL,
	        `image_detail_3` TEXT NULL,	        
	        `active` TINYINT(3) UNSIGNED NULL DEFAULT '1',	       
	        `default` TINYINT(3) UNSIGNED NULL DEFAULT '0', 
	        PRIMARY KEY (`id`)
            )
            $charset_collate;";
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }
}

register_activation_hook( __FILE__, 'framedware_create_db' );
function framedware_create_db()
{
    global $wpdb;
    $table_name = 'fware_woo';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (woo_consumer_key text, woo_consumer_secret text,  woo_category_id text) $charset_collate;";
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );

    $wpdb->insert(
        $table_name,
        [
            'woo_consumer_key' => null,
            'woo_consumer_secret' => null,
            'woo_category_id' => null,
        ],
        ['%s', '%s', '%s']
    );

    // PREPARE DATABASE TABLES
    create_migrations_table();
    create_config_table();
    create_cart_table();
    create_frame_table();
}

register_deactivation_hook( __FILE__, 'framedware_delete_db');
function framedware_delete_db()
{
    global $wpdb;
    $wpdb->query('DROP TABLE fware_config');
    $wpdb->query('DROP TABLE fware_woo');
}

add_action( 'plugins_loaded', 'framedware_loaded' );
function framedware_loaded()
{
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    // PREPARE DATABASE TABLES
    create_migrations_table();
    create_config_table();
    create_cart_table();
    create_frame_table();

    // RUN MIGRATIONS
    migrate();

    // WOO
    $table_name = 'fware_woo';
    $woo_consumer_key = $wpdb->get_var( 'SELECT woo_consumer_key FROM ' . $table_name );
    $woo_consumer_secret = $wpdb->get_var( 'SELECT woo_consumer_secret FROM ' . $table_name );
    $woo_category_id = $wpdb->get_var( 'SELECT woo_category_id FROM ' . $table_name );

    define('PLUGIN_PATH', plugin_dir_path( __FILE__ ));
    define('PLUGIN_URL', plugin_dir_url( __FILE__ ));
    define('WOO_CONSUMER_KEY', $woo_consumer_key);
    define('WOO_CONSUMER_SECRET', $woo_consumer_secret);
    define('WOO_CATEGORY_ID', $woo_category_id);

    // DEFAULT FRAME
    global $wpdb;
    $d = $wpdb->get_row('SELECT * FROM `fware_frame` WHERE `default` = "1" LIMIT 1;', 'ARRAY_A');
    define('FRAMEDWARE_FRAME_DEFAULT', $d);
}

/**
 * ATTACH PRODUCT THUMBNAIL
 */
function attach_product_thumbnail($post_id, $url, $flag)
{
    /*
     * If allow_url_fopen is enable in php.ini then use this
     */
    $image_url = $url;
    $url_array = explode('/',$url);
    $image_name = $url_array[count($url_array)-1];
    $image_data = file_get_contents($image_url); // Get image data

    /*
     * If allow_url_fopen is not enable in php.ini then use this
     */


    // $image_url = $url;
    // $url_array = explode('/',$url);
    // $image_name = $url_array[count($url_array)-1];

    // $ch = curl_init();
    // curl_setopt ($ch, CURLOPT_URL, $image_url);

    // // Getting binary data
    // curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    // curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);

    // $image_data = curl_exec($ch);
    // curl_close($ch);



    $upload_dir = wp_upload_dir(); // Set upload folder
    $unique_file_name = wp_unique_filename( $upload_dir['path'], $image_name ); //    Generate unique name
    $filename = basename( $unique_file_name ); // Create image file name

    // Check folder permission and define file location
    if( wp_mkdir_p( $upload_dir['path'] ) ) {
        $file = $upload_dir['path'] . '/' . $filename;
    } else {
        $file = $upload_dir['basedir'] . '/' . $filename;
    }

    // Create the image file on the server
    file_put_contents( $file, $image_data );

    // Check image file type
    $wp_filetype = wp_check_filetype( $filename, null );

    // Set attachment data
    $attachment = array(
        'post_mime_type' => $wp_filetype['type'],
        'post_title' => sanitize_file_name( $filename ),
        'post_content' => '',
        'post_status' => 'inherit'
    );

    // Create the attachment
    $attach_id = wp_insert_attachment( $attachment, $file, $post_id );

    // Include image.php
    require_once(ABSPATH . 'wp-admin/includes/image.php');

    // Define attachment metadata
    $attach_data = wp_generate_attachment_metadata( $attach_id, $file );

    // Assign metadata to attachment
    wp_update_attachment_metadata( $attach_id, $attach_data );

    // asign to feature image
    if( $flag == 0){
        // And finally assign featured image to post
        set_post_thumbnail( $post_id, $attach_id );
    }

    // assign to the product gallery
    if( $flag == 1 ){
        // Add gallery image to product
        $attach_id_array = get_post_meta($post_id,'_product_image_gallery', true);
        $attach_id_array .= ','.$attach_id;
        update_post_meta($post_id,'_product_image_gallery',$attach_id_array);
    }
}

/**
 * GALLERY WALL
 * Store images from filestack to local filesystem
 */
add_action('wp_ajax_nopriv_wall__store_x', 'wall__store_x');
add_action('wp_ajax_wall__store_x', 'wall__store_x');
function wall__store_x()
{
    //var_dump($_POST); exit;
    $data = $_POST;

    $path = FRAMEDWARE_UPLOAD_PATH . $data['wall']['sku'] . '/';
    mkdir($path, 0755, true);

    //  remove existing image from local filesystem, if any
    if ($_POST['remove'] !== null) {
        @unlink($path . $data['wall']['item_selected'] . '_' . $data['remove']['filename']);
        @unlink($path . $data['wall']['item_selected'] . '_' . $data['remove']['thumb_filename']);
    }

    $file = $data['filestack']['filesUploaded'][0];
    // main image
    $f = file_get_contents($file['url']);
    file_put_contents($path . $data['wall']['item_selected'] . '_' . $file['filename'], $f);
    // thumbnail
    $t = file_get_contents($file['thumb']);
    file_put_contents($path . $data['wall']['item_selected'] . '_' . $file['thumb_filename'], $t);

    header('Access-Control-Allow-Origin: *');
    header("Content-Type: application/json", true);
    echo json_encode(['success' => '1']);
    wp_die();
    return;
}

/**
 * Wall add to cart
 */
add_action('wp_ajax_nopriv_wall__add_to_cart', 'wall__add_to_cart');
add_action('wp_ajax_wall__add_to_cart', 'wall__add_to_cart');
function wall__add_to_cart()
{
    global $wpdb;

    //var_dump($_POST); exit;
    $wall = $_POST['wall'];

    if ( ! empty($wall)) {
        $sku = $wall['sku'];
        $path = FRAMEDWARE_UPLOAD_PATH . $sku . '/';
        $description = $wall['description'];
        $product = get_product_by_sku($sku);
        if ( ! $product) {
            // CREATE WOO PRODUCT
            $product = new WC_Product();
            $product->set_name($description . ' ' . $sku);
            $product->set_sku($sku);
            $product->set_description($description);
            $product->set_short_description($description);
            $product->set_regular_price($wall['price']);
            $product->set_category_ids([WOO_CATEGORY_ID]);
            $product->set_length($wall['length']);
            $product->set_width($wall['width']);
            $product->set_height($wall['height']);
            $product->set_weight($wall['weight']);
            $product->set_shipping_class_id($wall['shipping_class']);
            $product->save();

            // ATTACH PRODUCT IMAGE
            $cart_thumb = PLUGIN_PATH . '/assets/img/wall_cart_' . $wall['id'] . '.jpg';
            if (file_exists($cart_thumb)) {
                attach_product_thumbnail($product->get_id(), $cart_thumb, 0);
            }

            // ADD WOO PRODUCT TO THE CART
            WC()->cart->add_to_cart($product->get_id());
        }

        // CART REFERENCES
        $wall['cart_url'] = wc_get_cart_url();
        $wall['cart_redirect'] = get_option('woocommerce_cart_redirect_after_add');

        // STORE DATA TO DATABASE
        $table_name = 'fware_cart';
        $wpdb->delete(
            $table_name,
            [
                'sku' => $sku
            ],
            ['%s']
        );
        $wpdb->insert(
            $table_name,
            [
                'sku' => $sku,
                'type' => 'wall',
                'data' => json_encode($wall),
                'created_at' => Carbon::now()->toDateTimeString(),
            ],
            ['%s', '%s', '%s']
        );

        // INVOICE THUMBNAIL IMAGE
        @copy(PLUGIN_PATH . '/assets/img/wall_cart_' . $wall['id'] . '.jpg', $path . 'invoice.jpg');
    }

    header('Access-Control-Allow-Origin: *');
    header("Content-Type: application/json", true);
    echo json_encode($wall);
    wp_die();
    exit;
};

/**
 * FRAMEPRO
 * Store images from filestack to local filesystem
 */
add_action('wp_ajax_nopriv_framepro__store', 'framepro__store');
add_action('wp_ajax_framepro__store', 'framepro__store');
function framepro__store()
{
    //var_dump($_POST); exit;
    $data = $_POST['framepro'];

    foreach ($data as $key => $item) {
        $path = FRAMEDWARE_UPLOAD_PATH . $item['sku'] . '/';
        mkdir($path, 0755, true);
        // main image
        $f = file_get_contents($item['filestack']['url']);
        file_put_contents($path . $item['filestack']['filename'], $f);
        // thumbnail
        $t = file_get_contents($item['filestack']['thumb']);
        file_put_contents($path . $item['filestack']['thumb_filename'], $t);
        // invoice thumbnail image
        @copy($path . $item['filestack']['thumb_filename'], $path . 'invoice.jpg');
    }

    header('Access-Control-Allow-Origin: *');
    header("Content-Type: application/json", true);
    echo json_encode($data);
    wp_die();
    return;
}

/**
 * FRAMEPRO
 * Add to cart
 */
add_action('wp_ajax_nopriv_framepro__add_to_cart', 'framepro__add_to_cart');
add_action('wp_ajax_framepro__add_to_cart', 'framepro__add_to_cart');
function framepro__add_to_cart()
{
    //var_dump($_POST); exit;
    $framepro = $_POST['framepro'];

    if ( ! empty($framepro)) {
        foreach ($framepro as $key => $item) {
            $sku = $item['sku'];
            $path = FRAMEDWARE_UPLOAD_PATH . $sku . '/';

            $description = ' 
                Frame: ' . ucfirst($item['frame']). '<br>
                Mat: ' . ($item['mat'] == 1 ? 'Yes' : 'No') . '<br>
                Width: ' . $item['width'] . '" ' . $item['width_fraction'] . '<br>
                Height: ' . $item['height'] . '" ' . $item['height_fraction'] . '<br>
                Glass: ' . ($item['glass'] == 'invisible' ? 'Invisible' : 'Regular');

            $product = get_product_by_sku($sku);
            if ( ! $product) {
                // CREATE WOO PRODUCT
                $product = new WC_Product();
                $product->set_name('Framepro ' . $item['filestack']['filename']);
                $product->set_sku($sku);
                $product->set_description($description);
                $product->set_short_description($description);
                $product->set_regular_price($item['price']);
                $product->set_category_ids([WOO_CATEGORY_ID]);
                $product->save();

                // ATTACH PRODUCT IMAGE
                $cart_thumb = $path . $item['filestack']['thumb_filename'];
                error_log($cart_thumb);
                if (file_exists($cart_thumb)) {
                    attach_product_thumbnail($product->get_id(), $cart_thumb, 0);
                }

                // ADD WOO PRODUCT TO THE CART
                WC()->cart->add_to_cart($product->get_id(), $item['quantity']);
            }
        }
    }

    header('Access-Control-Allow-Origin: *');
    header("Content-Type: application/json", true);
    echo json_encode(['function' => 'framepro__add_to_cart', 'success' => '1', 'framepro' => $framepro]);
    wp_die();
    return;
};

/**
 * Calculate final (cropped) dimensions
 *
 * @param $config
 * @param $main
 * @param $thumb
 * @return array
 */
function get_final_dimensions($config, $main_width, $main_height, $thumb_width, $thumb_height)
{
    $data = [];
    $data['paper'] = $config['paper'];
    $data['width_px'] = $main_width;
    $data['height_px'] = $main_height;
    $data['width_thumb_px'] = $thumb_width;
    $data['height_thumb_px'] = $thumb_height;
    $data['print_resolution'] = $config['default_min_print_res'];
    $data['width_inch'] = round(($main_width / $config['default_min_print_res']), 2);
    $data['height_inch'] = round(($main_height / $config['default_min_print_res']), 2);
    $data['width_to_height_ratio'] = round(($main_width / $main_height), 2);
    if ($data['width_inch'] >= $data['height_inch']) {
        $data['side_long_inch'] = $data['width_inch'];
        $data['side_short_inch'] = $data['height_inch'];
        $data['orientation'] = 'landscape';
    } else {
        $data['side_long_inch'] = $data['height_inch'];
        $data['side_short_inch'] = $data['width_inch'];
        $data['orientation'] = 'portrait';
    }
    return $data;
}

/**
 * ADOBE SEARCH
 */
add_action('wp_ajax_nopriv_adobe__search', 'adobe__search');
add_action('wp_ajax_adobe__search', 'adobe__search');
function adobe__search()
{
    $input = $_POST['input'];
    $offset = $_POST['offset'];

    $data = [];
    try {
        $a = new AdobeStockController;
        $r = $a->search($input, $offset);
        if (is_array($r)) {
            foreach ($r as $item) {
                if (is_array($item)) {
                    if (isset($item['sizes']['full']['url'])) {
                        $data[] = [
                            'id' => $item['id'],
                            'title' => htmlspecialchars($item['title']),
                            'width' => $item['width'],
                            'height' => $item['height'],
                            'url_full' => $item['sizes']['full']['url'],
                            'url_thumb' => $item['sizes']['thumbnail']['url'],
                        ];
                    }
                }
            }
        }
        $output = [
            'status' => '1',
            'data' => $data,
        ];
    }
    catch (Exception $e) {
        $error = 'Caught exception: ' . $e->getMessage() . ' on line: ' . $e->getLine();
        //var_dump($error);
        $output = [
            'status' => '0',
        ];
    }

    header('Access-Control-Allow-Origin: *');
    header("Content-Type: application/json", true);
    echo json_encode($output);
    wp_die();
    exit;
}

/**
 * SINGLE STORE
 * Store images from filestack to local filesystem
 */
add_action('wp_ajax_nopriv_single__store', 'single__store');
add_action('wp_ajax_single__store', 'single__store');
function single__store()
{
    global $wpdb;
    $config = $wpdb->get_var( 'SELECT `data` FROM `fware_config`');
    $config = json_decode($config, true);

    //var_dump($_POST); exit;
    $data = $_POST['data'];

    // LOCAL
    $path = FRAMEDWARE_UPLOAD_PATH . $data['sku'] . '/';
    $url_local = FRAMEDWARE_UPLOAD_URL . $data['sku'] . '/';
    //var_dump($path); exit;
    mkdir($path, 0755, true);

    // MAIN IMAGE
    $f = file_get_contents($data['url_main']);
    file_put_contents($path . $data['filename'], $f);

    // THUMBNAIL
    $path_parts = pathinfo($path . $data['filename']);
    $data['filename_thumb'] = $path_parts['filename'] . '_thumb.' . $path_parts['extension'];
    //
    $t = file_get_contents($data['url_thumb']);
    file_put_contents($path . $data['filename_thumb'], $t);

    // DATA
    $data['step'] = 'crop'; // next step
    $data['type'] = 'single';
    $data['mode'] = '2d';
    $data['url_main_local'] = $url_local . $data['filename'];
    $data['url_thumb_local'] = $url_local . $data['filename_thumb'];
    $data['lowres'] = 'false';
    $data['lowres_px'] = null;
    $data['default_min_print_res'] = $config['default_min_print_res'];
    $data['minimum_print_length'] = $config['minimum_print_length'];
    $data['source'] = null;
    $data['mat_size'] = $config['mat_size'];
    $data['currency'] = FRAMEDWARE_CURRENCY;
    $data['currency_symbol'] = FRAMEDWARE_CURRENCY_SYMBOL;
    $data['unit_weight'] = FRAMEDWARE_UNIT_WEIGHT;
    $data['unit_dimension'] = FRAMEDWARE_UNIT_DIMENSION;
    // DEFAULT SELECTION
    $data['selection_mat'] = 'true';
    $data['selection_frame_url'] = FRAMEDWARE_PLUGIN_URL . FRAMEDWARE_FRAME_DEFAULT['image_width'];
    $data['selection_frame_side'] = FRAMEDWARE_FRAME_DEFAULT['side'];
    $data['selection_frame_side_url'] = FRAMEDWARE_PLUGIN_URL . FRAMEDWARE_FRAME_DEFAULT['image_height'];
    $data['selection_frame_name'] = 'Matt Black';
    $data['selection_invisible_glass'] = 'false';
    $data['selection_width_print'] = null;
    $data['selection_height_print'] = null;
    $data['selection_width_glass'] = null;
    $data['selection_height_glass'] = null;
    $data['selection_width_outer'] = null;
    $data['selection_height_outer'] = null;
    $data['selection_price'] = null;
    //$data['selection_grade'] = null;
    $data['selection_shipping_class'] = '';
    $data['selection_adobe_stock_retail'] = '';

    // INIT IMAGES FOR PROCESSING
    $main = Image::make($path . $data['filename']);
        $main_width = $main->width();
        $main_height = $main->height();
        if (isset($data['adobe_width'])) {
            $main_width = $data['adobe_width'];
            $main_height = $data['adobe_height'];
        }
    $thumb = Image::make($path . $data['filename_thumb']);
        $thumb_width = $thumb->width();
        $thumb_height = $thumb->height();


    // CHECK LOW RES
    $min_px = $config['default_min_print_res'] * $config['minimum_print_length'];
    $data['lowres_px'] = $min_px;
    if ($main_width < $min_px || $main_height < $min_px) {
        $data['lowres'] = 'true';
    }
    $data['original_width_px'] = $main_width;
    $data['original_height_px'] = $main_height;

    // INVOICE THUMBNAIL IMAGE
    $thumb->save($path . 'invoice.jpg', 90, 'jpg');

    // GET FINAL (CROPPED) DIMENSIONS
    $data = array_merge($data, get_final_dimensions($config, $main_width, $main_height, $thumb_width, $thumb_height));



    header('Access-Control-Allow-Origin: *');
    header("Content-Type: application/json", true);
    echo json_encode($data);
    wp_die();
    exit;
}

/**
 * SINGLE CROP
 * Crop images in local filesystem
 */
add_action('wp_ajax_nopriv_single__crop', 'single__crop');
add_action('wp_ajax_single__crop', 'single__crop');
function single__crop()
{
    global $wpdb;
    $config = $wpdb->get_var( 'SELECT `data` FROM `fware_config`');
    $config = json_decode($config, true);

    $data = $_POST['data'];

    $path = FRAMEDWARE_UPLOAD_PATH . $data['sku'] . '/';

    // INIT IMAGES FOR PROCESSING
    $main = Image::make($path . $data['filename']);
    $thumb = Image::make($path . $data['filename_thumb']);

    $crop_thumb_to_main_factor = round(($main->width() / $thumb->width()), 4);

    $data['crop_main'] = [
        'width' => round($data['crop_thumb']['width'] * $crop_thumb_to_main_factor),
        'height' => round($data['crop_thumb']['height'] * $crop_thumb_to_main_factor),
        'x' => round($data['crop_thumb']['x'] * $crop_thumb_to_main_factor),
        'y' => round($data['crop_thumb']['y'] * $crop_thumb_to_main_factor),
    ];
    $data['crop_thumb_to_main_factor'] = $crop_thumb_to_main_factor;

    // CROP
    $main->crop(
        $data['crop_main']['width'],
        $data['crop_main']['height'],
        $data['crop_main']['x'],
        $data['crop_main']['y']
    )->save();
    //
    $thumb->crop(
        $data['crop_thumb']['width'],
        $data['crop_thumb']['height'],
        $data['crop_thumb']['x'],
        $data['crop_thumb']['y']
    )->save();

    // INVOICE THUMBNAIL IMAGE
    $thumb->save($path . 'invoice.jpg', 90, 'jpg');

    // GET FINAL (CROPPED) DIMENSIONS
    $data = array_merge($data, get_final_dimensions($config, $main->width(), $main->height(), $thumb->width(), $thumb->height()));

    // NEXT STEP
    $data['step'] = 'options';



    header('Access-Control-Allow-Origin: *');
    header("Content-Type: application/json", true);
    echo json_encode($data);
    wp_die();
    exit;
}

/**
 * ART STORE
 * Store images from filestack to local filesystem
 */
add_action('wp_ajax_nopriv_art__store', 'art__store');
add_action('wp_ajax_art__store', 'art__store');
function art__store()
{
    //var_dump($_POST); exit;
    $data = $_POST['data'];

    // LOCAL
    $path = FRAMEDWARE_UPLOAD_PATH . $data['sku'] . '/';
    $url_local = FRAMEDWARE_UPLOAD_URL . $data['sku'] . '/';
    //var_dump($path); exit;
    mkdir($path, 0755, true);


    // THUMBNAIL
    $data['filename_thumb'] = $data['sku'] . '_thumb.jpg';
    $data['local_url_thumb'] = $url_local . $data['filename_thumb'];
    //
    $t = file_get_contents($data['url_thumb']);
    file_put_contents($path . $data['filename_thumb'], $t);

    // DATA
    $data['step'] = 'form'; // next step


    header('Access-Control-Allow-Origin: *');
    header("Content-Type: application/json", true);
    echo json_encode($data);
    wp_die();
    exit;
}

/**
 * ART EDIT
 */
add_action('wp_ajax_nopriv_art__update', 'art__update');
add_action('wp_ajax_art__update', 'art__update');
function art__update()
{
    // Include the menu in AJAX call
    if ( WP_NETWORK_ADMIN ) {
        require ABSPATH . 'wp-admin/network/menu.php';
    } elseif ( WP_USER_ADMIN ) {
        require ABSPATH . 'wp-admin/user/menu.php';
    } else {
        require ABSPATH . 'wp-admin/menu.php';
    }

    $id = $_POST['id'];

    global $wpdb;
    $art = $wpdb->get_row("SELECT * FROM `fware_art` WHERE id = " . $id);

    $data = [
        'url' => menu_page_url('framedware_art_input', false) . '&id=' . $art->id,
        'raw' => stripslashes($art->raw),
    ];

    header('Access-Control-Allow-Origin: *');
    header("Content-Type: application/json", true);
    echo json_encode($data);
    wp_die();
    exit;
}

// "ART" PAGE
add_action('wp_ajax_art__save', 'art__save');
function art__save()
{
    //var_dump($_POST); wp_die(); return;

    // VALIDATE INPUT
    $v = framedware_art_validate($_POST);
    if ($v != '') {
        header('Access-Control-Allow-Origin: *');
        header("Content-Type: application/json", true);
        echo json_encode(['status' => 0, 'message' => $v]);
        wp_die();
        return;
    }

    $raw = stripslashes($_POST['raw']);
    $raw_decoded = json_decode($raw, true);

    global $wpdb;

    if ($_POST['id'] !== '' && is_numeric($_POST['id']) && ctype_digit($_POST['id'])) { // UPDATE
        $wpdb->update('fware_art', [
            'raw' => $raw,
            'position' => $_POST['position'],
            'artist' => $_POST['artist'],
            'title' => $_POST['title'],
            'hi_res_filename' => $_POST['hi_res_filename'],
            'filesize_width' => $_POST['filesize_width'],
            'filesize_height' => $_POST['filesize_height'],
            'price_list' => $_POST['price_list'],
            'description' => $_POST['description'],
            'allow_max' => (isset($_POST['allow_max']) && $_POST['allow_max'] == '1') ? 1 : 0,
            'active' => (isset($_POST['active']) && $_POST['active'] == '1') ? 1 : 0,
            'in_stock' => (isset($_POST['in_stock']) && $_POST['in_stock'] == '1') ? 1 : 0,

        ],
        ['id' => $_POST['id']]);
    } else { // STORE
        $wpdb->insert('fware_art', [
            'sku' => $raw_decoded['sku'],
            'raw' => $raw,
            'position' => $_POST['position'],
            'artist' => $_POST['artist'],
            'title' => $_POST['title'],
            'hi_res_filename' => $_POST['hi_res_filename'],
            'filesize_width' => $_POST['filesize_width'],
            'filesize_height' => $_POST['filesize_height'],
            'price_list' => $_POST['price_list'],
            'description' => $_POST['description'],
            'url' => $raw_decoded['local_url_thumb'],
            'allow_max' => (isset($_POST['allow_max']) && $_POST['allow_max'] == '1') ? 1 : 0,
            'active' => (isset($_POST['active']) && $_POST['active'] == '1') ? 1 : 0,
            'in_stock' => (isset($_POST['in_stock']) && $_POST['in_stock'] == '1') ? 1 : 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    header('Access-Control-Allow-Origin: *');
    header("Content-Type: application/json", true);
    echo json_encode(['status' => 1, 'message' => 'Saved.']);
    wp_die();
    return;
}

/**
 * ART DELETE
 */
add_action('wp_ajax_nopriv_art__delete', 'art__delete');
add_action('wp_ajax_art__delete', 'art__delete');
function art__delete()
{
    $id = $_POST['id'];

    global $wpdb;
    $wpdb->delete('fware_art', ['id' => $id]);

    header('Access-Control-Allow-Origin: *');
    header("Content-Type: application/json", true);
    echo json_encode(['status' => 1, 'message' => 'Deleted.']);
    wp_die();
    exit;
}

/**
 * PRICELIST STORE
 * Store images from filestack to local filesystem
 */
add_action('wp_ajax_nopriv_pricelist__input', 'pricelist__input');
add_action('wp_ajax_pricelist__input', 'pricelist__input');
function pricelist__input()
{
    /*
    foreach ($_POST['data'] as $key => $value) {
        echo $key . ' -> ' . $value . "\n";
    }
    exit;
    */
    //var_dump($_POST); exit;

    // VALIDATE INPUT
    $v = framedware_pricelist_validate($_POST);
    if ($v != '') {
        header('Access-Control-Allow-Origin: *');
        header("Content-Type: application/json", true);
        echo json_encode(['status' => 0, 'message' => $v]);
        wp_die();
        return;
    }

    global $wpdb;

    if ($_POST['id'] !== '' && is_numeric($_POST['id']) && ctype_digit($_POST['id'])) { // UPDATE
        $wpdb->update('fware_pricelist', [
            'name' => $_POST['name'],
            'data' => json_encode($_POST['data']),
        ],
        ['id' => $_POST['id']]);
    } else { // STORE
        $wpdb->insert('fware_pricelist', [
            'name' => $_POST['name'],
            'data' => json_encode($_POST['data']),
        ]);
    }
    
    header('Access-Control-Allow-Origin: *');
    header("Content-Type: application/json", true);
    echo json_encode(['status' => 1, 'message' => 'Saved.']);
    wp_die();
    exit;
}

/**
 * PRICELIST DELETE
 */
add_action('wp_ajax_nopriv_pricelist__delete', 'pricelist__delete');
add_action('wp_ajax_pricelist__delete', 'pricelist__delete');
function pricelist__delete()
{
    $id = $_POST['id'];

    global $wpdb;
    $wpdb->delete('fware_pricelist', ['id' => $id]);

    header('Access-Control-Allow-Origin: *');
    header("Content-Type: application/json", true);
    echo json_encode(['status' => 1, 'message' => 'Deleted.']);
    wp_die();
    exit;
}

function get_product_by_sku($sku)
{
    global $wpdb;

    $product_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_sku' AND meta_value='%s' LIMIT 1", $sku ) );

    if ($product_id) {
        return new WC_Product($product_id);
    }
    return null;
}

function remove_item_from_cart($product_id)
{
    $cart = WC()->instance()->cart;

    $cart_id = $cart->generate_cart_id($product_id);
    $cart_item_id = $cart->find_product_in_cart($cart_id);
    error_log('cart_item_id= ' . $cart_item_id);

    if($cart_item_id){
        //$cart->set_quantity($cart_item_id, 0);

        WC()->cart->remove_cart_item($cart_item_id);
        return true;
    }
    return false;
}

function get_woo_weight_unit()
{
    global $wpdb;
    $result = $wpdb->get_row("SELECT option_name, option_value FROM wp_options where option_name = 'woocommerce_weight_unit'");
    return $result->option_value;
}

function get_woo_dimension_unit()
{
    global $wpdb;
    $result = $wpdb->get_row("SELECT option_name, option_value FROM wp_options where option_name = 'woocommerce_dimension_unit'");
    return $result->option_value;
}

/**
 * SINGLE ADD TO CART
 * NOTE: based on put_product_config_details
 */
add_action('wp_ajax_nopriv_single__add_to_cart', 'single__add_to_cart');
add_action('wp_ajax_single__add_to_cart', 'single__add_to_cart');
function single__add_to_cart()
{
    global $wpdb;

    $data = $_POST['data'];


    // STORE FRAMED IMAGE
    $path = FRAMEDWARE_UPLOAD_PATH . $data['sku'] . '/';
    $url_local = FRAMEDWARE_UPLOAD_URL . $data['sku'] . '/';
    mkdir($path, 0755, true);
    //
    $framed_image = $_POST['imgBase64']; // framed image
    $framed_image = str_replace('data:image/jpeg;base64,', '', $framed_image);
    $framed_image = str_replace(' ', '+', $framed_image);
    $framed_image_data = base64_decode($framed_image);
    //
    $path_parts = pathinfo($path . $data['filename']);
    $data['filename_framed'] = $path_parts['filename'] . '_framed.jpg';
    $data['url_framed_local'] = $url_local . $data['filename_framed'];
    file_put_contents($path . $data['filename_framed'], $framed_image_data);
    // resize
    /*
    $ii = Image::make($path . $data['filename_framed']);
    $ii->resize(500, 500, function ($constraint) {
        $constraint->aspectRatio();
        //$constraint->upsize();
    })->save();
    */
    // as invoice image also (overwrite non-framed)
    file_put_contents($path . 'invoice.jpg', $framed_image_data);


    // DESCRIPTION
    $price = $data['selection_price'];
    $type_f = 'Custom';
    if (strpos($data['crop_cat'], 'custom') === false) {
        $type_f = 'Express';
    }
    $print_f = $data['selection_width_print'] . ' ' . $data['unit_dimension'] . ' x ' . $data['selection_height_print'] . ' ' . $data['unit_dimension'];
    $glass_f = $data['selection_width_glass'] . ' ' . $data['unit_dimension'] . ' x ' . $data['selection_height_glass'] . ' ' . $data['unit_dimension'];
    $outer_f = $data['selection_width_outer'] . ' ' . $data['unit_dimension'] . ' x ' . $data['selection_height_outer'] . ' ' . $data['unit_dimension'];

    // CM
    //$print_f .= ' (' . round($data['selection_width_print'] * 2.54) . ' cm x ' . round($data['selection_height_print'] * 2.54) . ' cm)';
    //$glass_f .= ' (' . round($data['selection_width_glass'] * 2.54) . ' cm x ' . round($data['selection_height_glass'] * 2.54) . ' cm)';
    //$outer_f .= ' (' . round($data['selection_width_outer'] * 2.54) . ' cm x ' . round($data['selection_height_outer'] * 2.54) . ' cm)';

    $mat_f = 'none';
    if ($data['selection_mat'] == 'true') {
        //$mat_f = preg_replace("/\d+\.?\d*(\.?0+)/", "", $data['mat_size']) . ' ' . $data['unit_dimension']; // preg_replace to remove trailing zeros
        $mat_f = ($data['mat_size'] + 0) . ' ' . $data['unit_dimension']; // add 0 to string to remove trailing zeros
    }
    //error_log('--------------------------------------->' . $data['mat_size']);
    //error_log('--------------------------------------->' . $mat_f);
    $ig_f = 'Regular Glass';
    if ($data['selection_invisible_glass'] == 'true') {
        $ig_f = 'Invisible Glass';
    }
    $adobe_id = '';
    if (isset($data['adobe_id'])) { // Adobe Stock
        $adobe_id = 'ADOBE STOCK ID: ' . $data['adobe_id'];
    }
    $description = '
        Framing type: ' . $type_f . '<br>        
        Printed Image Size:<br>' . $print_f . '<br>
        Glass Size:<br>' . $glass_f . '<br>
        Outside Dimensions:<br>' . $outer_f .'<br>
        Frame Description: ' . $data['selection_frame_name'] . '<br>
        Matting: ' . $mat_f . '<br>'
        . $ig_f . '<br>'
        . $adobe_id;
    $description = rtrim($description, '<br>');

    // PACKAGE DIMENSIONS & WEIGHT
    $package_length = $data['selection_width_outer'] + $data['frame_size_padding']; // here: length = width + frame_size_padding
    $package_width = $data['selection_height_outer'] + $data['frame_size_padding']; // here: width = height + frame_size_padding
    $package_height = $data['frame_size_padding']; // here: height = frame_size_padding
    $package_weight = round($data['selection_width_outer'] * $data['selection_height_outer'] * $data['frame_weight_factor'], 2);

    // CREATE/UPDATE WOO PRODUCT
    $product = get_product_by_sku($data['sku']); // if product exists, get it and update
    if ($product) {
        //error_log('WC_PRODUCT ................................... UPDATE');

        // REMOVE ITEM FROM CART
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            if ($cart_item['product_id'] == $product->get_id()) {
                WC()->cart->remove_cart_item($cart_item_key);
            }
        }

        $product->set_description($description);
        $product->set_short_description($description);
        $product->set_regular_price($price);
        $product->set_shipping_class_id($data['selection_shipping_class']);
        $product->save();
    } else { // new
        //error_log('WC_PRODUCT ................................... NEW');
        $product = new WC_Product();
        $product->set_name($data['filename']);
        $product->set_sku($data['sku']);
        $product->set_description($description);
        $product->set_short_description($description);
        $product->set_regular_price($price);
        $product->set_category_ids([WOO_CATEGORY_ID]);
        $product->set_length($package_length);
        $product->set_width($package_width);
        $product->set_height($package_height);
        $product->set_weight($package_weight);
        $product->set_shipping_class_id($data['selection_shipping_class']);
        $product->save();
    }
    // UPDATE META DATA
    $product->update_meta_data('frame_number', $data['frame_number']); // key, value
    $product->update_meta_data('description', $description); // key, value
    if (isset($data['adobe_id'])) { // Adobe Stock
        $product->update_meta_data('adobe_id', $data['adobe_id']); // key, value
    }
    $product->save();

    //error_log($product);

    // ATTACH PRODUCT IMAGE
    attach_product_thumbnail($product->get_id(), $data['url_framed_local'], 0);

    // CART REFERENCES
    $data['cart_url'] = wc_get_cart_url();
    $data['cart_redirect'] = get_option('woocommerce_cart_redirect_after_add');

    // STORE DATA TO DATABASE
    $table_name = 'fware_cart';
    $wpdb->delete(
        $table_name,
        [
            'sku' => $data['sku']
        ],
        ['%s']
    );
    $wpdb->insert(
        $table_name,
        [
            'sku' => $data['sku'],
            'type' => 'single',
            'data' => json_encode($data),
            'created_at' => Carbon::now()->toDateTimeString(),
        ],
        ['%s', '%s', '%s']
    );

    // ADD WOO PRODUCT TO THE CART
    WC()->cart->add_to_cart($product->get_id(), 1);

    header('Access-Control-Allow-Origin: *');
    header("Content-Type: application/json", true);
    echo json_encode($data);
    wp_die();
    exit;
};

add_action('wp_ajax_nopriv_get_cart_item', 'get_cart_item');
add_action('wp_ajax_get_cart_item', 'get_cart_item');
function get_cart_item()
{
    global $wpdb;

    $sku = $_POST['sku'];

    $data = $wpdb->get_var('SELECT `data` FROM `fware_cart` WHERE `sku` = "' . $sku . '" LIMIT 1;'); // data is already json encoded string

    header('Access-Control-Allow-Origin: *');
    header("Content-Type: application/json", true);
    echo $data;
    exit();
};

add_action('wp_ajax_framedware_shippo_save', 'framedware_shippo_save');
function framedware_shippo_save()
{
    $key = $_POST['shippo_api_key'];

    global $wpdb;
    $query = "UPDATE `fware_config_x` SET `data` = %s WHERE `key` = 'shippo_api_key' LIMIT 1;";
    $wpdb->query($wpdb->prepare($query, $key));

    header('Access-Control-Allow-Origin: *');
    header("Content-Type: application/json", true);
    echo json_encode(['success' => '1']);
    wp_die();
    exit;
}

add_action('wp_ajax_framedware_db_insert_wc', 'framedware_db_insert_wc');
function framedware_db_insert_wc()
{
    $key = $_POST['consumerKey'];
    $secret = $_POST['consumerSecret'];

    try
    {
        global $wpdb;

        $site_url = get_site_url();
        
        $woocommerce = new WooCommerceClient(
            $site_url, // Your store URL
            $key, // Your consumer key
            $secret, // Your consumer secret,
            [
                'wp_api' => true, // Enable the WP REST API integration
                'version' => 'wc/v2', // WooCommerce WP REST API version
                'verify_ssl' => false,
                'timeout' => 1800,
                'query_string_auth' => true // Force Basic Authentication as query string true and using under HTTPS
            ]
        );
        
        //.//.//.//.//.//.//.//.//.//.//
        //.//.//.//.//.//.//.//.//.//.//
        // if woo product category already exists, read it
        $category_exists = false;
        $response = $woocommerce->get('products/categories');
        foreach($response as $category) {
            if ($category['name'] == 'Uploads') {
                $category_exists = true;
                $category_id = $category['id'];
            }
        }

        // if woo product category does not already exist, create it
        if ( ! $category_exists) {
            $data = [
                'name' => 'Uploads'
            ];
            $response = $woocommerce->post('products/categories', $data);
            $category_id = $response['id'];
        }
        //.//.//.//.//.//.//.//.//.//.//
        //.//.//.//.//.//.//.//.//.//.//

        $query = "UPDATE fware_woo SET woo_consumer_key = %s , woo_consumer_secret = %s, woo_category_id = %s";
        $wpdb->query($wpdb->prepare($query, $key, $secret, $category_id));
    }
    catch (Exception $e)
    {
        $error = 'Caught exception: ' . $e->getMessage() . ' on line: ' . $e->getLine();

        $error_message = $e->getMessage(); // Error message.
        $error_request = $e->getRequest(); // Last request data.
        $error_response = $e->getResponse(); // Last response data.

        error_log($error);

        header('Access-Control-Allow-Origin: *');
        header("Content-Type: application/json", true);
        echo json_encode(['success' => '0', 'msg' => $e->getMessage()]);
        wp_die();
        return;
    }

    header('Access-Control-Allow-Origin: *');
    header("Content-Type: application/json", true);
    echo json_encode(['success' => '1']);
    wp_die();
    return;
}

add_action('wp_ajax_framedware_db_delete_wc', 'framedware_db_delete_wc');
function framedware_db_delete_wc()
{
    global $wpdb;

    $query = "UPDATE fware_woo SET woo_consumer_key = null , woo_consumer_secret = null, woo_category_id = null";
    $wpdb->query($wpdb->prepare($query));

    header('Access-Control-Allow-Origin: *');
    header("Content-Type: application/json", true);
    echo json_encode(['success' => '1']);
    wp_die();
    return;
}

// Register Style (Admin)
add_action( 'admin_enqueue_scripts', 'admin_custom_scripts' );
function admin_custom_scripts()
{
    wp_enqueue_script( 'jquery' );
    wp_enqueue_script( 'jquery-ui-datepicker', array( 'jquery' ) );

    wp_register_style('jquery-ui', 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.8/themes/base/jquery-ui.css');
    wp_enqueue_style( 'jquery-ui' );

    wp_register_style( 'framedware_admin_style', plugins_url('assets/css/admin.css', __FILE__), [], FRAMEDWARE_ORDER_PLUGIN_VERSION);
    wp_enqueue_style( 'framedware_admin_style' );

    //Jovany Admin Style 

    wp_register_style( 'admin_style_frame', plugins_url('assets/css/framestyle.css', __FILE__), [], FRAMEDWARE_ORDER_PLUGIN_VERSION );
    wp_enqueue_style( 'admin_style_frame' );
    
    wp_register_style( 'admin_bootstrap_art', plugins_url('assets/css/bootstrap.min.css', __FILE__), [], FRAMEDWARE_ORDER_PLUGIN_VERSION );
    wp_enqueue_style( 'admin_bootstrap_art' );

    wp_localize_script('framedware_ajax_script', 'framedwareWriteAjax', ['ajaxurl' => admin_url('admin-ajax.php')]);
    wp_enqueue_script('framedware_ajax_script');
}

add_action( 'wp_loaded', 'framedware_wp_loaded' );
function framedware_wp_loaded()
{
    if (is_admin()) { // admin
        wp_localize_script('framedware_ajax_script', 'framedwareWriteAjax', ['ajaxurl' => admin_url('admin-ajax.php')]);
        wp_enqueue_script('jquery');
        wp_enqueue_script('framedware_ajax_script');
    }
}

function framedware_config_public()
{
    global $post;
    global $wpdb;
    $data = $wpdb->get_var( 'SELECT `data` FROM `fware_config`');
    $data_o = $data;
    $data = json_decode($data, true);
    // drop "inactive" items from the output
    if(is_array($data['paper'])) {
        foreach ($data['paper'] as $ratio => $list) {
            if(is_array($list)) {
                foreach ($list as $price => $item) {
                    if (is_array($item)) {
                        if (isset($item['active']) && $item['active'] == '0') {
                            unset($data['paper'][$ratio][$price]);
                        }
                    }
                }
            }
        }
    }

    // keep this for backward compatibility with old version that used this `wall_pricing_formatted` variable
    $data['wall_pricing_formatted'] = [];
    if (is_array($data['wall'])) {
        foreach ($data['wall'] as $w => $list) {
            $data['wall_pricing_formatted'][$w] = FRAMEDWARE_CURRENCY_SYMBOL . $list['price'];
        }
    }
    //

    $output = '';
    $output .= '<script type="text/javascript">' . "\n";
    $output .= 'var framedware_version = \'' . FRAMEDWARE_ORDER_PLUGIN_VERSION . '\';' . "\n";
    $output .= 'var framedware_config = ' . $data_o . ';' . "\n";
    $output .= 'var currency = "' . FRAMEDWARE_CURRENCY . '";' . "\n";
    $output .= 'var currency_symbol = "' . (string) FRAMEDWARE_CURRENCY_SYMBOL . '";' . "\n";
    $output .= 'var unit_weight = "' . FRAMEDWARE_UNIT_WEIGHT . '";' . "\n";
    $output .= 'var unit_dimension = "' . FRAMEDWARE_UNIT_DIMENSION . '";' . "\n";
    $output .= 'var $default_min_print_res = ' . $data['default_min_print_res'] . ';' . "\n";
    $output .= 'var $minimum_print_length = ' . $data['minimum_print_length'] . ';' . "\n";
    $output .= 'var frame_weight_factor = ' . $data['frame_weight_factor'] . ';' . "\n";
    $output .= 'var frame_size_padding = ' . $data['frame_size_padding'] . ';' . "\n";
    $output .= 'var wall_image_width = ' . $data['wall_image_width'] . ';' . "\n";
    $output .= 'const ui = ' . json_encode($data['ui']) . ';' . "\n";
    $output .= 'var wall_config = ' . json_encode($data['wall']) . ';' . "\n";
    $output .= 'var wall_pricing_formatted = ' . json_encode($data['wall_pricing_formatted']) . ';' . "\n"; // keep this for backward compatibility with old version that used this `wall_pricing_formatted` variable
    $output .= 'var $paper = ' . json_encode($data['paper']) . ';' . "\n";
    $output .= 'var mat_size = ' . json_encode($data['mat_size']) . ';' . "\n";
    $output .= 'var skip_crop = ' . json_encode($data['skip_crop']) . ';' . "\n";
    $output .= 'var use_adobe = ' . ((is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'framedeware_adobe_stock')) ? '1' : '0') . ';' . "\n";
    $output .= '</script>';
    echo $output;
}

function framedware_config_admin()
{
    global $wpdb;
    $data = $wpdb->get_var( 'SELECT `data` FROM `fware_config`');
    $output = '';
    $output .= '<script type="text/javascript">' . "\n";
    $output .= 'var framedware_config_admin = ' . $data . ';';
    $output .= '</script>';
    echo $output;
}

add_action( 'admin_footer', 'framedware_admin_footer' );
function framedware_admin_footer()
{
    wp_enqueue_script('framedware_admin_script', framedware_config_admin());
    wp_register_script('framedware_admin_script', plugins_url('assets/js/admin.js', __FILE__), ['jquery'], FRAMEDWARE_ORDER_PLUGIN_VERSION);
    wp_enqueue_script('framedware_admin_script');
    //wp_localize_script('framedware_admin_script', 'framedware_config_admin', framedware_config_admin());
}

/**
 * Delete Woocommerce product and its attachment images.
 * (internally deletes WP post)
 *
 * @param $product_id
 */
function woo_delete_product($product_id)
{
    global $wpdb;
    $arg = [
        'post_parent' => $product_id,
        'post_type'   => 'attachment',
        'numberposts' => -1,
        'post_status' => 'any'
    ];
    $children = get_children($arg);
    if($children) {
        foreach ($children as $attachment) {
            //echo $attachment->ID . "<br>";
            wp_delete_attachment($attachment->ID, true);
            $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE post_id = " . $attachment->ID);
            wp_delete_post($attachment->ID, true); // delete attachments
        }
    }
    wp_delete_post($product_id, true); // delete product
}

/**
 * Check if directory is empty
 *
 * @param $dir
 * @return bool|null
 */
function is_dir_empty($dir) {
    if ( ! is_readable($dir)) return null;
    return (count(scandir($dir)) == 2);
}

/**
 *  Recursively delete a directory and its entire contents (files + sub dirs).
 *
 * @param $dir
 */
function rrmdir($dir) {
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (is_dir($dir . DIRECTORY_SEPARATOR . $object) && ! is_link($dir ."/" . $object)) {
                    @rrmdir($dir . DIRECTORY_SEPARATOR . $object);
                }
                else {
                    @unlink($dir . DIRECTORY_SEPARATOR . $object);
                }
            }
        }
        @rmdir($dir);
    }
}

/**
 * Get array value if it exist, and append a separator.
 *
 * @param $value
 * @param string $separator
 * @return string
 */
function retrieve($value, $separator = '')
{
    $output = '';
    if (isset($value) && ! empty($value)) {
        $output .= $value . $separator;
    }
    return $output;
}

/**
 * Create order invoice.
 *
 * @param $e Order data array
 * @return false|string
 */
function create_invoice($order, $ups = false)
{
    $invoice = 'Invoice ' . $order->get_id() . ' for order ' . $order->get_id();

    $billing_address = '';
    $billing_address .= retrieve($order->get_billing_first_name(), ' ');
    $billing_address .= retrieve($order->get_billing_last_name(), "<br>\n");
    $billing_address .= retrieve($order->get_billing_company(), "<br>\n");
    $billing_address .= retrieve($order->get_billing_address_1(), ' ');
    $billing_address .= retrieve($order->get_billing_address_2(), "<br>\n");
    $billing_address .= retrieve($order->get_billing_city(), ', ');
    $billing_address .= retrieve($order->get_billing_state(), ', ');
    $billing_address .= retrieve($order->get_billing_postcode(), ', ');
    $billing_address .= retrieve($order->get_billing_country(), "<br>\n");
    $billing_address .= retrieve($order->get_billing_email(), "<br>\n");
    $billing_address .= retrieve($order->get_billing_phone(), '');

    $shipping_address = '';
    $shipping_address .= retrieve($order->get_shipping_first_name(), ' ');
    $shipping_address .= retrieve($order->get_shipping_last_name(), "<br>\n");
    $shipping_address .= retrieve($order->get_shipping_company(), "<br>\n");
    $shipping_address .= retrieve($order->get_shipping_address_1(), ' ');
    $shipping_address .= retrieve($order->get_shipping_address_2(), "<br>\n");
    $shipping_address .= retrieve($order->get_shipping_city(), ', ');
    $shipping_address .= retrieve($order->get_shipping_state(), ', ');
    $shipping_address .= retrieve($order->get_shipping_postcode(), ', ');
    $shipping_address .= retrieve($order->get_shipping_country(), '');

    $shipping_method = '';
    $shipping_method .= '<strong>' . retrieve($order->get_shipping_method(), "<br>\n") . '</strong>';
    foreach($order->get_shipping_methods() as $item_id => $item){
        $meta = $item->get_meta_data();
        foreach ($meta as $item) {
            if ($item->key == '_pickup_location_name') {
                $shipping_method .= $item->value . "<br>\n";
            }
            if ($item->key == '_pickup_location_address') {
                if (is_array($item->value)) {
                    $shipping_method .= retrieve($item->value['address_1'], "<br>\n");
                    $shipping_method .= retrieve($item->value['address_2'], "<br>\n");
                    $shipping_method .= retrieve($item->value['city'], ', ');
                    $shipping_method .= retrieve($item->value['state'], ', ');
                    $shipping_method .= retrieve($item->value['postcode'], ', ');
                    $shipping_method .= retrieve($item->value['country'], '');
                }
            }
        }
    }

    $subtotal = 0;
    foreach ($order->get_items() as $item) {
        $subtotal += $item->get_subtotal();
    }

    ob_start();
    include PLUGINPATH . '/views/invoice.php';
    $output = ob_get_clean();

    return $output;
}



add_action('parse_request', 'my_custom_url_handler');
function my_custom_url_handler()
{
    $site_url = get_site_url();

    // TEST
    if (strpos($_SERVER["REQUEST_URI"], '/framedware/rrr') !== false)
    {



        //echo 'nin'; exit;





        // SHIPPO
        global $wpdb;
        $shippo_api_key = $wpdb->get_var( "SELECT `data` FROM `fware_config_x` WHERE `key` = 'shippo_api_key' LIMIT 1");
        Shippo::setApiKey($shippo_api_key);

        // ORDER
        $order = wc_get_order(6608);



//        {
//            "carrier": "ups",
//    "account_id": "myupsuser", // UPS user ID
//    "parameters": {
//            "password": "HipposDontLie!", // UPS password
//        "account_number": "AB1234", // UPS account number
//        "surepost": false, // Add Surepost rating (optional)
//        "cost_center": "shippo", // Mail Innovations cost center (optional)
//        "usps_endorsement": "3" // Mail Innovations USPS endorsement (optional)
//    },
//    ...
//}

//        $ups_account = Shippo_CarrierAccount::create(array(
//            'carrier' => 'ups',
//            'account_id' => 'test_2',  // UPS user ID
//            'parameters' => [
//                'password' => 'test', // UPS password
//                'account_number' => 'test', // UPS account number
//                'surepost' => '', // Add Surepost rating (optional)
//                'cost_center' => '', // Mail Innovations cost center (optional)
//                'usps_endorsement' => '' // Mail Innovations USPS endorsement (optional)
//            ],
//            'test' => true,
//            'active' => true,
//        ));
        //var_dump($ups_account); exit;





        // List all carrier accounts
        $ca = Shippo_CarrierAccount::all();
        //var_dump($ca->results); exit;

        if (is_array($ca->results)) {
            foreach ($ca->results as $item) {
                var_dump($item->carrier . ' -> ' . $item->object_id);
            }
        }




        exit;





        // WOOCOMMERCE STORE INFORMATION
        $store_address     = get_option( 'woocommerce_store_address' );
        $store_address_2   = get_option( 'woocommerce_store_address_2' );
        $store_city        = get_option( 'woocommerce_store_city' );
        $store_postcode    = get_option( 'woocommerce_store_postcode' );
        // The country/state
        $store_raw_country = get_option( 'woocommerce_default_country' );
        // Split the country/state
        $split_country = explode( ":", $store_raw_country );
        // Country and state separated:
        $store_country = $split_country[0];
        $store_state   = $split_country[1];

        $fromAddress = [
            'name' => 'Frameshop',
            'company' => 'Frameshop',
            'street1' => $store_address,
            'street2' => $store_address_2,
            'city' => $store_city,
            'state' => $store_state,
            'zip' => $store_postcode,
            'country' => $store_country,
            'phone' => '+1 212 570 5710',
            'email' => 'artsyjoe@gmail.com'
        ];
        //var_dump($fromAddress); exit;

        $toAddress = [
            'name' => $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),
            'company' => $order->get_shipping_company(),
            'street1' => $order->get_shipping_address_1(),
            'street2' => $order->get_shipping_address_2(),
            'city' => $order->get_shipping_city(),
            'state' => $order->get_shipping_state(),
            'zip' => $order->get_shipping_postcode(),
            'country' => $order->get_shipping_country(),
            'phone' => $order->get_billing_phone(), // [billing]
            'email' => $order->get_billing_email(), // [billing]
        ];
        //var_dump($toAddress); exit;

        $items = $order->get_items();
        //var_dump($items);

        if ( ! empty($items)) {
            $item_first = $items[array_key_first($items)];
            $product = wc_get_product($item_first->get_product_id());
            //var_dump($product->get_width());
        }

        $parcel = [
            'length'=> $product->get_length(),
            'width'=> $product->get_width(),
            'height'=> $product->get_height(),
            'distance_unit'=> get_woo_dimension_unit(),
            'weight'=> $product->get_weight(),
            'mass_unit'=> substr(get_woo_weight_unit(), 0, 2),
        ];
        //var_dump($parcel); exit;

        $shipment = [
            'address_from'=> $fromAddress,
            'address_to'=> $toAddress,
            'parcels'=> [$parcel],
        ];
        //var_dump($shipment); exit;

        $transaction = Shippo_Transaction::create( [
                'shipment' => $shipment,
                //'carrier_account' => 'b741b99f95e841639b54272834bc478c',
                'carrier_account' => '5e29d98fd9dc44109a54214afa7d31f7',
                'servicelevel_token' => 'usps_priority',
                'label_file_type' => "PNG",
            ]
        );
        var_dump($transaction); exit;


//        // CREATE INVOICE
//        $invoice = @create_invoice($order, true);
//        if ( ! empty($invoice)) {
//            file_put_contents($order_path . 'invoice.html', $invoice);
//        }

        exit;


        //Shippo::setApiKey("shippo_live_dada2363c3e3b61874cb2fbdbbd81f78d2fa20fe");

//        $fromAddress = array(
//            'name' => 'Shawn Ippotle',
//            'street1' => '215 Clayton St.',
//            'city' => 'San Francisco',
//            'state' => 'CA',
//            'zip' => '94117',
//            'country' => 'US'
//        );
//
//        $toAddress = array(
//            'name' => 'Mr Hippo"',
//            'street1' => 'Broadway 1',
//            'city' => 'New York',
//            'state' => 'NY',
//            'zip' => '10007',
//            'country' => 'US',
//            'phone' => '+1 555 341 9393'
//        );
//
//        $parcel = array(
//            'length'=> '5',
//            'width'=> '5',
//            'height'=> '5',
//            'distance_unit'=> 'in',
//            'weight'=> '2',
//            'mass_unit'=> 'lb',
//        );
//
//        $shipment = Shippo_Shipment::create( array(
//                'address_from'=> $fromAddress,
//                'address_to'=> $toAddress,
//                'parcels'=> array($parcel),
//                'async'=> false
//            )
//        );
//
//        var_dump($shipment);
//        exit;


        $fromAddress = array(
            'name' => 'Big Apple Art Gallery & Framing',
            'company' => 'Big Apple Art Gallery & Framing',
            'street1' => '7 Kulick Rd',
            'city' => 'Fairfield',
            'state' => 'NJ',
            'zip' => '07004',
            'country' => 'US',
            'phone' => '+1 212 570 5710',
            'email' => 'artsyjoe@gmail.com'
        );

        $toAddress = array(
            'name' => 'Studio ADT',
            'company' => 'Studio ADT',
            'street1' => '505 E Buckeye Rd',
            'street2' => '',
            'city' => 'Phoenix',
            'state' => 'AZ',
            'zip' => '85004',
            'country' => 'US',
            'phone' => '+1 480 491 2606',
            'email' => 'test@gmail.com'
        );

        $parcel = array(
            'length'=> '5',
            'width'=> '5',
            'height'=> '5',
            'distance_unit'=> 'in',
            'weight'=> '2',
            'mass_unit'=> 'lb',
        );

        $shipment = array(
            'address_from'=> $fromAddress,
            'address_to'=> $toAddress,
            'parcels'=> array($parcel),
        );

        $transaction = Shippo_Transaction::create( array(
                'shipment' => $shipment,
                //'carrier_account' => 'b741b99f95e841639b54272834bc478c',
                'carrier_account' => '5e29d98fd9dc44109a54214afa7d31f7',
                'servicelevel_token' => 'usps_priority',
                'label_file_type' => "PNG",
            )
        );

        var_dump($transaction);

        exit;
    }

    // Adobe Stock test
    if (strpos($_SERVER["REQUEST_URI"], '/framedware/aaa') !== false)
    {
        try {
            //$client = new AdobeStock('f875de6faead4cad9152274454491d80', 'Adobe Stock Lib/1.0.0', 'STAGE', new  HttpClient());
            //var_dump($client);

            $a = new AdobeStockController;
            //$r = $a->search('bird');
            $r = $a->search('new york city');
            //$r = $a->search('254472908');
            //$r = $a->search('254472908');
            var_dump($r);

            if (is_array($r)) {
                foreach ($r as $item) {
                    if (is_array($item)) {
//                        if (isset($item['sizes']['medium']['url'])) {
//                            //echo $item['sizes']['medium']['url'];
//                            echo '<img style="width:500px; height: 500px; " src="' . $item['sizes']['medium']['url'] . '"><br>' . "\n";
//                        }
                        if (isset($item['sizes']['full']['url'])) {
                            //echo $item['sizes']['medium']['url'];
                            echo '<img style="width:1000px; height: auto; margin: 0 0 10px 0;" src="' . $item['sizes']['full']['url'] . '"><br>' . "\n";
                        }
                    }
                }
            }
        }
        catch (Exception $e) {
            $error = 'Caught exception: ' . $e->getMessage() . ' on line: ' . $e->getLine();
            var_dump($error);
        }

        exit;
    }

    // TEST admin config data
    if (strpos($_SERVER["REQUEST_URI"], '/framedware/nnn') !== false)
    {
        $json = '{"default_min_print_res":"100","minimum_print_length":"5","ui":{"custom_custom":1,"express_1_1":1,"express_3_2":1,"express_4_3":1,"express_16_9":0},"wall_pricing":{"1x3":"450","2x4":"950","3x3":"700","4x3":"1090","stairway":"850"},"paper":{"custom_custom":[{"long_side":"3","short_side":"1","invisible_glass_price":"1","active":"1","shipping_class":"","adobe_stock_retail":"1"},{"long_side":"5","short_side":"5","invisible_glass_price":"20","active":"1","shipping_class":"183","adobe_stock_retail":"40"},{"long_side":"7","short_side":"5","invisible_glass_price":"25","active":"1","shipping_class":"184","adobe_stock_retail":"40"},{"long_side":"12","short_side":"9","invisible_glass_price":"25","active":"1","shipping_class":"183","adobe_stock_retail":"4"},{"long_side":"13","short_side":"13","invisible_glass_price":"100","active":"1","shipping_class":"","adobe_stock_retail":"100"},{"long_side":"17","short_side":"15","invisible_glass_price":"100","active":"1","shipping_class":"","adobe_stock_retail":"100"},{"long_side":"18","short_side":"12","invisible_glass_price":"50","active":"1","shipping_class":"184","adobe_stock_retail":"21"},{"long_side":"21","short_side":"19","invisible_glass_price":"11","active":"1","shipping_class":"","adobe_stock_retail":"11"},{"long_side":"24","short_side":"18","invisible_glass_price":"50","active":"1","shipping_class":"","adobe_stock_retail":"40"},{"long_side":"33","short_side":"31","invisible_glass_price":"100","active":"1","shipping_class":"","adobe_stock_retail":"100"},{"long_side":"34","short_side":"24","invisible_glass_price":"100","active":"1","shipping_class":"","adobe_stock_retail":"40"}],"express_1_1":[{"long_side":"7","short_side":"7","invisible_glass_price":"25","active":"1","shipping_class":"","adobe_stock_retail":"40"},{"long_side":"12","short_side":"12","invisible_glass_price":"25","active":"1","shipping_class":"","adobe_stock_retail":"40"},{"long_side":"16","short_side":"16","invisible_glass_price":"50","active":"1","shipping_class":"","adobe_stock_retail":"40"}],"express_3_2":[{"long_side":"9","short_side":"6","invisible_glass_price":"25","active":"1","shipping_class":"","adobe_stock_retail":"40"},{"long_side":"15","short_side":"10","invisible_glass_price":"25","active":"1","shipping_class":"","adobe_stock_retail":"40"},{"long_side":"21","short_side":"14","invisible_glass_price":"50","active":"1","shipping_class":"","adobe_stock_retail":"40"},{"long_side":"30","short_side":"20","invisible_glass_price":"70","active":"1","shipping_class":"","adobe_stock_retail":"40"},{"long_side":"36","short_side":"24","invisible_glass_price":"250","active":"1","shipping_class":"","adobe_stock_retail":"40"}],"express_4_3":[{"long_side":"12","short_side":"9","invisible_glass_price":"25","active":"1","shipping_class":"","adobe_stock_retail":"40"},{"long_side":"16","short_side":"12","invisible_glass_price":"25","active":"1","shipping_class":"","adobe_stock_retail":"40"},{"long_side":"20","short_side":"15","invisible_glass_price":"50","active":"1","shipping_class":"","adobe_stock_retail":"40"},{"long_side":"30","short_side":"24","invisible_glass_price":"100","active":"1","shipping_class":"","adobe_stock_retail":"40"}],"express_16_9":[{"long_side":"8","short_side":"6","invisible_glass_price":"25","active":"1","shipping_class":"","adobe_stock_retail":"40"},{"long_side":"12","short_side":"9","invisible_glass_price":"25","active":"1","shipping_class":"","adobe_stock_retail":"40"},{"long_side":"16","short_side":"12","invisible_glass_price":"25","active":"1","shipping_class":"","adobe_stock_retail":"40"},{"long_side":"20","short_side":"15","invisible_glass_price":"50","active":"1","shipping_class":"","adobe_stock_retail":"40"}]},"frame_weight_factor":"0.03","frame_size_padding":"5","wall_image_width":"156","skip_crop":0,"mat_size":"2.00","lowres_title":"Low resolution","lowres_message":"The selected file is a bit too small to print.","ui_3d":0}';
        $r = json_decode($json, true);
        var_dump($r);
        exit;
    }

    // TEST admin config `paper` data sorting
    if (strpos($_SERVER["REQUEST_URI"], '/framedware/iii') !== false)
    {
        $data = 'a:5:{s:13:"custom_custom";a:7:{i:1;a:7:{s:9:"long_side";s:1:"3";s:5:"price";s:1:"1";s:10:"short_side";s:1:"1";s:21:"invisible_glass_price";s:1:"1";s:14:"shipping_class";s:0:"";s:18:"adobe_stock_retail";s:1:"1";s:6:"active";s:1:"1";}i:2;a:7:{s:9:"long_side";s:1:"5";s:5:"price";s:2:"39";s:10:"short_side";s:1:"5";s:21:"invisible_glass_price";s:2:"20";s:14:"shipping_class";s:3:"183";s:18:"adobe_stock_retail";s:2:"40";s:6:"active";s:1:"1";}i:3;a:7:{s:9:"long_side";s:1:"7";s:5:"price";s:2:"65";s:10:"short_side";s:1:"5";s:21:"invisible_glass_price";s:2:"25";s:14:"shipping_class";s:3:"184";s:18:"adobe_stock_retail";s:2:"40";s:6:"active";s:1:"1";}i:4;a:7:{s:9:"long_side";s:2:"12";s:5:"price";s:2:"85";s:10:"short_side";s:1:"9";s:21:"invisible_glass_price";s:2:"25";s:14:"shipping_class";s:3:"183";s:18:"adobe_stock_retail";s:1:"4";s:6:"active";s:1:"1";}i:5;a:7:{s:9:"long_side";s:2:"18";s:5:"price";s:2:"99";s:10:"short_side";s:2:"12";s:21:"invisible_glass_price";s:2:"50";s:14:"shipping_class";s:3:"184";s:18:"adobe_stock_retail";s:2:"21";s:6:"active";s:1:"1";}i:6;a:7:{s:9:"long_side";s:2:"24";s:5:"price";s:3:"145";s:10:"short_side";s:2:"18";s:21:"invisible_glass_price";s:2:"50";s:14:"shipping_class";s:0:"";s:18:"adobe_stock_retail";s:2:"40";s:6:"active";s:1:"1";}i:7;a:7:{s:9:"long_side";s:2:"34";s:5:"price";s:3:"179";s:10:"short_side";s:2:"24";s:21:"invisible_glass_price";s:3:"100";s:14:"shipping_class";s:0:"";s:18:"adobe_stock_retail";s:2:"40";s:6:"active";s:1:"1";}}s:11:"express_1_1";a:3:{i:1;a:7:{s:9:"long_side";s:1:"7";s:5:"price";s:2:"85";s:10:"short_side";s:1:"7";s:21:"invisible_glass_price";s:2:"25";s:14:"shipping_class";s:0:"";s:18:"adobe_stock_retail";s:2:"40";s:6:"active";s:1:"1";}i:2;a:7:{s:9:"long_side";s:2:"12";s:5:"price";s:2:"99";s:10:"short_side";s:2:"12";s:21:"invisible_glass_price";s:2:"25";s:14:"shipping_class";s:0:"";s:18:"adobe_stock_retail";s:2:"40";s:6:"active";s:1:"1";}i:3;a:7:{s:9:"long_side";s:2:"16";s:5:"price";s:3:"145";s:10:"short_side";s:2:"16";s:21:"invisible_glass_price";s:2:"50";s:14:"shipping_class";s:0:"";s:18:"adobe_stock_retail";s:2:"40";s:6:"active";s:1:"1";}}s:11:"express_3_2";a:5:{i:1;a:7:{s:9:"long_side";s:1:"9";s:5:"price";s:2:"85";s:10:"short_side";s:1:"6";s:21:"invisible_glass_price";s:2:"25";s:14:"shipping_class";s:0:"";s:18:"adobe_stock_retail";s:2:"40";s:6:"active";s:1:"1";}i:2;a:7:{s:9:"long_side";s:2:"15";s:5:"price";s:2:"99";s:10:"short_side";s:2:"10";s:21:"invisible_glass_price";s:2:"25";s:14:"shipping_class";s:0:"";s:18:"adobe_stock_retail";s:2:"40";s:6:"active";s:1:"1";}i:3;a:7:{s:9:"long_side";s:2:"21";s:5:"price";s:3:"145";s:10:"short_side";s:2:"14";s:21:"invisible_glass_price";s:2:"50";s:14:"shipping_class";s:0:"";s:18:"adobe_stock_retail";s:2:"40";s:6:"active";s:1:"1";}i:4;a:7:{s:9:"long_side";s:2:"30";s:5:"price";s:3:"179";s:10:"short_side";s:2:"20";s:21:"invisible_glass_price";s:2:"70";s:14:"shipping_class";s:0:"";s:18:"adobe_stock_retail";s:2:"40";s:6:"active";s:1:"1";}i:5;a:7:{s:9:"long_side";s:2:"36";s:5:"price";s:3:"209";s:10:"short_side";s:2:"24";s:21:"invisible_glass_price";s:3:"250";s:14:"shipping_class";s:0:"";s:18:"adobe_stock_retail";s:2:"40";s:6:"active";s:1:"1";}}s:11:"express_4_3";a:4:{i:1;a:7:{s:9:"long_side";s:2:"12";s:5:"price";s:2:"85";s:10:"short_side";s:1:"9";s:21:"invisible_glass_price";s:2:"25";s:14:"shipping_class";s:0:"";s:18:"adobe_stock_retail";s:2:"40";s:6:"active";s:1:"1";}i:2;a:7:{s:9:"long_side";s:2:"16";s:5:"price";s:2:"99";s:10:"short_side";s:2:"12";s:21:"invisible_glass_price";s:2:"25";s:14:"shipping_class";s:0:"";s:18:"adobe_stock_retail";s:2:"40";s:6:"active";s:1:"1";}i:3;a:7:{s:9:"long_side";s:2:"20";s:5:"price";s:3:"145";s:10:"short_side";s:2:"15";s:21:"invisible_glass_price";s:2:"50";s:14:"shipping_class";s:0:"";s:18:"adobe_stock_retail";s:2:"40";s:6:"active";s:1:"1";}i:4;a:7:{s:9:"long_side";s:2:"30";s:5:"price";s:3:"179";s:10:"short_side";s:2:"24";s:21:"invisible_glass_price";s:3:"100";s:14:"shipping_class";s:0:"";s:18:"adobe_stock_retail";s:2:"40";s:6:"active";s:1:"1";}}s:12:"express_16_9";a:4:{i:1;a:7:{s:9:"long_side";s:1:"8";s:5:"price";s:2:"59";s:10:"short_side";s:1:"6";s:21:"invisible_glass_price";s:2:"25";s:14:"shipping_class";s:0:"";s:18:"adobe_stock_retail";s:2:"40";s:6:"active";s:1:"1";}i:2;a:7:{s:9:"long_side";s:2:"12";s:5:"price";s:2:"99";s:10:"short_side";s:1:"9";s:21:"invisible_glass_price";s:2:"25";s:14:"shipping_class";s:0:"";s:18:"adobe_stock_retail";s:2:"40";s:6:"active";s:1:"1";}i:3;a:7:{s:9:"long_side";s:2:"16";s:5:"price";s:3:"129";s:10:"short_side";s:2:"12";s:21:"invisible_glass_price";s:2:"25";s:14:"shipping_class";s:0:"";s:18:"adobe_stock_retail";s:2:"40";s:6:"active";s:1:"1";}i:4;a:7:{s:9:"long_side";s:2:"20";s:5:"price";s:3:"159";s:10:"short_side";s:2:"15";s:21:"invisible_glass_price";s:2:"50";s:14:"shipping_class";s:0:"";s:18:"adobe_stock_retail";s:2:"40";s:6:"active";s:1:"1";}}}';
        $data = unserialize($data);
        var_dump($data); exit;
        $data = $data['custom_custom'];

        $data[] = [
            'long_side' => '10',
            'price' => '100',
            'short_side' => '8',
            'invisible_glass_price' => '20',
            'shipping_class' => '',
            'adobe_stock_retail' => '20',
            'active' => '1',
        ];

        $data[] = [
            'long_side' => '21',
            'price' => '100',
            'short_side' => '17',
            'invisible_glass_price' => '20',
            'shipping_class' => '',
            'adobe_stock_retail' => '20',
            'active' => '1',
        ];

        $data[] = [
            'long_side' => '13',
            'price' => '100',
            'short_side' => '13',
            'invisible_glass_price' => '20',
            'shipping_class' => '',
            'adobe_stock_retail' => '20',
            'active' => '1',
        ];

        // SORT BY LONG_SIDE ASC
        usort($data, function($a, $b) {
            return $a['long_side'] <=> $b['long_side'];
        });

        var_dump($data);

        exit;
    }

    if (strpos($_SERVER["REQUEST_URI"], '/framedware/ttt') !== false)
    {
        $order = wc_get_order(6260);
        //echo $order->get_order_currency();
        //echo $order->get_payment_method_title();
        //echo get_woocommerce_currency_symbol($order->get_order_currency());
        //$product = wc_get_product(6258);
        //echo $product->get_meta('Frame number');
        //echo $order->get_meta('_pickup_location_address_country');
        //echo $order->get_shipping_method();
        //echo $order->get_currency();
        //echo get_woocommerce_currency_symbol();

        $shipping_method = '';
        foreach( $order->get_shipping_methods() as $item_id => $item ){
            echo $item->get_name(); echo "<br>";
            echo $item->get_type(); echo "<br>";
            echo $item->get_method_title(); echo "<br>";
            echo $item->get_method_id(); echo "<br>";
            echo $item->get_instance_id(); echo "<br>";
            echo $item->get_total(); echo "<br>";
            echo $item->get_total_tax(); echo "<br>";
            var_dump($item->get_taxes()); // array

            // Get custom meta-data
            $formatted_meta_data = $item->get_formatted_meta_data(' ', true);

            $meta = $item->get_meta_data();
            //var_dump($meta);
            foreach ($meta as $item) {
                if ($item->key == '_pickup_location_name') {
                    $shipping_method .= '<strong>Address:</strong> ' . $item->value . "<br>\n";
                }
                if ($item->key == '_pickup_location_address') {
                    if (is_array($item->value)) {
                        //var_dump($item->value);
                        $shipping_method .= retrieve($item->value['address_1'], "<br>\n");
                        $shipping_method .= retrieve($item->value['address_2'], "<br>\n");
                        $shipping_method .= retrieve($item->value['city'], ', ');
                        $shipping_method .= retrieve($item->value['state'], ', ');
                        $shipping_method .= retrieve($item->value['postcode'], ', ');
                        $shipping_method .= retrieve($item->value['country'], '');
                    }
                }
            }

            // Displaying the row custom meta data Objects (just for testing)
//            echo '<pre>'; print_r($formatted_meta_data); echo '</pre>';
//
//            foreach ($formatted_meta_data as $item) {
//                if ($item->key == '_pickup_location_name') {
//                    $shipping_method .= '<strong>Address:</strong> ' . $item->value . "<br>\n";
//                }
//                if ($item->key == '_pickup_location_phone') {
//                    $shipping_method .= '<strong>Phone:</strong> ' . $item->value . "<br>\n";
//                }
//            }
        }
        var_dump($shipping_method);

        foreach ($order->get_items() as $item) {
            //echo $item->get_name();
            //echo $item->get_meta('_pickup_location_name');
            //$product = wc_get_product($item->get_product_id());
            //echo $product->get_stock_quantity();
        }
        exit;
    }

    // CRONJOB ROUTE (MAINTENANCE TASKS)
    /*
    if (strpos($_SERVER["REQUEST_URI"], '/framedware/filestack/test') !== false)
    {
        $security = new FilestackSecurity(FRAMEDWARE_FILESTACK_SECRET);
        $filelink = new Filelink('eWK0NP66TN6HzevLKtOW', FRAMEDWARE_FILESTACK_API_KEY, $security);

        # delete remote file
        $filelink->delete();

        echo 'o o o';
        exit;
    }
    */

    // CRONJOB ROUTE (MAINTENANCE TASKS)
    /*
    if (strpos($_SERVER["REQUEST_URI"], '/framedware/filestack/test') !== false)
    {
        $security = new FilestackSecurity(FRAMEDWARE_FILESTACK_SECRET);
        $filelink = new Filelink('eWK0NP66TN6HzevLKtOW', FRAMEDWARE_FILESTACK_API_KEY, $security);

        # delete remote file
        $filelink->delete();

        echo 'o o o';
        exit;
    }
    */

    // LOCATION TEST
    /*
    if($_SERVER["REQUEST_URI"] == '/framedware/location/test')
    {
        $location = new WC_Local_Pickup_Plus_Pickup_Location(2683);
        $e = $location->get_email_recipients();
        var_dump($e);

        //
        $locations = new WC_Local_Pickup_Plus_Pickup_Locations();
        //var_dump($list = $locations->get_pickup_locations());
        $list = $locations->get_pickup_locations();
        //var_dump($list);

        foreach ($list as $id => $item) {
            //var_dump($id);
            $location = new WC_Local_Pickup_Plus_Pickup_Location($id);
            var_dump($location->get_name());
            var_dump($location->get_email_recipients());
        }

        exit;
    }
    */

    /*
     * Read all pickup locations and create order root folder (if it does not exist already)
     * Note: Depends on 'WooCommerce Local Pickup Plus' plugin
     */
    if($_SERVER["REQUEST_URI"] == '/framedware/location/prep')
    {
        $locations = new WC_Local_Pickup_Plus_Pickup_Locations();
        $list = $locations->get_pickup_locations();
        foreach ($list as $id => $item) {
            $order_path = ABSPATH . 'uploadhandler/orders/frameshops_store_' . $id . '/'; // ROOT
            if ( ! file_exists($order_path)) {
                $msg = 'Creating folder ' . $order_path;
                error_log($msg);
                echo $msg . "<br>\n";
                mkdir($order_path, 0755, true);
            }
        }
        exit;
    }

    // REPORT EXPORT AS .CSV [ALL]
    if(strpos($_SERVER["REQUEST_URI"], '/framedware/report/export/summary') !== false)
    {
        //$after = '2020-10-01'; // test
        //$before = '2020-10-24'; // test
        $after = $_GET['after'];
        $before = $_GET['before'];
        $orders = order_list($after, $before);

        $nini = [];
        foreach ($orders as $e) {
            $place = 'unknown';
            if (isset($e['shipping_lines']['0']['meta_data']) && ! empty(isset($e['shipping_lines']['0']['meta_data']))) {
                foreach ($e['shipping_lines']['0']['meta_data'] as $item) {
                    if (isset($item['key']) && $item['key'] == '_pickup_location_id') {
                        $place = $item['value'];
                    }
                }
            }
            $subtotal = 0;
            if (is_array($e['line_items'])) {
                foreach ($e['line_items'] as $item) {
                    $subtotal += $item['subtotal'];
                }
            }
            $nini[$place]['number_of_orders'] += 1;
            $nini[$place]['subtotal_sum'] += $subtotal;
            $nini[$place]['shipping_total_sum'] += $e['shipping_total'];
            $nini[$place]['tax_total_sum'] += $e['total_tax'];
            $nini[$place]['total_sum'] += $e['total'];
            $nini[$place]['currency'] = $e['currency'];
            $nini[$place]['currency_symbol'] = $e['currency_symbol'];
        }
        $i = 1;
        $output = [];
        $output[] = [
            '#',
            'Store ID',
            'Number of orders',
            'Subtotal Sum',
            'Shipping total Sum',
            'Tax total Sum',
            'Order total Sum',
        ];
        foreach ($nini as $place => $item) {
            $output[] = [
                $i . '.',
                $place,
                $item['number_of_orders'],
                number_format($item['subtotal_sum'], 2, '.', ''),
                number_format($item['shipping_total_sum'], 2, '.', ''),
                number_format($item['total_tax_sum'], 2, '.', ''),
                number_format($item['total_sum'], 2, '.', ''),
            ];
            $i++;
        }
        $output = getCSV($output);

        header('Content-Type: application/csv');
        header('Content-Disposition: attachment; filename=report_summary_' . date('Y-m-d-h-i-s') . '.csv');
        header('Pragma: no-cache');
        echo $output;

        exit;
    }

    // REPORT EXPORT AS .CSV [ALL]
    if(strpos($_SERVER["REQUEST_URI"], '/framedware/report/export/all') !== false)
    {
        //$after = '2020-10-01'; // test
        //$before = '2020-10-24'; // test
        $after = $_GET['after'];
        $before = $_GET['before'];
        $orders = order_list($after, $before);

        $i = 1;
        $output = [];
        $output[] = [
            '#',
            'Store ID',
            'Order number',
            'Order date',
            'Subtotal',
            'Shipping total',
            'Tax total',
            'Order total',
        ];
        foreach ($orders as $e) {
            $place = 'unknown';
            if (isset($e['shipping_lines']['0']['meta_data']) && ! empty(isset($e['shipping_lines']['0']['meta_data']))) {
                foreach ($e['shipping_lines']['0']['meta_data'] as $item) {
                    if (isset($item['key']) && $item['key'] == '_pickup_location_id') {
                        $place = $item['value'];
                    }
                }
            }
            $date_created = new DateTime($e['date_created']);
            $subtotal = 0;
            if (is_array($e['line_items'])) {
                foreach ($e['line_items'] as $item) {
                    $subtotal += $item['subtotal'];
                }
            }
            $output[] = [
                $i . '.',
                $place,
                $e['number'],
                $date_created->format('Y-m-d'),
                number_format($subtotal, 2, '.', ''),
                $e['shipping_total'],
                $e['total_tax'],
                $e['total'],
            ];
            $i++;
        }
        $output = getCSV($output);

        header('Content-Type: application/csv');
        header('Content-Disposition: attachment; filename=report_all_' . date('Y-m-d-h-i-s') . '.csv');
        header('Pragma: no-cache');
        echo $output;

        exit;
    }

    // CRON JOB
    if (strpos($_SERVER["REQUEST_URI"], '/framedware/cron') !== false)
    {
        $msg = 'CRON SCRIPT START';
        error_log($msg);
        echo $msg . "<br>\n";

        try
        {
            /*
             * Call route to:
             * Read all pickup locations and create order root folder (if it does not exist already)
             */
            $msg = 'CRON call /framedware/location/prep';
            error_log($msg);
            echo $msg . "<br>\n";

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $site_url . '/framedware/location/prep');
            curl_setopt($ch, CURLOPT_POST, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $server_output = curl_exec($ch);
            curl_close ($ch);


            /*
             * CRITERIA 0
             * DELETE TRANSACTIONAL UPLOADS, THAT ARE OLDER THEN FRAMEDWARE_CRON_UPLOADS_DAYS
             */
            if (FRAMEDWARE_CRON_UPLOADS_DELETE == 1) {
                error_log('CRON FRAMEDWARE_CRON_UPLOADS_DAYS = ' . FRAMEDWARE_CRON_UPLOADS_DAYS);
                $i = 0;
                $j = 0;
                $now = new \DateTime('now', new DateTimeZone('America/New_York'));
                $upload_path = ABSPATH . 'uploadhandler/uploads/';
                $folders = glob($upload_path . '*');
                foreach($folders as $folder) {
                    if(is_dir($folder)) {
                        $pos = strpos($folder, 'image_assets');
                        if ($pos == false) { // not found
                            $folder_date = new DateTime();
                            $folder_date->setTimestamp(filectime($folder));
                            if($folder_date->diff($now)->days > FRAMEDWARE_CRON_UPLOADS_DAYS) { // number of days in the past
                                //echo $folder . "/  [" . $folder_date->format('Y-m-d h:i') . "] diff = " . $folder_date->diff($now)->days . "\n";
                                // MSG
                                $files = glob($folder . '/*');
                                foreach($files as $file) {
                                    if(is_file($file)) {
                                        $msg = 'CRON [CRITERIA 0] Deleting upload = ' . $file;
                                        error_log($msg);
                                        echo $msg . "<br>\n";
                                        $j++;
                                    }
                                }
                                // delete folder and all of its content
                                rrmdir($folder);
                            }
                        }
                    }
                    $j++;
                }
                //echo 'TOTAL FOLDERS: ' . $i . "\n";
                //echo 'TOTAL FILES: ' . $j . "\n";
                unset($i, $j);
            }

            if (FRAMEDWARE_CRON_PRODUCTS_DELETE == 1) {
                $msg = 'CRON FRAMEDWARE_CRON_PRODUCT_DAYS = ' . FRAMEDWARE_CRON_PRODUCT_DAYS;
                error_log($msg);
                echo $msg . "<br>\n";

                $url = get_site_url();
                $woocommerce = new WooCommerceClient(
                    $url, // Your store URL
                    WOO_CONSUMER_KEY, // Your consumer key
                    WOO_CONSUMER_SECRET, // Your consumer secret
                    [
                        'wp_api' => true, // Enable the WP REST API integration
                        'version' => 'wc/v2', // WooCommerce WP REST API version
                        'verify_ssl' => false,
                        'timeout' => 1800,
                        'query_string_auth' => true // Force Basic Authentication as query string true and using under HTTPS
                    ]
                );


                /*
                 * CRITERIA 1
                 * DELETE PRODUCTS USED IN ORDERS FOR ORDERS THAT ARE OLDER THAN SPECIFIED NUMBER OF DAYS
                 */

                // GET THE LIST OF ORDERS THAT ARE OLDER THAN FRAMEDWARE_CRON_PRODUCT_DAYS
                $order_list = [];
                $product_list = [];
                $parameters = [];

                $now = new \DateTime('now', new DateTimeZone('America/New_York'));
                $diff = $now->sub(new DateInterval('P400D'));
                $parameters['before'] = $diff->format('Y-m-d\TH:i:s'); // ISO8601 compliant date

                $orders = $woocommerce->get('orders', $parameters);
                if (is_array($orders)) {
                    foreach($orders as $order) {
                        //error_log(json_encode($order));
                        foreach($order['line_items'] as $line_item) {
                            if ($line_item['product_id'] != 0) { // product exists
                                $product_list[] = $line_item;
                                //echo "\t" . 'Product id = ' . $line_item['product_id'] . "\n";
                            }
                        }
                    }
                }
                //var_dump($order_list); exit;
                //var_dump($product_ids); exit;
                error_log('CRON [CRITERIA 1] ' . json_encode($order_list));
                error_log('CRON [CRITERIA 1] ' . json_encode($product_list));

                // DELETE PRODUCTS USED IN SELECTED ORDERS
                foreach ($product_list as $item) {
                    $msg = 'CRON [CRITERIA 1] Deleting product id = ' . $item['id'];
                    error_log($msg);
                    echo $msg . "<br>\n";
                    $upload_path = ABSPATH . 'uploadhandler/uploads/' . $item['sku'] . '/';
                    // 1. delete folder and all of its content
                    rrmdir($upload_path);
                    // 2. delete product and attachment images
                    woo_delete_product($item['id']); // delete product and attachment images
                }


                /*
                 * [CRITERIA 2]
                 * DELETE PRODUCTS FROM SPECIFIED CATEGORIES THAT ARE NOT USED IN ANY ORDER
                 * (TRANSACTIONAL PRODUCTS FROM ABANDONED CART)
                 */

                // GET THE LIST OF PRODUCTS USED IN ORDERS
                $orders = $woocommerce->get('orders', ['per_page' => 100]);
                //var_dump(count($orders)); exit;
                $products_used_ids = [];
                if (is_array($orders)) {
                    foreach($orders as $order) {
                        foreach($order['line_items'] as $line_item) {
                            //echo $line_item['product_id'] . '<br>';
                            if ($line_item['product_id'] != 0) { // product exists
                                $products_used_ids[] = $line_item['product_id'];
                            }
                        }
                    }
                }
                //error_log(json_encode($products_used_ids));
                //var_dump($products_used_ids); exit;

                // FIND PRODUCTS NOT USED IN ANY ORDER
                $products = $woocommerce->get('products', ['per_page' => 100, 'exclude' => $products_used_ids]);
                $products_not_used_ids = [];
                if (is_array($products)) {
                    foreach($products as $product) {
                        $products_not_used_ids[] = $product['id'];
                    }
                }
                //error_log(json_encode($products_not_used));
                //var_dump($products_not_used_ids); exit;

                // GET THE LIST OF PRODUCTS FROM SPECIFIED CATEGORIES
                $products_specific_categories_id_sku = [];
                $args = [
                    'limit' => -1,
                    'paginate' => false,
                    'category' => ['uploads', 'uncategorized'], // <--
                    'orderby'  => 'date_created',
                ];
                $products = wc_get_products($args);
                if (is_array($products)) {
                    foreach($products as $product) {
                        $products_specific_categories_id_sku[] = [
                            'id' => $product->get_id(),
                            'sku' => $product->get_sku(),
                        ];
                    }
                }
                //var_dump($products); exit;
                //var_dump($products_specific_categories_id_sku); exit;

                // GET THE LIST OF PRODUCTS FROM SPECIFIED CATEGORIES, NOT USED IN ANY ORDER
                $products_not_used_and_specific_categories_id_sku = [];
                foreach ($products_specific_categories_id_sku as $item) {
                    if (in_array($item['id'], $products_not_used_ids)) {
                        $products_not_used_and_specific_categories_id_sku[] = $item;
                    }
                }
                //var_dump($products_not_used_and_specific_categories_id_sku); exit;

                // DELETE PRODUCTS FROM SPECIFIED CATEGORIES, NOT USED IN ANY ORDER
                foreach ($products_not_used_and_specific_categories_id_sku as $item) {
                    $msg = 'CRON [CRITERIA 2] Deleting product id = ' . $item['id'];
                    error_log($msg);
                    echo $msg . "<br>\n";
                    $upload_path = ABSPATH . 'uploadhandler/uploads/' . $item['sku'] . '/';
                    // 1. delete folder and all of its content
                    rrmdir($upload_path);
                    // 2. delete product and attachment images
                    woo_delete_product($item['id']); // delete product and attachment images
                }
            }
        }
        catch (Exception $e) {
            $error = 'Caught exception: ' . $e->getMessage() . ' on line: ' . $e->getLine();
            error_log($error);
        }

        $msg = 'CRON SCRIPT END';
        error_log($msg);
        echo $msg . "<br>\n";
        exit;
    }
}

add_filter( 'woocommerce_cart_item_name', 'product_details', 10, 3 );
function product_details( $product_name,  $cart_item,  $cart_item_key )
{
    $description = $cart_item['data']->get_description();

    $product_name = '<div>
	      <div><span class="cart-item-title">'.$product_name.'</span></div>
	      <div><span class="cart-item-description">'.$description.'</span></div>
	   </div>';

    return $product_name;
}

function framedware_wp_footer() {
    global $post;

    // SINGLE
    if( is_a( $post, 'WP_Post' ) && (
            has_shortcode( $post->post_content, 'framedeware_single')
            || has_shortcode( $post->post_content, 'framedeware_uploader') // deprecated, used for backward compatibility
            || has_shortcode( $post->post_content, 'framedeware_adobe_stock')
    )) {
        wp_register_script('p5-js', plugin_dir_url(__FILE__) . 'assets/js/p5.js', [], FRAMEDWARE_ORDER_PLUGIN_VERSION, false);
        wp_enqueue_script('p5-js');

        wp_register_script('sketch-js', plugin_dir_url(__FILE__) . 'assets/js/sketch.js', [], FRAMEDWARE_ORDER_PLUGIN_VERSION, false);
        wp_enqueue_script('sketch-js');

        wp_register_script('framedware_wp_footer_single', plugin_dir_url(__FILE__) . 'assets/js/single.js', ['jquery'], FRAMEDWARE_ORDER_PLUGIN_VERSION, false);
        wp_enqueue_script('framedware_wp_footer_single');
    }

    // GALLERY WALL
    if( is_a( $post, 'WP_Post' ) && (
            has_shortcode( $post->post_content, 'framedeware_gallery_wall_1x3')
            || has_shortcode( $post->post_content, 'framedeware_gallery_wall_2x4')
            || has_shortcode( $post->post_content, 'framedeware_gallery_wall_3x3')
            || has_shortcode( $post->post_content, 'framedeware_gallery_wall_4x3')
            || has_shortcode( $post->post_content, 'framedeware_gallery_wall_stairway')
    )) {
        wp_register_script('framedware_wp_footer', plugin_dir_url(__FILE__) . 'assets/js/gallery_wall.js', ['jquery'], FRAMEDWARE_ORDER_PLUGIN_VERSION, false);
        wp_enqueue_script('framedware_wp_footer');
    }

    // FRAME PRO
    if( is_a( $post, 'WP_Post' ) && (
            has_shortcode( $post->post_content, 'framedeware_framepro')
    )) {
        wp_register_script('framedware_wp_footer_framepro', plugin_dir_url(__FILE__) . 'assets/js/framepro.js', ['jquery'], FRAMEDWARE_ORDER_PLUGIN_VERSION, false);
        wp_enqueue_script('framedware_wp_footer_framepro');
    }
}
add_action('wp_footer', 'framedware_wp_footer');


add_action('wp_ajax_nopriv_process_filestack', 'process_filestack');
add_action('wp_ajax_process_filestack', 'process_filestack');
function process_filestack() {
    global $wpdb;

    $data = $_POST['data'];

    error_log(json_encode($data));

    echo json_encode($data);
    die();
};

/**
 *  outputCSV creates a line of CSV and outputs it to browser
 */
function outputCSV($array)
{
    $fp = fopen('php://output', 'w'); // this file actually writes to php output
    //fputcsv($fp, $array);
    foreach ($array as $item) {
        if (count($item) < 10) { // add extra elements up to 40
            $item = array_merge($item, array_fill(count($item) + 1, 10 - count($item), ''));
        }
        fputcsv($fp, $item, ',', '""');
    }
    fclose($fp);
}

/**
 *  getCSV creates a line of CSV and returns it.
 */
function getCSV($array)
{
    ob_start(); // buffer the output ...
    outputCSV($array);
    return ob_get_clean(); // ... then return it as a string!
}

/**
 * Filter orders, and return the list.
 *
 * @param $after
 * @param $before
 * @return array
 */
function order_list($after, $before)
{
    $orders = [];
    $run = false;
    $parameters = [
        'per_page' => 100,
    ];
    if (isset($after)) {
        $parameters['after'] = $after . 'T00:00:00'; // ISO8601 compliant date
        $run = true;
    }
    if (isset($before)) {
        $parameters['before'] = $before . 'T23:59:59'; // to ISO8601 compliant date
        $run = true;
    }
    if ($run) {
        $url = get_site_url();
        $woocommerce = new WooCommerceClient(
            $url, // Your store URL
            WOO_CONSUMER_KEY, // Your consumer key
            WOO_CONSUMER_SECRET, // Your consumer secret
            [
                'wp_api' => true, // Enable the WP REST API integration
                'version' => 'wc/v2', // WooCommerce WP REST API version
                'verify_ssl' => false,
                'timeout' => 1800,
                'query_string_auth' => true // Force Basic Authentication as query string true and using under HTTPS
            ]
        );

        $orders = $woocommerce->get('orders', $parameters);
    }
    //echo $output;
    //var_dump(count($orders));
    //var_dump($orders);
    return $orders;
}

/**
 * Admin Report page view.
 */
function framedware_report_function()
{
    //$after = '2020-10-01'; // test
    //$before = '2020-10-24'; // test
    $after = $_GET['after'];
    $before = $_GET['before'];
    $orders = order_list($after, $before);

    // Create summary
    $nini = [];
    foreach ($orders as $e) {
        $place = 'unknown';
        if (isset($e['shipping_lines']['0']['meta_data']) && ! empty(isset($e['shipping_lines']['0']['meta_data']))) {
            foreach ($e['shipping_lines']['0']['meta_data'] as $item) {
                if (isset($item['key']) && $item['key'] == '_pickup_location_id') {
                    $place = $item['value'];
                }
            }
        }
        $subtotal = 0;
        if (is_array($e['line_items'])) {
            foreach ($e['line_items'] as $item) {
                $subtotal += $item['subtotal'];
            }
        }
        $nini[$place]['number_of_orders'] += 1;
        $nini[$place]['subtotal_sum'] += $subtotal;
        $nini[$place]['shipping_total_sum'] += $e['shipping_total'];
        $nini[$place]['tax_total_sum'] += $e['total_tax'];
        $nini[$place]['total_sum'] += $e['total'];
        $nini[$place]['currency'] = $e['currency'];
        $nini[$place]['currency_symbol'] = $e['currency_symbol'];
    }
    //var_dump($nini); exit;

    $file = plugin_dir_path( __FILE__ ) . "views/admin_report.php";
    if ( file_exists( $file ) ) {
        require $file;
    }
}

/**
 * Get pickup location (store) details (name & email).
 *
 * @param $store_id
 * @return array|null
 */
function getPickupLocationDetails($store_id)
{
    $output = [];
    $location = new WC_Local_Pickup_Plus_Pickup_Location($store_id);
    if ($location) {
        $name = $location->get_name();
        $emails = $location->get_email_recipients();
        //var_dump($emails);
        //
        $output['name'] = $name;
        if (isset($emails[0])) {
            $output['email'] = $emails[0];
        }
        return $output;
    }
    return null;
}

/**
 * Format PayPal payout data.
 *
 * @param $input
 * @return array
 */
function payPalFormatPayoutData($recipients)
{
    // from documentation
    $example = '{
        "sender_batch_header": {
            "email_subject": "You have a payment",
            "sender_batch_id": "batch-1604934282020"
        },
        "items": [
            {
                "recipient_type": "EMAIL",
                "amount": {
                    "value": "1.00",
                    "currency": "USD"
                },
                "receiver": "email@aol.com",
                "note": "Payouts sample transaction",
                "sender_item_id": "item-2-1604934282021"
            }
        ]
    }';

    $data = [];
    $data['sender_batch_header'] = [
        'email_subject' => 'You have a payment from Frameshops.com',
        'sender_batch_id' => 'frameshops-' . time(),
    ];
    foreach ($recipients as $store_id => $details) {
        $data['items'][] = [
            'recipient_type' => 'EMAIL',
            'amount' => [
                'value' => $details['amount'],
                'currency' => 'USD',
            ],
            'receiver' => $details['email'],
            'note' => 'Frameshops payout',
            'sender_item_id' => 'store-' . $store_id . '-' . time(),
        ];
    }
    $data = json_encode($data);
    return $data;
}

/**
 * PayPal get Access Token
 *
 * https://developer.paypal.com/docs/api/get-an-access-token-curl/
 */
function payPalGetAccessToken($client_id, $secret)
{
    $sandbox = '';
    if (FRAMEDWARE_PAYPAL_SANDBOX == true) {
        $sandbox = 'sandbox.';
    }
    $endpoint = 'https://api.' . $sandbox . 'paypal.com/v1/oauth2/token';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Accept-Language: en_US',
    ]);
    curl_setopt($ch, CURLOPT_USERPWD, $client_id . ':' . $secret);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');

    // receive server response
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $server_output = curl_exec($ch);
    error_log($server_output);
    curl_close ($ch);

    //var_dump($server_output); // test
    $o = json_decode($server_output, true);
    $o['endpoint'] = $endpoint;
    //var_dump($o); // test

    return $o;
}

/**
 * PayPal Create Payout
 *
 * https://developer.paypal.com/docs/payouts/integrate/api-integration/#create-payout
 */
function payPalCreatePayout($access_token, $data)
{
    $sandbox = '';
    if (FRAMEDWARE_PAYPAL_SANDBOX == true) {
        $sandbox = 'sandbox.';
    }
    $endpoint = 'https://api-m.' . $sandbox . 'paypal.com/v1/payments/payouts';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'accept: application/json',
        'authorization: Bearer ' . $access_token,
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

    // receive server response
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $server_output = curl_exec($ch);
    error_log($server_output);
    curl_close($ch);

    //var_dump($server_output);
    $o = json_decode($server_output, true);
    $o['endpoint'] = $endpoint;
    //var_dump($o);

    return $o;
}

/**
 * Paypal get Payout Details
 *
 * https://developer.paypal.com/docs/payouts/integrate/api-integration/#show-payout-details
 */
function payPalGetPayoutDetails($access_token, $payout_batch_id)
{
    $sandbox = '';
    if (FRAMEDWARE_PAYPAL_SANDBOX == true) {
        $sandbox = 'sandbox.';
    }
    $endpoint = 'https://api.' . $sandbox . 'paypal.com/v1/payments/payouts/' . $payout_batch_id;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_POST, 0);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'accept: application/json',
        'authorization: Bearer ' . $access_token,
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $server_output = curl_exec($ch);
    error_log($server_output);
    curl_close($ch);

    //var_dump($server_output);
    $o = json_decode($server_output, true);
    $o['endpoint'] = $endpoint;
    //var_dump($o);

    return $o;
}

// TODO: validate input (email, etc.)

/**
 * Send PayPal payout
 * AJAX
 */
add_action('wp_ajax_paypal_payout_send', 'paypal_payout_send');
function paypal_payout_send()
{
    if (is_admin()) {
        parse_str($_POST['data'], $data);
        //var_dump($data); return;

        $response = payPalGetAccessToken($data['client_id'], $data['secret']); // test
        error_log(json_encode($response));
        //var_dump($response);

        $access_token = null;
        $html = 'Request failed.'; $status = 'error';
        if (isset($response['error_description'])) {
            $html = $response['error_description'] . '.'; $status = 'error';
        } else if (isset($response['access_token'])) {
            $access_token = $response['access_token'];
            //var_dump($access_token);
            $recipients = [];
            foreach ($data as $key => $array) {//
                if (strpos($key, 'store_') !== false) {
                    $store_id = (int) str_replace('store_', '', $key);
                    if (is_int($store_id)) {
                        $recipients[$store_id] = [
                            'email' => $array['email'],
                            'amount' => $array['amount'],
                        ];
                    }
                }
            }
            //var_dump($recipients);
            error_log(json_encode($recipients));

            $payout_data = payPalFormatPayoutData($recipients);
            error_log($payout_data);
            //var_dump($payout_data);

            $response = payPalCreatePayout($access_token, $payout_data);
            error_log(json_encode($response));
            //var_dump($response);

            if (isset($response['batch_header']['payout_batch_id'])) {
                $html = 'Payout sent.'; $status = 'success';
            }
            if (isset($response['name'])) {
                $html = str_replace('_', ' ', $response['name']) . '.'; $status = 'error';
            }
        }

        $html = '<div id="form-response-html-message" class="form-response-' . $status .'">' . $html . '</div><div id="form-response-html-details">' . json_encode($response) . '</div>';

        header('Access-Control-Allow-Origin: *');
        header("Content-Type: application/json", true);
        echo json_encode(['success' => '1', 'html' => $html]);
        wp_die();
        return;
    }
}

/**
 * Admin `Report` page view.
 */
function framedware_prices_function()
{
    global $wpdb;
    $data = $wpdb->get_var( 'SELECT `data` FROM `fware_config`');
    //error_log($data);
    //$data = json_decode($data, true);

    $shipping_classes = get_terms(['taxonomy' => 'product_shipping_class', 'hide_empty' => false]);
    //var_dump($shipping_classes);

    $file = plugin_dir_path( __FILE__ ) . 'views/admin_config.php';
    if (file_exists($file)) {
        require $file;
    }
}

/**
 * Admin `Art Upload (Input)` page view.
 */
function framedware_art_input_function()
{
    //var_dump($_GET);
    $file = plugin_dir_path( __FILE__ ) . 'views/admin_art_input.php';
    if (file_exists($file)) {
        require $file;
    }
}

/**
 * Admin `Art List` page view.
 */
function framedware_art_list_function()
{
    $file = plugin_dir_path( __FILE__ ) . 'views/admin_art_list.php';
    if (file_exists($file)) {
        require $file;
    }
}

/**
 * Admin `Art Price List Input` page view.
 */
function framedware_pricelist_input_function()
{
    //var_dump($_GET);
    $file = plugin_dir_path( __FILE__ ) . 'views/admin_pricelist_input.php';
    if (file_exists($file)) {
        require $file;
    }
}

/**
 * Admin `Art Price list List` page view.
 */
function framedware_pricelist_list_function()
{
    $file = plugin_dir_path( __FILE__ ) . 'views/admin_pricelist_list.php';
    if (file_exists($file)) {
        require $file;
    }
}

/**
 * INPUT HANDLING
 *
 * @param $input
 * @return int
 */
function input_in__checkbox($input) {
    if ($input == '1') {
     return 1;
    }
    return 0;
}

// CHECK IF ARRAY HAS DUPLICATES
function array_has_duplicates($array) {
    return count($array) !== count(array_unique($array));
}

function framedware_config_validate($post)
{
    // RULES
    $rules = [
        'default_min_print_res' => ['Minimum Print Resolution (DPI)', ['required', 'decimal'], $post['default_min_print_res']],
        'minimum_print_length' => ['Minimum Print Length (Inches)', ['required', 'decimal'], $post['minimum_print_length']],
        'mat_size' => ['Mat Size', ['required', 'decimal'], $post['mat_size']],
        'ui_3d' => ['Skip Ratios Screen', ['checkbox'], $post['ui_3d']],
        'skip_crop' => ['Skip Ratios Screen', ['checkbox'], $post['skip_crop']],
        //
        'ui_custom_custom' => ['Custom Aspect Ratio', ['checkbox'] , $post['ui_custom_custom']],
        'ui_express_1_1' => ['1:1 Aspect Ratio', ['checkbox'], $post['ui_express_1_1']],
        'ui_express_3_2' => ['3:2 Aspect Ratio', ['checkbox'], $post['ui_express_3_2']],
        'ui_express_4_3' => ['4:3 Aspect Ratio', ['checkbox'], $post['ui_express_4_3']],
        'ui_express_16_9' => ['16:9 Aspect Ratio', ['checkbox'], $post['ui_express_16_9']],
    ];
    $gw = ['1x3', '2x4', '3x3', '4x3', 'stairway'];
    foreach ($gw as $w) {
        $rules['wall_price_' . $w] = ['Gallery Wall Price ' . $w, ['required', 'decimal'], $post['wall_price_' . $w]];
        $rules['wall_weight_' . $w] = ['Gallery Wall Weight ' . $w, ['required', 'decimal'], $post['wall_weight_' . $w]];
        $rules['wall_length_' . $w] = ['Gallery Wall Length ' . $w, ['required', 'decimal'], $post['wall_length_' . $w]];
        $rules['wall_width_' . $w] = ['Gallery Wall Width ' . $w, ['required', 'decimal'], $post['wall_width_' . $w]];
        $rules['wall_height_' . $w] = ['Gallery Wall Height ' . $w, ['required', 'decimal'], $post['wall_height_' . $w]];
        $rules['wall_shipping_class_' . $w] = ['Gallery Wall Shipping Class ' . $w, ['integer'], $post['wall_shipping_class_' . $w]];
    }

    $prices = [];
    if(is_array($post['paper'])) {
        foreach ($post['paper'] as $ratio => $list) {
            $data['paper'][$ratio] = [];
            if(is_array($list)) {
                foreach ($list as $key => $item) {
                    // get all prices
                    $prices[$ratio][] = $item['price'];
                    // rules
                    if ($ratio == 'custom_custom') {
                        $label = 'Custom Ratio ';
                    }
                    if ($ratio == 'express_1_1') {
                        $label = '1:1 Ratio ';
                    }
                    if ($ratio == 'express_3_2') {
                        $label = '3:2 Ratio ';
                    }
                    if ($ratio == 'express_4_3') {
                        $label = '4:3 Ratio ';
                    }
                    if ($ratio == 'express_16_9') {
                        $label = '16:9 Ratio ';
                    }
                    $rules['paper[' . $ratio . '][' . $key . '][price]'] = [$label . '[Price] (' . $key . ')', ['required', 'decimal'], $item['price']];
                    $rules['paper[' . $ratio . '][' . $key . '][long_side]'] = [$label . '[Long Side] (' . $key . ')', ['required', 'decimal'], $item['long_side']];
                    $rules['paper[' . $ratio . '][' . $key . '][short_side]'] = [$label . '[Short Side] (' . $key . ')', ['required', 'decimal'], $item['short_side']];
                    $rules['paper[' . $ratio . '][' . $key . '][invisible_glass_price]'] = [$label . '[Invisible Glass Price] (' . $key . ')', ['required', 'decimal'], $item['invisible_glass_price']];
                    $rules['paper[' . $ratio . '][' . $key . '][active]'] = [$label . '[Active/Inactive] (' . $key . ')', ['radio'], $item['active']];
                    $rules['paper[' . $ratio . '][' . $key . '][shipping_class]'] = [$label . '[Shipping Class] (' . $key . ')', [], $item['shipping_class']];
                    $rules['paper[' . $ratio . '][' . $key . '][adobe_stock_retail]'] = [$label . '[Adobe Stock Retail] (' . $key . ')', ['required', 'decimal'], $item['adobe_stock_retail']];
                }
            }
        }
    }
    //return $rules;

    // VALIDATE
    $errors = [];

    // check for duplicate prices (cannot have them)
    //var_dump($prices); exit;
    if (is_array($prices)) {
        foreach ($prices as $ratio => $list) {
            if (is_array($list)) {
                if (array_has_duplicates($list)) {
                    $errors[] = 'Make sure all prices are unique in ' . $ratio . ' list.';
                }
            }
        }
    }

    foreach ($rules as $key => $item) { // 0 = label, 1 = rules, 2 = value
        if (isset($item[0]) && isset($item[1]) && array_key_exists(2, $item)) {
            // REQUIRED
            if (is_array($item[1]) && in_array('required', $item[1]) && ($item[2] == '' || $item[2] == null)) {
                $errors[] = $item[0] . ' field is required. ';
            }
            // NUMBER (DECIMAL)
            if (is_array($item[1]) && in_array('decimal', $item[1]) && ! is_numeric($item[2])) {
                $errors[] = $item[0] . ' must be a decimal number. ';
            }
            // CHECKBOX
            if (is_array($item[1]) && in_array('checkbox', $item[1]) && ! in_array($item[2], ['1', '0', '', null])) {
                $errors[] = $item[0] . ' must be a valid chechbox value. ';
            }
            // RADIO
            if (is_array($item[1]) && in_array('radio', $item[1]) && ! in_array($item[2], ['1', '0', '', null])) {
                $errors[] = $item[0] . ' must be a valid radio value. ';
            }
        }
    }

    // OUTPUT
    $output = '';
    foreach ($errors as $error) {
        $output .= '&#8226; ' . $error . "<br>\n";
    }
    return $output;
}

function framedware_art_validate($post)
{
    //var_dump($post); exit;
    // RULES
    $rules = [
        'artist' => ['Artist Name', ['required'], $post['artist']],
        'title' => ['Art Title', ['required'], $post['title']],
        'hi_res_filename' => ['Hi res filename', ['required'], $post['hi_res_filename']],
        'price_list' => ['Price List', ['required'], $post['price_list']],
        'description' => ['Description', ['required'], $post['description']],
        'filesize_width' => ['Hi res Width', ['required'], $post['filesize_width']],
        'filesize_height' => ['Hi res Height', ['required'], $post['filesize_height']],
        'allow_max' => ['Allow max size', ['required', 'radio'], $post['allow_max']],
        'active' => ['AOrder Status', ['required', 'radio'], $post['active']],
        'in_stock' => ['In Stock', ['required', 'radio'], $post['in_stock']],
        'position' => ['Position', ['integer'], $post['position']],
    ];
    //var_dump($rules); exit;

    // VALIDATE
    $errors = [];
    foreach ($rules as $key => $item) { // 0 = label, 1 = rules, 2 = value
        if (isset($item[0]) && isset($item[1]) && array_key_exists(2, $item)) {
            // REQUIRED
            if (is_array($item[1]) && in_array('required', $item[1]) && ($item[2] == '' || $item[2] == null)) {
                $errors[] = $item[0] . ' field is required. ';
            }
            // NUMBER (INTEGER)
            if (is_array($item[1]) && in_array('integer', $item[1]) && ! ctype_digit($item[2])) {
                $errors[] = $item[0] . ' must be an integer number. ';
            }
            // NUMBER (DECIMAL)
            if (is_array($item[1]) && in_array('decimal', $item[1]) && ! is_numeric($item[2])) {
                $errors[] = $item[0] . ' must be a decimal number. ';
            }
            // CHECKBOX
            if (is_array($item[1]) && in_array('checkbox', $item[1]) && ! in_array($item[2], ['1', '0', '', null])) {
                $errors[] = $item[0] . ' must be a valid chechbox value. ';
            }
            // RADIO
            if (is_array($item[1]) && in_array('radio', $item[1]) && ! in_array($item[2], ['1', '0', '', null])) {
                $errors[] = $item[0] . ' must be a valid radio value. ';
            }
        }
    }

    // OUTPUT
    $output = '';
    foreach ($errors as $error) {
        $output .= '&#8226; ' . $error . "<br>\n";
    }
    return $output;
}

function framedware_pricelist_validate($post)
{
    // RULES
    $rules = [
        'name' => ['Name', ['required'], $post['name']],
    ];

    $i = 1;
    foreach ($_POST['data'] as $key => $value) {
        //echo $key . ' -> ' . $value . "\n";
        $rules['price_' . $key] = ['Price ' . $i, ['required', 'integer'], $post['data'][$key]];
        $i++;
    }
    //var_dump($rules); exit;

    // VALIDATE
    $errors = [];
    foreach ($rules as $key => $item) { // 0 = label, 1 = rules, 2 = value
        if (isset($item[0]) && isset($item[1]) && array_key_exists(2, $item)) {
            // REQUIRED
            if (is_array($item[1]) && in_array('required', $item[1]) && ($item[2] == '' || $item[2] == null)) {
                $errors[] = $item[0] . ' field is required. ';
            }
            // NUMBER (INTEGER)
            if (is_array($item[1]) && in_array('integer', $item[1]) && ! ctype_digit($item[2])) {
                $errors[] = $item[0] . ' must be an integer number. ';
            }
            // NUMBER (DECIMAL)
            if (is_array($item[1]) && in_array('decimal', $item[1]) && ! is_numeric($item[2])) {
                $errors[] = $item[0] . ' must be a decimal number. ';
            }
            // CHECKBOX
            if (is_array($item[1]) && in_array('checkbox', $item[1]) && ! in_array($item[2], ['1', '0', '', null])) {
                $errors[] = $item[0] . ' must be a valid chechbox value. ';
            }
            // RADIO
            if (is_array($item[1]) && in_array('radio', $item[1]) && ! in_array($item[2], ['1', '0', '', null])) {
                $errors[] = $item[0] . ' must be a valid radio value. ';
            }
        }
    }

    // OUTPUT
    $output = '';
    foreach ($errors as $error) {
        $output .= '&#8226; ' . $error . "<br>\n";
    }
    return $output;
}

add_action('wp_ajax_framedware_config_restore', 'framedware_config_restore');
function framedware_config_restore()
{
    global $wpdb;
    $data = get_default_config_data();
    $data = json_encode($data);
    
    $query = "UPDATE `fware_config` SET data = %s";
    $wpdb->query($wpdb->prepare($query, $data));

    header('Access-Control-Allow-Origin: *');
    header("Content-Type: application/json", true);
    echo json_encode(['status' => 1, 'message' => 'Defaults restored.']);
    wp_die();
    return;
}

// "OPTIONS" PAGE
add_action('wp_ajax_framedware_config_save_ii', 'framedware_config_save_ii');
function framedware_config_save_ii()
{
    //var_dump(framedware_config_validate($_POST)); wp_die(); return;
    //var_dump($_POST); wp_die(); return;

    // VALIDATE INPUT
    $v = framedware_config_validate($_POST);
    if ($v != '') {
        header('Access-Control-Allow-Origin: *');
        header("Content-Type: application/json", true);
        echo json_encode(['status' => 0, 'message' => $v]);
        wp_die();
        return;
    }

    global $wpdb;
    $data = $wpdb->get_var( 'SELECT `data` FROM `fware_config`');
    //error_log($data);
    $data = json_decode($data, true);

    $data['ui_3d'] = input_in__checkbox($_POST['ui_3d']);
    $data['skip_crop'] = input_in__checkbox($_POST['skip_crop']);

    $data = json_encode($data); // <-
    $query = "UPDATE `fware_config` SET data = %s";
    $wpdb->query($wpdb->prepare($query, $data));

    header('Access-Control-Allow-Origin: *');
    header("Content-Type: application/json", true);
    echo json_encode(['status' => 1, 'message' => 'Saved.']);
    wp_die();
    return;
}

// "ADJUST PRICES" PAGE
add_action('wp_ajax_framedware_config_save', 'framedware_config_save');
function framedware_config_save()
{
    //var_dump(framedware_config_validate($_POST)); wp_die(); return;
    //var_dump($_POST); wp_die(); return;
    //var_dump($_POST['paper']['custom_custom']); wp_die(); return;

    // VALIDATE INPUT
    $v = framedware_config_validate($_POST);
    if ($v != '') {
        header('Access-Control-Allow-Origin: *');
        header("Content-Type: application/json", true);
        echo json_encode(['status' => 0, 'message' => $v]);
        wp_die();
        return;
    }

    global $wpdb;
    $data = $wpdb->get_var( 'SELECT `data` FROM `fware_config`');
    //error_log($data);
    $data = json_decode($data, true);

    $data['default_min_print_res'] = $_POST['default_min_print_res'];
    $data['minimum_print_length'] = $_POST['minimum_print_length'];
    $data['frame_weight_factor'] = $_POST['frame_weight_factor'];
    $data['frame_size_padding'] = $_POST['frame_size_padding'];
    $data['wall_image_width'] = $_POST['wall_image_width'];
    $data['mat_size'] = $_POST['mat_size'];
    //
    $data['ui']['custom_custom'] = input_in__checkbox($_POST['ui_custom_custom']);
    $data['ui']['express_1_1'] = input_in__checkbox($_POST['ui_express_1_1']);
    $data['ui']['express_3_2'] = input_in__checkbox($_POST['ui_express_3_2']);
    $data['ui']['express_4_3'] = input_in__checkbox($_POST['ui_express_4_3']);
    $data['ui']['express_16_9'] = input_in__checkbox($_POST['ui_express_16_9']);
    //
    $gw = ['1x3', '2x4', '3x3', '4x3', 'stairway'];
    foreach ($gw as $w) {
        $data['wall'][$w]['price'] = $_POST['wall_price_' . $w];
        $data['wall'][$w]['weight'] = $_POST['wall_weight_' . $w];
        $data['wall'][$w]['length'] = $_POST['wall_length_' . $w];
        $data['wall'][$w]['width'] = $_POST['wall_width_' . $w];
        $data['wall'][$w]['height'] = $_POST['wall_height_' . $w];
        $data['wall'][$w]['shipping_class'] = $_POST['wall_shipping_class_' . $w];
    }
    //
    if(is_array($_POST['paper'])) {
        foreach ($_POST['paper'] as $ratio => $list) {
            $data['paper'][$ratio] = [];
            if(is_array($list)) {
                foreach ($list as $item) {
                    if (
                        isset($item['price'])
                        && isset($item['long_side'])
                        && isset($item['short_side'])
                        && isset($item['invisible_glass_price'])
                        && isset($item['active'])
                    ) {

                        // assert long/short values are correct
                        $long_side = $item['long_side'];
                        $short_side = $item['short_side'];
                        if ($item['short_side'] > $item['long_side']) {
                            $long_side = $item['short_side'];
                            $short_side = $item['long_side'];
                        }

                        $data['paper'][$ratio][$item['price']] = [
                            'long_side' => $long_side,
                            'short_side' => $short_side,
                            'invisible_glass_price' => $item['invisible_glass_price'],
                            'active' => $item['active'],
                            'shipping_class' => $item['shipping_class'],
                            'adobe_stock_retail' => $item['adobe_stock_retail'],
                        ];
                    }
                }
            }
            // SORT RATIO LIST BY `LONG_SIDE` ASC
            uasort($data['paper'][$ratio], function($a, $b) {
                return $a['long_side'] <=> $b['long_side'];
            });
        }
    }
    //var_dump($data['paper']['custom_custom']); exit;
    //error_log('--------------------------------'); error_log(serialize($_POST['paper'])); error_log('--------------------------------');
    //
    $data['lowres_title'] = htmlspecialchars($_POST['lowres_title']);
    $data['lowres_message'] = htmlspecialchars($_POST['lowres_message']);


    $data = json_encode($data); // <-
    $query = "UPDATE `fware_config` SET data = %s";
    $wpdb->query($wpdb->prepare($query, $data));

    header('Access-Control-Allow-Origin: *');
    header("Content-Type: application/json", true);
    echo json_encode(['status' => 1, 'message' => 'Saved.']);
    wp_die();
    return;
}

add_action('woocommerce_payment_complete', 'framedware_woocommerce_payment_complete');
function framedware_woocommerce_payment_complete( $order_id )
{
    error_log('Woocommerce order payment has been received for order ' . $order_id, 0);
    $site_url = get_site_url();
    $order = wc_get_order($order_id);

    $use_zip = false;
    if (class_exists('ZipArchive')) { // use Zip if available on server
        $use_zip = true;
    }
    //$use_zip = false; // <-- force not to use zip

    // PROCESS ORDER ITEMS
    $place = '';
    $delete = []; // list of files to delete after zip operation
    $order_path = ABSPATH . 'uploadhandler/orders/' . $place . 'order_' . $order_id . '/';
    $order_url = $site_url . '/uploadhandler/orders/' . $place . 'order_' . $order_id . '/order_' . $order_id . '.zip';
    if ( ! file_exists($order_path)) {
        mkdir($order_path, 0755, true);
    }

    $note0 = 'Order files are located in ' . $order_path;
    $note_adobe = '';

    $adobe_i = 1;
    foreach ($order->get_items() as $item)
    {
        $product = wc_get_product($item->get_product_id());
        $sku = $product->get_sku();
        $item_upload_path = ABSPATH . '/uploadhandler/uploads/' . $sku . '/';
        $item_order_path = $order_path . $sku . '/';
        if ( ! file_exists($item_order_path)) {
            mkdir($item_order_path, 0755, true);
        }

        $adobe_id = $product->get_meta('adobe_id');
        if ( ! empty($adobe_id)) {
            $note_adobe .= $adobe_i . '. <a href="https://stock.adobe.com/images/x/' . $adobe_id . '" target="_blank">' . $adobe_id . '</a>' . "<br>\n";
            $adobe_i++;
        }

        // COPY FILES AND FOLDERS FROM UPLOAD FOLDER TO ORDER FOLDER
        $source = $item_upload_path;
        $destination = $item_order_path;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
        foreach ($iterator as $item) {
            if ($item->isDir()) {
                mkdir($destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName());
            } else {
                copy($item, $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName());
            }
        }

        // BUILD LIST OF FILES TO DELETE
        if (class_exists('ZipArchive')) { // use Zip if available on server
            $delete[] = $item_order_path;
        }
    }

    // CREATE INVOICE
    $invoice = @create_invoice($order);
    if ( ! empty($invoice)) {
        file_put_contents($order_path . 'invoice.html', $invoice);
    }

    // ZIP
    if ($use_zip)
    {
        $zip = new ZipArchive();
        $zip_file = 'order_' . $order_id . '.zip';
        $zip->open($order_path . '/' . $zip_file, ZipArchive::OVERWRITE | ZipArchive::CREATE);

        // Create recursive directory iterator
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($order_path), RecursiveIteratorIterator::LEAVES_ONLY);
        foreach ($iterator as $name => $file) {
            // Skip directories (they would be added automatically)
            if ( ! $file->isDir()) {
                // Get real and relative path for current file
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($order_path));

                // Add current file to archive
                $zip->addFile($filePath, $relativePath);
            }
        }
        // Zip archive will be created only after closing object
        $r = $zip->close();

        /**
         * Current setup is to have both, zip and unziped file/folders, so DO NOT delete.
         */
        // DELETE FOLDERS & FILES
        /*
        if ($r) {
            foreach ($delete as $dir) {
                $it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
                $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
                foreach($files as $file) {
                    if ($file->isDir()) {
                        //rmdir($file->getRealPath());
                    }
                    else {
                        //error_log($file->getRealPath());
                        unlink($file->getRealPath());
                    }
                }
                unset($it, $files);
                rmdir($dir);
            }
        }
        */

        // Note
        $note = '<a href="' . $order_url . '">DOWNLOAD IMAGES</a>';
    }

    // NOTE
        // prep
        $note_adobe = rtrim($note_adobe, '<br>');
        if ( ! empty($note_adobe)) {
            $note_adobe = 'ADOBE STOCK IMAGE(S):' . "<br>\n". $note_adobe;
        }

        // store
        $order->add_order_note(__($note0));
        if ( ! empty($note_adobe)) {
            $order->add_order_note(__($note_adobe));
        }
        $order->add_order_note(__($note));
}

// TEST
//global $wp_filter;
//print_r($wp_filter);
//exit;

add_action('woocommerce_before_add_to_cart_form', 'framedware_get_cart_item', 5);
function framedware_get_cart_item() {
    global $product;
    echo '<span onclick="get_cart_item(\'' . $product->sku.  '\');" class="product-edit framedware-product-item" style="text-align: left;"><i class="far fa-edit" aria-hidden="true"></i>Edit</span>';
}

add_action( 'woocommerce_order_actions', 'framedware_woo_order_actions' );
function framedware_woo_order_actions( $actions ) {
    $actions['print_ups_invoice'] =  'Print UPS Invoice';
    return $actions;
}

add_action( 'woocommerce_order_action_print_ups_invoice', 'framedware_woo_print_ups_invoice' );
function framedware_woo_print_ups_invoice( $order ) {
    $order_id = $order->get_id();
    //error_log('-pui-II--' . $order_id);


    global $wpdb;
    $shippo_api_key = $wpdb->get_var( "SELECT `data` FROM `fware_config_x` WHERE `key` = 'shippo_api_key' LIMIT 1");

    $fromAddress = [
        'name' => 'Big Apple Art Gallery & Framing',
        'company' => 'Big Apple Art Gallery & Framing',
        'street1' => get_option('woocommerce_store_address'),
        'street2' => get_option('woocommerce_store_address_2'),
        'city' => get_option('woocommerce_store_city'),
        'state' => get_option('woocommerce_store_state'),
        'zip' => get_option('woocommerce_store_postcode'),
        'country' => get_option('woocommerce_default_country'),
        'phone' => '',
        'email' => '',
    ];

    $toAddress = [
        'name' => $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),
        'company' => $order->get_shipping_company(),
        'street1' => $order->get_shipping_address_1(),
        'street2' => $order->get_shipping_address_2(),
        'city' => $order->get_shipping_city(),
        'state' => $order->get_shipping_state(),
        'zip' => $order->get_shipping_postcode(),
        'country' => $order->get_shipping_country(),
        'phone' => $order->get_billing_email(), // [billing]
        'email' => $order->get_billing_email(), // [billing]
    ];


        if ( count( $order->get_items() ) > 0 ) {
            foreach ( $order->get_items() as $item_id => $item ) {
                // Add order pay to available pay
                $available_pay += $item->get_total();
            }
        }


    $parcel = [
        'length'=> '5',
        'width'=> '5',
        'height'=> '5',
        'distance_unit'=> 'in',
        'weight'=> '2',
        'mass_unit'=> 'lb',
    ];

    $shipment = [
        'address_from'=> $fromAddress,
        'address_to'=> $toAddress,
        'parcels'=> [$parcel],
    ];

    $transaction = Shippo_Transaction::create( [
            'shipment' => $shipment,
            //'carrier_account' => 'b741b99f95e841639b54272834bc478c',
            'carrier_account' => '5e29d98fd9dc44109a54214afa7d31f7',
            'servicelevel_token' => 'usps_priority',
            'label_file_type' => "PNG",
        ]
    );


}

//// Add our actions to list
//add_filter( "bulk_actions-edit-shop_order", function($actions) {
//    $statuses = wc_get_order_statuses();
//    foreach ($statuses as $key => $name) {
//        $actions[ 'set-' . $key] = "Set status \"" . $name . "\"";
//    }
//
//    return $actions;
//}, 9 );