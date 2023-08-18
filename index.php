<?php
/*
Plugin Name: User Registration API Plugin
Description: A custom plugin for user registration using the REST API.
Version: 1.0
Author: Govind Kewat
*/

// Define routes
define('URAP_REGISTER_ROUTE', 'wp/v2/users/register');
define('URAP_LOGIN_ROUTE', 'wp/v2/users/login');
define('URAP_FORGOT_PASSWORD_ROUTE', 'wp/v2/forgot-password');
define('URAP_CHANGE_PASSWORD_ROUTE', 'wp/v2/change-password');
define('URAP_DELETE_USER_ROUTE', 'wp/v2/users/delete');
define('URAP_CHECK_EMAIL_ROUTE', 'custom/v2/check-email');


// Register the REST route for user registration
add_action('rest_api_init', 'wp_rest_user_endpoints');

function wp_rest_user_endpoints()
{
  /**
   * Handle Register User request.
   */
  register_rest_route(
    'wp/v2',
    'users/register',
    array(
      'methods' => 'POST',
      'callback' => 'wc_rest_user_endpoint_handler',
    )
  );

  register_rest_route(
    'wp/v2',
    'users/login',
    array(
      'methods' => 'POST',
      'callback' => 'wc_rest_user_login_endpoint_handler',
    )
  );

  register_rest_route(
    'wp/v2',
    '/forgot-password',
    array(
      'methods' => 'POST',
      'callback' => 'custom_forgot_password_callback',
    )
  );

  // Change Password
  register_rest_route(
    'wp/v2',
    '/change-password',
    array(
      'methods' => 'POST',
      'callback' => 'custom_change_password_callback',
    )
  );
  // Delete User route
  register_rest_route('wp/v2', 'users/delete', array(
    'methods' => 'POST',
    'callback' => 'wc_rest_user_endpoint_deleteuser',
  )
  );

  // Custom email check route
  register_rest_route('custom/v2', 'check-email', array(
    'methods' => 'GET',
    'callback' => 'custom_check_email_exists',
  )
  );

}

/**
 * Handle Register User request.
 *
 * @param  WP_REST_Request $request Full details about the request.
 * @return WP_REST_Response $response.
 **/
function wc_rest_user_endpoint_handler($request = null)
{
  $response = array();
  $parameters = $request->get_json_params();
  $firstname = sanitize_text_field($parameters['first_name']);
  $email = sanitize_email($parameters['email']);
  $password = sanitize_text_field($parameters['password']);

  $error = new WP_Error();

  // Generate username based on the email if not provided
  $username = (!empty($parameters['username'])) ? sanitize_user($parameters['username']) : generate_username_from_email($email);

  if (empty($firstname)) {
    $error->add(400, __("First name field 'first_name' is required.", 'wp-rest-user'), array('status' => 400));
    return $error;
  }
  if (empty($email)) {
    $error->add(401, __("Email field 'email' is required.", 'wp-rest-user'), array('status' => 400));
    return $error;
  }
  if (empty($password)) {
    $error->add(404, __("Password field 'password' is required.", 'wp-rest-user'), array('status' => 400));
    return $error;
  }

  $user_id = username_exists($username);
  if (!$user_id && email_exists($email) == false) {
    $user_id = wp_create_user($username, $password, $email);
    if (!is_wp_error($user_id)) {
      // Set first name
      update_user_meta($user_id, 'first_name', $firstname);

      // Get User Meta Data (Sensitive, Password included. DO NOT pass to front end.)
      $user = get_user_by('id', $user_id);
      $user->set_role('subscriber');
      // WooCommerce specific code
      if (class_exists('WooCommerce')) {
        $user->set_role('customer');
      }
      // Get User Data (Non-Sensitive, Pass to front end.)
      $response['code'] = 200;
      $response['message'] = sprintf(__("User '%s' Registration was Successful", 'wp-rest-user'), $username);
      $response['user'] = array(
        'user_id' => $user_id,
        'username' => $username,
        'first_name' => $firstname,
        'email' => $email,
      );
    } else {
      return $user_id;
    }
  } else {
    $error->add(406, __("Email already exists, please try 'Reset Password'", 'wp-rest-user'), array('status' => 400));
    return $error;
  }
  return new WP_REST_Response($response, 200);
}

/**
 * Generate a username from the email address
 *
 * @param  string $email User's email address.
 * @return string        Generated username.
 */
function generate_username_from_email($email)
{
  $username = '';
  if ($email) {
    // Use the email username as the basis for the username
    $email_parts = explode('@', $email);
    $username = sanitize_user($email_parts[0]);

    // Check if the generated username already exists, if so, add a unique suffix
    $suffix = 1;
    while (username_exists($username . $suffix)) {
      $suffix++;
    }

    $username = $username . $suffix;
  }
  return $username;
}

