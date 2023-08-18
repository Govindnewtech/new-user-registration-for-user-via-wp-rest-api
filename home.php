<?php
function urap_display_instructions()
{
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
    <p><strong> Password Reset:</strong></p>

    <code>POST <?php echo esc_url_raw(rest_url('wp/v2/forgot-password')); ?></code>


    <pre><code>
          Content-Type: application/json

          {
            "email": "user@example.com"
          }

          //response message
          {
            "message": "Password reset email sent."
          }
          </pre></code>
    <p><strong> Password Reset:</strong></p>

    <code>POST <?php echo esc_url_raw(rest_url('wp/v2/change-password')); ?></code>

    <pre><code>

          Content-Type: application/json

          {
            "user_id": 123,
            "old_password": "old_password_here",
            "new_password": "new_password_here"
          }
          //response message
          {
          "message": "Password updated successfully."
          }
          </pre></code>
    <p><strong>Account Deletion API:</strong></p>
    <p>Use the following endpoint to delete a user account via the REST API:</p>
    <code>POST <?php echo esc_url_raw(rest_url('wp/v2/users/delete')); ?></code>
    <p>Required Parameter:</p>
    <p><strong>Example Request:</strong></p>
    <pre><code>
      POST <?php echo esc_url_raw(rest_url('wp/v2/users/delete')); ?>

      Content-Type: application/json

      {  
          "user_id": 3699,
          "email": "johnone@example.com"
      }

      </code></pre>
    <pre><code>
      {
        "code": 200,
        "message": "User account deleted successfully."
      }
      </pre></code>


  </div>
  <?php
}