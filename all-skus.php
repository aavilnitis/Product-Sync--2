<?php
/**
 * Plugin Name: Stock Count Sync (Grillman -> Gatavo Dabā)
 * Description: Wordpress plugin that synchronises the stock count based on Grillman's available amounts.
 * Version: 1.0.4
 * Author: Aleksis Vilnitis
**/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define the interval in hours
$sync_interval = 6;

function get_all_product_skus() {
    // URL to fetch the XML file from
    $xml_url = 'https://grillman.lt/module/xmlfeeds/api?id=18&affiliate=affiliate_name';

    // Load the XML file from the URL
    $xml = simplexml_load_file($xml_url);

    $args = array(
        'post_type'      => 'product',
        'posts_per_page' => -1,
    );

    $products = new WP_Query($args);

    // Create the output content
    ob_start();

    if ($products->have_posts()) {
        echo '<table>';
        echo '<tr>';
        echo '<th>Product SKU</th>';
        echo '<th>Grillman SKU</th>';
        echo '<th>Grillman Quantity</th>';
        echo '<th>Gatavo Quantity</th>';
        echo '</tr>';

        while ($products->have_posts()) {
            $products->the_post();
            $product_id = get_the_ID();
            $product = wc_get_product($product_id);
            $sku = $product->get_sku();

            foreach ($xml->product as $grillman) {
                $grillsku = (string) $grillman->reference;
                $stock = (int) $grillman->quantity;
                if ($sku == $grillsku) {
                    update_post_meta($product_id, '_stock', $stock);
                    update_post_meta($product_id, '_manage_stock', 'yes');
                    if ($stock <= 1) {
                        update_post_meta($product_id, '_stock_status', 'outofstock');
                    } else {
                        update_post_meta($product_id, '_stock_status', 'instock');
                    }
                    $current_stock = get_post_meta($product_id, '_stock', true);

                    echo '<tr>';
                    echo '<td style="color: green;">' . $sku . '</td>';
                    echo '<td style="color: red;">' . $grillsku . '</td>';
                    echo '<td style="color: red;">' . $stock . '</td>';
                    echo '<td style="color: green;">' . $current_stock . '</td>';
                    echo '</tr>';
                }
            }
        }

        echo '</table>';

        wp_reset_postdata();
    }

    // Get the output content
    $output_content = ob_get_clean();

    // Write the output to the file
    $file = fopen(plugin_dir_path(__FILE__) . 'output.txt', 'w');
    fwrite($file, $output_content);
    fclose($file);
}

function schedule_sync_stock() {
    // Run the synchronization on plugin activation
    get_all_product_skus();

    // Schedule the synchronization to run every 6 hours
    if (!wp_next_scheduled('sync_stock_event')) {
        wp_schedule_event(time(), 'six_hours', 'sync_stock_event');
    }
}

// Schedule the synchronization on plugin activation
register_activation_hook(__FILE__, 'schedule_sync_stock');

// Hook the synchronization function to the scheduled event
add_action('sync_stock_event', 'get_all_product_skus');

// Define the custom cron interval
function add_six_hours_interval($schedules) {
    $schedules['six_hours'] = array(
        'interval' => 6 * HOUR_IN_SECONDS,
        'display'  => 'Every 6 Hours'
    );
    return $schedules;
}
add_filter('cron_schedules', 'add_six_hours_interval');

function skus_shortcode() {
    // Read the output.txt file
    $output_content = file_get_contents(plugin_dir_path(__FILE__) . 'output.txt');

    // Return the content for shortcode display
    return $output_content;
}
add_shortcode('sync_stock', 'skus_shortcode');
