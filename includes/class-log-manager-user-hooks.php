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
        $user = get_userdata($user_id);
        if (!$user) {
            return;
        }

        $message = sprintf(
            'Login successful.<br/>User ID: <b>%d</b><br/>Role: <b>%s</b><br/>Email: <b>%s</b>%s',
            $user_id,
            esc_html(implode(', ', $user->roles)),
            esc_html($user->user_email),
            $this->sdw_get_user_full_name($user)
        );

        Log_Manager_Logger::insert([
            'ip_address'  => sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? ''),
            'userid'      => $user_id,
            'event_time'  => current_time('mysql'),
            'object_type' => 'User',
            'severity'    => 'info',
            'event_type'  => 'logged-in',
            'message'     => $message,
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

        $user = get_userdata($user_id);
        if (!$user) {
            return;
        }

        $message = sprintf(
            'User logged out.<br/>User ID: <b>%d</b><br/>Role: <b>%s</b><br/>Email: <b>%s</b>%s',
            $user_id,
            esc_html(implode(', ', $user->roles)),
            esc_html($user->user_email),
            $this->sdw_get_user_full_name($user)
        );

        Log_Manager_Logger::insert([
            'ip_address'  => sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? ''),
            'userid'      => $user_id,
            'event_time'  => current_time('mysql'),
            'object_type' => 'User',
            'severity'    => 'info',
            'event_type'  => 'logout',
            'message'     => $message,
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

        // Ignore successful authentication
        if (!is_wp_error($user) || empty($username)) {
            return $user;
        }

        $ip = sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? '');

        // Try resolving user
        $user_obj = get_user_by('login', $username);
        if (!$user_obj && is_email($username)) {
            $user_obj = get_user_by('email', $username);
        }

        /**
         * Existing user → wrong password (WARNING)
         */
        if ($user_obj instanceof WP_User) {

            $message = sprintf(
                'Wrong password attempt.<br/>User ID: <b>%d</b><br/>Username: <b>%s</b><br/>Email: <b>%s</b>%s',
                $user_obj->ID,
                esc_html($user_obj->user_login),
                esc_html($user_obj->user_email),
                $this->sdw_get_user_full_name($user_obj)
            );

            Log_Manager_Logger::insert([
                'ip_address'  => $ip,
                'userid'      => $user_obj->ID,
                'event_time'  => current_time('mysql'),
                'object_type' => 'User',
                'severity'    => 'warning',
                'event_type'  => 'login-failed',
                'message'     => $message,
            ]);

            return $user;
        }

        /**
         * Non-existent user → ALERT
         */
        Log_Manager_Logger::insert([
            'ip_address'  => $ip,
            'userid'      => 0,
            'event_time'  => current_time('mysql'),
            'object_type' => 'User',
            'severity'    => 'alert',
            'event_type'  => 'login-failed',
            'message'     => 'Login attempt with non-existent username: <b>' . esc_html($username) . '</b>',
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

        $first = trim($user->user_firstname);
        $last  = trim($user->user_lastname);

        if ($first === '' && $last === '') {
            return '';
        }

        return '<br/>Full Name: <b>' . esc_html(trim($first . ' ' . $last)) . '</b>';

    }

}