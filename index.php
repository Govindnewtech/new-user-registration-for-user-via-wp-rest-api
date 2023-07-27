<?php
/*
Plugin Name: User Login & Registration API Plugin
Description: A custom plugin for user registration and login using the REST API.
Version: 1.0
Author: Govind Kewat
*/

// Activation hook
// Register the REST routes for user registration and login
add_action('rest_api_init', 'wp_rest_user_endpoints');

function wp_rest_user_endpoints()
{
    /**
     * Handle Register User request.
     */
    register_rest_route('wp/v2', 'users/register', array(
        'methods' => 'POST',
        'callback' => 'wc_rest_user_register_endpoint_handler',
    )
    );

    /**
     * Handle User Login request.
     */
    register_rest_route('wp/v2', 'users/login', array(
        'methods' => 'POST',
        'callback' => 'wc_rest_user_login_endpoint_handler',
    )
    );
}

/**
 * Handle Register User request.
 *
 * @param  WP_REST_Request $request Full details about the request.
 * @return WP_REST_Response $response.
 **/
function wc_rest_user_register_endpoint_handler($request = null)
{
    // Rest of the code for user registration remains unchanged
    // ...
}

/**
 * Handle User Login request.
 *
 * @param  WP_REST_Request $request Full details about the request.
 * @return WP_REST_Response $response.
 **/
// Make sure to install and activate the "jwt-authentication-for-wp-rest-api" plugin first

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


// Function to generate JWT token
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




// Rest of the code remains unchanged
// ...

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
// Callback function to display the menu page content
function urap_display_instructions()
{
    wp_enqueue_style('urap-styles', plugins_url('urap-styles.css', __FILE__));

    ?>
    <div class="wrap">
        <h1>Welcome To  User Login & Registration API Plugin</h1>
        <p class="author">Author: Govind Kewat</p>
        <p class="support">Support Email: <a href="mailto:govindkewat019@gmail.com">govindkewat019@gmail.com</a></p>

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
                "user_id": 13,
                "username": "john1",
                "first_name": "John",
                "email": "john@example.com",
                "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9"
           }
          </code></pre>
    </div>
    <?php
}