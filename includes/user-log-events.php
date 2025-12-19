<?php 

/** login success */
add_action( 'set_logged_in_cookie', 'log_successful_login', 10, 4 );
function log_successful_login( $cookie, $expire, $expiration, $user_id ) {

    global $wpdb;
    $table = $wpdb->prefix . 'event_db';

    $wpdb->insert(
        $table,
        [
            'userid'        => $user_id,
            'ip_address'    => $_SERVER['REMOTE_ADDR'] ?? '',
            'event_time'    => date("Y/m/d"),
            'object_type'   => 'User',
            'event_type'    => 'created',
            'warning_level' => 'low',
            'message'       => 'Login successful',
        ]
    );
}


add_action( 'wp_login_failed', 'sdw_handle_failed_login2' );
function sdw_handle_failed_login2( $username ) {
      global $wpdb;
    $table = $wpdb->prefix . 'event_db';
    $wpdb->insert(
            $table,
            [
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'userid'     => get_current_user_id(),
                'event_time' => date("Y/m/d"),
                'object_type' => 'User',
                'warning_level' => 'high' ,
                'event_type' => 'created',
                'message'    => 'User login attempt failed',
            ]
        );

     return;
}