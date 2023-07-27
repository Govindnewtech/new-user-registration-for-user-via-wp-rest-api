<?php
/*
Plugin Name: User Registration API Plugin
Description: A custom plugin for user registration using the REST API.
Version: 1.0
Author: Govind Kewat
*/

// Activation hook
// Register the REST route for user registration
add_action('rest_api_init', 'wp_rest_user_endpoints');

function wp_rest_user_endpoints() {
  /**
   * Handle Register User request.
   */
  register_rest_route('wp/v2', 'users/register', array(
    'methods' => 'POST',
    'callback' => 'wc_rest_user_endpoint_handler',
  ));

  register_rest_route('wp/v2', 'users/login', array(
    'methods' => 'POST',
    'callback' => 'wc_rest_user_login_endpoint_handler',
  ));
}

/**
 * Handle Register User request.
 *
 * @param  WP_REST_Request $request Full details about the request.
 * @return WP_REST_Response $response.
 **/
function wc_rest_user_endpoint_handler($request = null) {
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
function generate_username_from_email($email) {
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


function wc_rest_user_login_endpoint_handler($request = null) {
  $response = array();
  $parameters = $request->get_json_params();
  $email_or_username = sanitize_text_field($parameters['email_or_username']);
  $password = sanitize_text_field($parameters['password']);

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
  $user = wp_signon(array(
    'user_login' => $username, // The login is based on the provided username (email)
    'user_password' => $password,
    'remember' => true,
  ));

  if (is_wp_error($user)) {
    // Login failed
    $error->add(401, __("Invalid email/username or password.", 'wp-rest-user'), array('status' => 401));
    return $error;
  } else {
    // Login successful
    $response['code'] = 200;
    $response['message'] = __("User login successful.", 'wp-rest-user');
    $response['user_id'] = $user->ID;
    $response['username'] = $user->user_login;
    $response['first_name'] = $user->first_name;
    $response['email'] = $user->user_email;
  }

  return new WP_REST_Response($response, 200);
}


// Add a custom menu in the admin dashboard
add_action('admin_menu', 'urap_add_menu');

function urap_add_menu() {
  add_menu_page(
    'User Registration API Plugin', // Page title
    'User Registration API', // Menu title
    'manage_options', // Capability
    'user_registration_api', // Menu slug
    'urap_display_instructions', // Callback function to display the page content
    'dashicons-admin-users' // Icon
  );
}
// Callback function to display the menu page content
function urap_display_instructions() {
    wp_enqueue_style('urap-styles', plugins_url('urap-styles.css', __FILE__));
  
    ?>
    <div class="wrap">
      <h1>Welcome To User Registration API Plugin</h1>
      <p class="author">Author: Govind Kewat</p>
      <p class="support">Support: <a href="mailto:govindkewat019@gmail.com">govindkewat019@gmail.com</a></p>
      
      <h2>User Registration API</h2>
      <p>Use the following endpoint to register a new user via the REST API:</p>
      <code>POST <?php echo esc_url_raw(rest_url('wp/v2/users/register')); ?></code>
      <p>Required Fields:</p>
      <ul>
        <li>first_name: The first name of the user.</li>
        <li>email: The email address of the user.</li>
        <li>password: The password for the user.</li>
      </ul>
      <p><strong>Example Request:</strong></p>
      <pre><code>
        POST <?php echo esc_url_raw(rest_url('wp/v2/users/register')); ?>
  
        Content-Type: application/json
  
        {
          "first_name": "John",
          "email": "john@example.com",
          "password": "password123"
        }
      </code></pre>
      <p><strong>Example Response:</strong></p>
      <pre><code>
        {
          "code": 200,
          "message": "User 'john123' Registration was Successful",
          "user": {
            "user_id": 123,
            "username": "john123",
            "first_name": "John",
            "email": "john@example.com"
          }
        }
      </code></pre>
  
      <h2>User Login API</h2>
      <p>Use the following endpoint to login a user via the REST API:</p>
      <code>POST <?php echo esc_url_raw(rest_url('wp/v2/users/login')); ?></code>
      <p>Required Fields:</p>
      <ul>
        <li>email_or_username: The email address or username of the user.</li>
        <li>password: The password for the user.</li>
      </ul>
      <p><strong>Example Request:</strong></p>
      <pre><code>
        POST <?php echo esc_url_raw(rest_url('wp/v2/users/login')); ?>
  
        Content-Type: application/json
  
        {
          "email_or_username": "john@example.com", 
          "password": "password123"
        }
      </code></pre>
      <p><strong>Example Response:</strong></p>
      <pre><code>
        {
          "code": 200,
          "message": "User login successful.",
          "user_id": 123,
          "username": "john123",
          "first_name": "John",
          "email": "john@example.com"
        }
      </code></pre>
    </div>
    <?php
  }


// Callback function to display the menu page content
function urap_display_instructions() {
  wp_enqueue_style('urap-styles', plugins_url('urap-styles.css', __FILE__));

  ?>
  <div class="wrap">
    <h1>Welcome To User Registration API Plugin</h1>
    <p class="author">Author: Govind Kewat</p>
    <p class="support">Support: <a href="mailto:govindkewat019@gmail.com">govindkewat019@gmail.com</a></p>
    <h3>User Registration API Plugin</h3>
    <p>Use the following endpoint to register a new user via the REST API:</p>
    <code>POST <?php echo esc_url_raw(rest_url('wp/v2/users/register')); ?></code>
    <p>Required Fields:</p>
    <ul>
      <li>first_name: The first name of the user.</li>
      <li>email: The email address of the user.</li>
      <li>password: The password for the user.</li>
    </ul>
    <p><strong>Example Request:</strong></p>
    <pre><code>
      POST <?php echo esc_url_raw(rest_url('wp/v2/users/register')); ?>

      Content-Type: application/json

      {
        "first_name": "John",
        "email": "john@example.com",
        "password": "password123"
      }
    </code></pre>
    <p><strong>Example Response:</strong></p>
    <pre><code>
      {
        "code": 200,
        "message": "User 'john123' Registration was Successful",
        "user": {
          "user_id": 123,
          "username": "john123",
          "first_name": "John",
          "email": "john@example.com"
        }
      }
    </code></pre>
  </div>
  <?php
}
