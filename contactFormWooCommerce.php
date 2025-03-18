<?php

// 3. Integrate Contact Form 7 with WooCommerce Registration
// Redirect logged-in users to My Account page when accessing the registration form
function redirect_logged_in_user_from_registration() {
    if (is_user_logged_in() && is_page('register')) {
        wp_redirect(site_url('/my-account')); 
        exit;
    }
}
add_action('template_redirect', 'redirect_logged_in_user_from_registration');

// Modify the CF7 submission handler to redirect logged-in users
add_action('wpcf7_mail_sent', 'register_woocommerce_user');

function register_woocommerce_user($cf7) {
    if (is_user_logged_in()) {
        // Redirect logged-in users to My Account page
        wp_redirect(site_url('/my-account')); 
        exit;
    }

    $submission = WPCF7_Submission::get_instance();

    if ($submission) {
        $data = $submission->get_posted_data();

        $email = sanitize_email($data['user_email']);
        $password = sanitize_text_field($data['password-user']);
        $first_name = sanitize_text_field($data['first_name']);
        $last_name = sanitize_text_field($data['last_name']);
        $postal_code = sanitize_text_field($data['postal_code']);
        $birthday = sanitize_text_field($data['birthday']);

        // Extract username from email
        $username = strstr($email, '@', true);

        // Check if email already exists
        if (email_exists($email)) {
            return; 
        }

        // Create a WooCommerce user
        $user_id = wp_create_user($username, $password, $email);

        if (is_wp_error($user_id)) {
            return; 
        }

        // Update user meta
        wp_update_user([
            'ID' => $user_id,
            'first_name' => $first_name,
            'last_name' => $last_name,
        ]);

        update_user_meta($user_id, 'birthday', $birthday);

        // Update WooCommerce customer data using WC_Customer
        $customer = new WC_Customer($user_id);
        $customer->set_billing_postcode($postal_code);
        $customer->set_first_name($first_name);
        $customer->set_last_name($last_name);
        $customer->save(); 

        // Assign WooCommerce role
        $user = new WP_User($user_id);
        $user->set_role('customer');

        // Auto-login the user
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id);
        do_action('wp_login', $username, wp_get_current_user());
    }
}

// Redirect after successful form submission
function cf7_redirect_after_success() {
    ?>
    <script>
        document.addEventListener('wpcf7mailsent', function(event) {
            // Redirect the user to the "My Account" page after success
            window.location.href = "/my-account";
        }, false);
    </script>
    <?php
}
add_action('wp_footer', 'cf7_redirect_after_success');

?>