function wc_rest_user_login_endpoint_handler($request = null)
{
  $response = array();
  $parameters = $request->get_json_params();
  $email_or_username = isset($parameters['email_or_username']) ? $parameters['email_or_username'] : '';
  $password = isset($parameters['password']) ? $parameters['password'] : '';

  $error = new WP_Error();

  if (empty($email_or_username)) {
    $error->add(400, __("Email or username field 'email_or_username' is required.", 'wp-rest-user'), array('status' => 400));
    return $error;
  }
  if (empty($password)) {
    $error->add(401, __("Password field 'password' is required.", 'wp-rest-user'), array('status' => 400));
    return $error;
  }

  // Try to log in the user using email
  $user = get_user_by('email', $email_or_username);

  // If user not found by email, try using username
  if (!$user) {
    $user = get_user_by('login', $email_or_username);
  }

  if (!$user) {
    // User not found by email or username
    $error->add(401, __("Invalid email/username or password.", 'wp-rest-user'), array('status' => 401));
    return $error;
  }

  $username = $user->user_login;

  // Continue with the existing login code using the $username

  // Try to log in the user
  $user = wp_signon(
    array(
      'user_login' => $username,
      // The login is based on the provided username (email)
      'user_password' => $password,
      'remember' => true,
    )
  );

  if (is_wp_error($user)) {
    // Login failed
    $error->add(401, __("Invalid email/username or password.", 'wp-rest-user'), array('status' => 401));
    return $error;
  } else {
    // Login successful
    $user_id = $user->ID;
    $secret_key = 'your_secret_key'; // Replace with your secret key
    $token = generate_jwt_token($user_id, $secret_key); // Generate JWT token

    $response['code'] = 200;
    $response['message'] = __("User login successful.", 'wp-rest-user');
    $response['user_id'] = $user_id;
    $response['username'] = $user->user_login;
    $response['first_name'] = $user->first_name;
    $response['email'] = $user->user_email;
    $response['token'] = $token; // Include the token in the response
  }

  return new WP_REST_Response($response, 200);
}

function generate_jwt_token($user_id, $secret_key, $expiration_time = 3600)
{
  $header = [
    'typ' => 'JWT',
    'alg' => 'HS256'
  ];

  $payload = [
    'user_id' => $user_id,
    'exp' => time() + $expiration_time
  ];

  $header_encoded = base64_encode(json_encode($header));
  $payload_encoded = base64_encode(json_encode($payload));

  $signature = hash_hmac('sha256', $header_encoded . '.' . $payload_encoded, $secret_key, true);
  $signature_encoded = base64_encode($signature);

  $jwt_token = $header_encoded . '.' . $payload_encoded . '.' . $signature_encoded;
  return $jwt_token;
}

// Callback function to handle the password reset request
function custom_forgot_password_callback($request)
{
  $email = $request->get_param('email');

  // Check if the email is valid and exists in the system
  $user = get_user_by('email', $email);
  if (!$user) {
    return new WP_Error('invalid_email', 'Invalid email address.', array('status' => 400));
  }

  // Generate the password reset URL
  $reset_url = esc_url_raw(
    add_query_arg(
      array(
        'action' => 'rp',
        'key' => get_password_reset_key($user),
        'login' => rawurlencode($user->user_login),
      ),
      wp_lostpassword_url()
    )
  );

  // Send the password reset email to the user
  $subject = 'Password Reset Request';
  $message = 'Click the following link to reset your password: ' . $reset_url;

  $sent = wp_mail($email, $subject, $message);

  if ($sent) {
    return array('message' => 'Password reset email sent.');
  } else {
    return new WP_Error('email_failed', 'Failed to send password reset email.', array('status' => 500));
  }
}

// Callback function to handle password change request
function custom_change_password_callback($request)
{
  $user_id = $request->get_param('user_id');
  $old_password = $request->get_param('old_password');
  $new_password = $request->get_param('new_password');

  // Get the user object
  $user = get_user_by('ID', $user_id);

  // Check if the user is valid and passwords match
  if (!$user || !wp_check_password($old_password, $user->user_pass, $user->ID)) {
    return new WP_Error('invalid_user', 'Invalid user credentials.', array('status' => 400));
  }

  // Update the user's password
  wp_set_password($new_password, $user->ID);

  return array('message' => 'Password updated successfully.');
}



function wc_rest_user_endpoint_deleteuser($request)
{
  // Get JSON parameters from the request
  $parameters = $request->get_json_params();

  // Get email parameter from the request
  $email = $parameters['email'];

  // Get user_id parameter from the request
  $id = $parameters['user_id'];

  // Check if the user with the provided email exists
  if (email_exists($email)) {
    $user = get_user_by('email', $email);

    // Check if the provided user_id matches the found user's ID
    if ($user && $id == $user->ID) {
      require_once(ABSPATH . 'wp-admin/includes/user.php');
      wp_delete_user($user->ID);
      return new WP_REST_Response([
        'message' => 'User deleted successfully',
      ], 200);
    } else {
      return new WP_REST_Response([
        'message' => 'User not found',
      ], 400);
    }
  } else {
    return new WP_REST_Response([
      'message' => 'User not found',
    ], 400);
  }
}
function custom_check_email_exists($request)
{
  $email = $request->get_param('email');

  if (email_exists($email)) {
    $user = get_user_by('email', $email); // Retrieve user by email
    $user_id = $user->ID;
    $secret_key = 'your_secret_key'; // Replace with your secret key
    $token = generate_jwt_token($user_id, $secret_key); // Generate JWT token
    if ($user) {
      $response['code'] = 200;
      $response['message'] = 'User details retrieved.';
      $response['user'] = array(
        'user_id' => $user->ID,
        'username' => $user->user_login,
        'first_name'=>$user->first_name,
        'email' => $user->user_email,
        'token'=> $token
        // Add more fields as needed
      );
    } else {
      $response['code'] = 500; // Internal Server Error
      $response['message'] = 'Failed to retrieve user details.';
    }
  } else {
    $response['code'] = 404;
    $response['message'] = 'Email does not exist.';
  }



  return rest_ensure_response($response);
}


// Add a custom menu in the admin dashboard
add_action('admin_menu', 'urap_add_menu');

function urap_add_menu()
{
  add_menu_page(
    'User Registration API Plugin',
    // Page title
    'User Registration API',
    // Menu title
    'manage_options',
    // Capability
    'user_registration_api',
    // Menu slug
    'urap_display_instructions',
    // Callback function to display the page content
    'dashicons-admin-users' // Icon
  );
}
// Callback function to display the menu page content
include 'home.php';
