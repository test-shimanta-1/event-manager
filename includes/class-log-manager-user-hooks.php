<?php
/**
 * user related event hooks class file
 * 
 * Handles logging of user authentication events such as
 * login success, login failure, and logout.
 * 
 * @since 1.0.2
 * @package Log_Manager
 */

class Log_Manager_User_Hooks
{
    /**
     * Register user authentication-related hooks.
     *
     * Hooks into:
     * - set_logged_in_cookie: Logs successful user login events.
     * - wp_login_failed: Logs failed login attempts.
     * - wp_logout: Logs user logout events.
     *
     * @return void
     */
    public function __construct()
    {
        add_action('set_logged_in_cookie', [$this, 'sdw_log_successful_login'], 10, 4);
        add_action('wp_logout', [$this, 'sdw_log_user_logout']);
        add_filter('authenticate', [$this, 'sdw_capture_all_login_attempts'], 30, 3);
    }

    /**
     * Log successful user login events.
     *
     * This method is triggered after a user is successfully authenticated
     * and a login cookie is set. It records user details such as role,
     * email, full name, IP address, and login time.
     *
     *
     * @param string $cookie     Authentication cookie.
     * @param int    $expire     Cookie expiration time.
     * @param int    $expiration Cookie expiration timestamp.
     * @param int    $user_id    Logged-in user ID.
     *
     * @return void
     */
    public function sdw_log_successful_login($cookie, $expire, $expiration, $user_id)
    {
        $user_info = get_userdata($user_id);
        $user_role = implode(', ', $user_info->roles);

        $full_name = $this->sdw_get_user_full_name($user_info);
        $message = 'Login successful. ';
        $message .= 'User ID: ' . $user_id . ', ';
        $message .= 'Role: ' . $user_role . ', ';
        $message .= 'Email: ' . $user_info->user_email;

        if ($full_name !== '') {
            $message .= ', Full Name: ' . $full_name;
        }

        Log_Manager_Logger::insert([
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'userid' => $user_id,
            'event_time' => current_time('mysql'),
            'object_type' => 'User',
            'severity' => 'info',
            'event_type' => 'logged-in',
            'message' => $message,
        ]);
    }

    
    /**
     * Log user logout events.
     *
     * This method records an event when a logged-in user logs out
     * of the system. It logs user details, role, email, and IP address.
     *
     * @param int $user_id Logged-out user ID.
     *
     * @return void
     */
    public function sdw_log_user_logout($user_id)
    {
        if (!$user_id) {
            return;
        }

        $user_info = get_userdata($user_id);
        if (!$user_info) {
            return;
        }

        $user_role = implode(', ', $user_info->roles);
        $full_name = $this->sdw_get_user_full_name($user_info);

        // Build clean message
        $message  = 'User logged out. ';
        $message .= 'User ID: ' . $user_id;
        $message .= ', Role: ' . $user_role;
        $message .= ', Email: ' . $user_info->user_email;

        if ($full_name !== '') {
            $message .= ', Full Name: ' . $full_name;
        }

        Log_Manager_Logger::insert([
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'userid'     => $user_id,
            'event_time' => current_time('mysql'),
            'object_type'=> 'User',
            'severity'   => 'info',
            'event_type' => 'logout',
            'message'    => $message,
        ]);
    }


    /**
     * Log failed login attempts via the authenticate filter.
     *
     * Captures failed authentication for existing users
     * (wrong password) and non-existent users, while
     * ignoring successful logins and logout requests.
     *
     * @since 1.0.2
     *
     * @param WP_User|WP_Error|null $user
     * @param string               $username
     * @param string               $password
     *
     * @return WP_User|WP_Error|null
     */
    public function sdw_capture_all_login_attempts($user, $username, $password)
    {
        // Skip during logout
        if (did_action('wp_logout')) {
            return $user;
        }

        // Skip empty username (logout / cron / API)
        if (empty($username)) {
            return $user;
        }

        // Only handle failed authentication
        if (!is_wp_error($user)) {
            return $user;
        }

        // Try to find user by username
        $user_obj = get_user_by('login', $username);

        // If input is email, try finding user by email
        if (!$user_obj && is_email($username)) {
            $user_obj = get_user_by('email', $username);
        }

        // Existing user → wrong password
        if ($user_obj instanceof WP_User) {

            $real_username = $user_obj->user_login;
            $full_name     = $this->sdw_get_user_full_name($user_obj);

            $message  = 'Wrong password for username: ' . $real_username . '. ';
            $message .= 'User ID: ' . $user_obj->ID;
            $message .= ', Email: ' . $user_obj->user_email;

            if ($full_name !== '') {
                $message .= ', Full Name: ' . $full_name;
            }

            Log_Manager_Logger::insert([
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'userid'     => $user_obj->ID,
                'event_time' => current_time('mysql'),
                'object_type'=> 'User',
                'severity'   => 'warning',
                'event_type' => 'login-failed',
                'message'    => $message,
            ]);

            return $user;
        }

        // Non-existent user
        Log_Manager_Logger::insert([
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'userid'     => 0,
            'event_time' => current_time('mysql'),
            'object_type'=> 'User',
            'severity'   => 'warning',
            'event_type' => 'login-failed',
            'message'    => 'Login attempt with non-existent username: ' . $username,
        ]);

        return $user;
    }

    /**
     * Get the user's full name safely.
     *
     * This helper prevents storing blank or meaningless name
     * values in the log records.
     *
     * @since 1.0.2
     *
     * @param WP_User $user User object.
     *
     * @return string Full name if available, otherwise empty string.
     */
    private function sdw_get_user_full_name($user)
    {
        if (!$user instanceof WP_User) {
            return '';
        }

        $first = trim($user->user_firstname ?? '');
        $last  = trim($user->user_lastname ?? '');

        if ($first === '' && $last === '') {
            return '';
        }

        return trim($first . ' ' . $last);
    }

}