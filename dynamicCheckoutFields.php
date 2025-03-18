<?php

// 4. Dynamic Checkout Fields by Language with GTranslate Integration
// For Tagalog (Philiphines)
// Language detection function for GTranslate
function get_current_language() {
    if (isset($_COOKIE['googtrans'])) {
        $cookie_value = sanitize_text_field($_COOKIE['googtrans']);
        $language_parts = explode('/', $cookie_value);
        $current_lang = end($language_parts);
        if (!empty($current_lang) && $current_lang !== '/') {
            return strtolower($current_lang);
        }
    }
    if (function_exists('gtranslate_get_current_language')) {
        $gt_lang = strtolower(gtranslate_get_current_language());
        if (!empty($gt_lang)) {
            return $gt_lang;
        }
    }
    $locale = get_locale();
    return substr($locale, 0, 2);
}

// Main function to modify checkout fields
add_filter('woocommerce_checkout_fields', function($fields) {
    if (get_current_language() === 'tl') {
        unset($fields['billing']['billing_company']);
        unset($fields['billing']['billing_address_2']);
        $new_fields = array();
        foreach ($fields['billing'] as $key => $field) {
            $new_fields[$key] = $field;
            if ($key === 'billing_address_1') {
                $new_fields['billing_building'] = array(
                    'type'        => 'text',
                    'label'       => 'Building Name/Number',
                    'placeholder' => 'Pangalan/Numero ng Building',
                    'required'    => false,
                    'class'       => array('form-row-wide'),
                    'priority'    => 55
                );
                $new_fields['billing_division'] = array(
                    'type'        => 'text',
                    'label'       => 'Division / Barangay',
                    'placeholder' => 'Division / Barangay',
                    'required'    => false,
                    'class'       => array('form-row-wide'),
                    'priority'    => 56
                );
            }
        }
        $fields['billing'] = $new_fields;
    }
    return $fields;
}, 99);

// Save data using checkout_update_order_meta
add_action('woocommerce_checkout_update_order_meta', function($order_id) {
    // Save individual custom fields
    if (!empty($_POST['billing_building'])) {
        update_post_meta($order_id, '_billing_building', sanitize_text_field($_POST['billing_building']));
    }
    if (!empty($_POST['billing_division'])) {
        update_post_meta($order_id, '_billing_division', sanitize_text_field($_POST['billing_division']));
    }

    // Get the custom fields
    $building = get_post_meta($order_id, '_billing_building', true);
    $division = get_post_meta($order_id, '_billing_division', true);

    // Get current billing address details
    $order = wc_get_order($order_id);
    if ($order) {
        $current_address = $order->get_billing_address_1();

        // Build the formatted address
        $address_parts = array_filter([
            $building ? $building : '',
            $current_address,
            $division ? $division : ''
        ]);
        $formatted_address = implode(', ', $address_parts);

        // Save the formatted address in _billing_address_index
        update_post_meta($order_id, '_billing_address_index', $formatted_address);
    }
});

// Display additional fields to the user
function display_additional_fields_to_user($order_id) { ?>
    <h2><?php _e('Extra Information'); ?></h2>
    <table class="shop_table shop_table_responsive additional_info">
        <tbody>
            <tr>
                <th><?php _e('Building Name/Number:'); ?></th>
                <td><?php echo esc_html(get_post_meta($order_id, '_billing_building', true)); ?></td>
            </tr>
            <tr>
                <th><?php _e('Division / Barangay:'); ?></th>
                <td><?php echo esc_html(get_post_meta($order_id, '_billing_division', true)); ?></td>
            </tr>
        </tbody>
    </table>
<?php }
add_action('woocommerce_thankyou', 'display_additional_fields_to_user', 20);
add_action('woocommerce_view_order', 'display_additional_fields_to_user', 20);

// Display and edit data on WC Dashboard order details page
add_action('woocommerce_admin_order_data_after_order_details', function($order) { ?>
    <div class="order_data_column" style="width: 200px;">
        <h4><?php _e('Additional Information', 'woocommerce'); ?><a href="#" class="edit_address"><?php _e('Edit', 'woocommerce'); ?></a></h4>
        <div class="address" style="width: 500px;">
            <?php
            $building = get_post_meta($order->get_id(), '_billing_building', true);
            $division = get_post_meta($order->get_id(), '_billing_division', true);
            ?>
            <p><strong><?php _e('Building Name/Number:', 'woocommerce'); ?></strong> <?php echo esc_html($building); ?></p>
            <p><strong><?php _e('Division / Barangay:', 'woocommerce'); ?></strong> <?php echo esc_html($division); ?></p>
        </div>
        <div class="edit_address" style="width: 500px;">
            <?php
            woocommerce_wp_text_input(array(
                'id'            => '_billing_building',
                'label'         => __('Building Name/Number', 'woocommerce'),
                'placeholder'   => __('Enter Building Name/Number', 'woocommerce'),
                'value'         => $building,
                'wrapper_class' => '_billing_building_field'
            ));
            woocommerce_wp_text_input(array(
                'id'            => '_billing_division',
                'label'         => __('Division / Barangay', 'woocommerce'),
                'placeholder'   => __('Enter Division / Barangay', 'woocommerce'),
                'value'         => $division,
                'wrapper_class' => '_billing_division_field'
            ));
            ?>
        </div>
    </div>
<?php });

// Save updated fields from admin and update the full address
add_action('woocommerce_process_shop_order_meta', function($post_id) {
    if (!$post_id) {
        return;
    }

    // Check and sanitize inputs
    $building = isset($_POST['_billing_building']) ? sanitize_text_field($_POST['_billing_building']) : '';
    $division = isset($_POST['_billing_division']) ? sanitize_text_field($_POST['_billing_division']) : '';

    // Update the post meta for individual fields
    if ($building) {
        update_post_meta($post_id, '_billing_building', $building);
    }
    if ($division) {
        update_post_meta($post_id, '_billing_division', $division);
    }

    // Update the formatted billing address
    $order = wc_get_order($post_id);
    if ($order) {
        $current_address = $order->get_billing_address_1();
        $address_parts = array_filter([$building, $current_address, $division]);
        $formatted_address = implode(', ', $address_parts);

        // Save the formatted address in _billing_address_index
        update_post_meta($post_id, '_billing_address_index', $formatted_address);
    }
});

// Add fields to order emails
add_filter('woocommerce_email_order_meta_fields', function($fields, $sent_to_admin, $order) {
    $building = get_post_meta($order->get_id(), '_billing_building', true);
    $division = get_post_meta($order->get_id(), '_billing_division', true);
    if ($building) {
        $fields['billing_building'] = array(
            'label' => __('Building Name/Number'),
            'value' => $building
        );
    }
    if ($division) {
        $fields['billing_division'] = array(
            'label' => __('Division / Barangay'),
            'value' => $division
        );
    }
    return $fields;
}, 10, 3);


?>