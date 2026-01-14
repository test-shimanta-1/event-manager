<?php

/**
 * Log Manager Logger class file
 *
 * Handles logging storage logic by routing log data
 * either to the database or to a log file based on
 * plugin settings.
 *
 * @since 1.0.2
 * @package Log_Manager
 */
class Log_Manager_Logger
{
    /**
     * Insert log data into the selected storage.
     *
     * @since 1.0.2
     * @param array $data Log data to be stored.
     *
     * @return void
     */
    public static function insert($data)
    {
        $storage = get_option('log_manager_storage_type', 'database');
        if ($storage === 'file') {
            self::sdw_write_to_file($data);
        } else {
            self::sdw_write_to_db($data);
        }
    }

    /**
     * Write log data to the database.
     *
     * @since 1.0.2
     * @param array $data Log data to be inserted.
     *
     * @return void
     */
    protected static function sdw_write_to_db($data)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'log_db';
        date_default_timezone_set('Asia/Kolkata');
        $wpdb->insert($table, $data);
    }

    /**
     * Write log data to a log file.
     *
     * @since 1.0.2
     * @param array $data Log data to be written to file.
     *
     * @return void
     */
    protected static function sdw_write_to_file($data)
    {
        $dir = get_option('log_manager_file_path');
        if (empty($dir)) {
            return;
        }

        $dir = rtrim($dir, '/\\');

        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }

        if (!is_writable($dir)) {
            return;
        }

        $file = $dir . DIRECTORY_SEPARATOR . 'log-manager.txt';
        $is_new = !file_exists($file);

        $handle = fopen($file, 'a');
        if (!$handle) {
            return;
        }

        if ($is_new) {
            fwrite($handle, str_repeat('-', 88) . PHP_EOL);
            fwrite(
                $handle,
                sprintf(
                    "%-20s | %-7s | %-10s | %s\n",
                    'Date & Time',
                    'User ID',
                    'Event Type',
                    'Message'
                )
            );
            fwrite($handle, str_repeat('-', 88) . PHP_EOL);
        }

        // default timezone is set to IST
        date_default_timezone_set('Asia/Kolkata');

        fwrite(
            $handle,
            sprintf(
                "%-20s | %-7s | %-10s | %s\n",
                current_time('mysql'),
                $data['userid'] ?? '0',
                $data['event_type'] ?? '',
                $data['message'] . "\n"
            )
        );

        fclose($handle);
    }
}