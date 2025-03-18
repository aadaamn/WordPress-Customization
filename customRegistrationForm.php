<?php

// 2. Adding Custom Fields to the WooCommerce Registration Form
// Add custom fields to the WooCommerce registration form
add_action('woocommerce_register_form', 'custom_woocommerce_registration_fields');
function custom_woocommerce_registration_fields() {
    ?>
    <!-- First Name -->
    <p class="form-row form-row-first">
        <label for="reg_first_name"><?php esc_html_e('First Name', 'woocommerce'); ?> <span class="required">*</span></label>
        <input type="text" class="input-text" name="first_name" id="reg_first_name" value="<?php echo isset($_POST['first_name']) ? esc_attr($_POST['first_name']) : ''; ?>" required />
    </p>

    <!-- Last Name -->
    <p class="form-row form-row-last">
        <label for="reg_last_name"><?php esc_html_e('Last Name', 'woocommerce'); ?> <span class="required">*</span></label>
        <input type="text" class="input-text" name="last_name" id="reg_last_name" value="<?php echo isset($_POST['last_name']) ? esc_attr($_POST['last_name']) : ''; ?>" required />
    </p>

    <!-- Postal Code -->
    <p class="form-row form-row-wide">
        <label for="reg_postal_code"><?php esc_html_e('Postal Code', 'woocommerce'); ?></label>
        <input type="text" class="input-text" name="postal_code" id="reg_postal_code" value="<?php echo isset($_POST['postal_code']) ? esc_attr($_POST['postal_code']) : ''; ?>" />
    </p>

    <!-- Birthday -->
    <p class="form-row form-row-wide">
        <label for="reg_birthday"><?php esc_html_e('Birthday', 'woocommerce'); ?></label>
        <input type="date" class="input-text" name="birthday" id="reg_birthday" value="<?php echo isset($_POST['birthday']) ? esc_attr($_POST['birthday']) : ''; ?>" />
    </p>

    <!-- Gender -->
    <p class="form-row form-row-wide">
        <label for="reg_gender"><?php esc_html_e('Gender', 'woocommerce'); ?></label>
        <span>
            <label><input type="radio" name="gender" value="male" <?php checked(isset($_POST['gender']) && $_POST['gender'] === 'male'); ?>> <?php esc_html_e('Male', 'woocommerce'); ?></label>
            <label><input type="radio" name="gender" value="female" <?php checked(isset($_POST['gender']) && $_POST['gender'] === 'female'); ?>> <?php esc_html_e('Female', 'woocommerce'); ?></label>
            <label><input type="radio" name="gender" value="prefer_not_to_state" <?php checked(isset($_POST['gender']) && $_POST['gender'] === 'prefer_not_to_state'); ?>> <?php esc_html_e('Prefer Not To State', 'woocommerce'); ?></label>
        </span>
    </p>
    <?php
}

// Validate the custom fields during registration
add_action('woocommerce_register_post', 'validate_custom_fields', 10, 3);
function validate_custom_fields($username, $email, $validation_errors) {
    // Validate First Name
    if (empty($_POST['first_name'])) {
        $validation_errors->add('first_name_error', __('First Name is required.', 'woocommerce'));
    }
    
    // Validate Last Name
    if (empty($_POST['last_name'])) {
        $validation_errors->add('last_name_error', __('Last Name is required.', 'woocommerce'));
    }
    
    return $validation_errors;
}

// Save custom fields to the database during registration
add_action('woocommerce_created_customer', 'save_custom_fields');
function save_custom_fields($customer_id) {
    if (isset($_POST['first_name'])) {
        update_user_meta($customer_id, 'first_name', sanitize_text_field($_POST['first_name']));
    }
    if (isset($_POST['last_name'])) {
        update_user_meta($customer_id, 'last_name', sanitize_text_field($_POST['last_name']));
    }
    if (isset($_POST['postal_code'])) {
        update_user_meta($customer_id, 'billing_postcode', sanitize_text_field($_POST['postal_code'])); // Sync to WooCommerce billing field
    }
    if (isset($_POST['birthday'])) {
        update_user_meta($customer_id, 'birthday', sanitize_text_field($_POST['birthday']));
    }
    if (isset($_POST['gender'])) {
        update_user_meta($customer_id, 'gender', sanitize_text_field($_POST['gender']));
    }
}

// Add custom fields to the "My Account" page
add_action('woocommerce_edit_account_form', 'add_custom_fields_to_my_account');
function add_custom_fields_to_my_account() {
    $user_id = get_current_user_id();
    ?>
    <!-- Postal Code -->
    <p class="form-row form-row-wide">
        <label for="account_postal_code"><?php esc_html_e('Postal Code', 'woocommerce'); ?></label>
        <input type="text" name="postal_code" id="account_postal_code" value="<?php echo esc_attr(get_user_meta($user_id, 'billing_postcode', true)); ?>" />
    </p>

    <!-- Birthday -->
    <p class="form-row form-row-wide">
        <label for="account_birthday"><?php esc_html_e('Birthday', 'woocommerce'); ?></label>
        <input type="date" name="birthday" id="account_birthday" value="<?php echo esc_attr(get_user_meta($user_id, 'birthday', true)); ?>" />
    </p>

    <!-- Gender -->
    <p class="form-row form-row-wide">
        <label for="account_gender"><?php esc_html_e('Gender', 'woocommerce'); ?></label>
        <span>
            <label><input type="radio" name="gender" value="male" <?php checked(get_user_meta($user_id, 'gender', true), 'male'); ?>> <?php esc_html_e('Male', 'woocommerce'); ?></label>
            <label><input type="radio" name="gender" value="female" <?php checked(get_user_meta($user_id, 'gender', true), 'female'); ?>> <?php esc_html_e('Female', 'woocommerce'); ?></label>
            <label><input type="radio" name="gender" value="prefer_not_to_state" <?php checked(get_user_meta($user_id, 'gender', true), 'prefer_not_to_state'); ?>> <?php esc_html_e('Prefer Not To State', 'woocommerce'); ?></label>
        </span>
    </p>
    <?php
}

// Save custom fields from "My Account" page
add_action('woocommerce_save_account_details', 'save_my_account_custom_fields');
function save_my_account_custom_fields($user_id) {
    if (isset($_POST['postal_code'])) {
        $postal_code = sanitize_text_field($_POST['postal_code']);
        update_user_meta($user_id, 'billing_postcode', $postal_code);

        // Update the customer lookup table
        $customer = new WC_Customer($user_id);
        $customer->set_billing_postcode($postal_code);
        $customer->save(); 
    }

    if (isset($_POST['birthday'])) {
        update_user_meta($user_id, 'birthday', sanitize_text_field($_POST['birthday']));
    }

    if (isset($_POST['gender'])) {
        update_user_meta($user_id, 'gender', sanitize_text_field($_POST['gender']));
    }
}

?>