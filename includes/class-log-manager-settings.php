<?php
/**
 * Log Manager Settings - Simple version
 */
class Log_Manager_Settings {
    
    /**
     * Settings option name
     */
    const OPTION_NAME = 'log_manager_settings';
    
    /**
     * Default settings
     */
    private static $defaults = [
        'log_destination' => 'database', // database or textfile
        'textfile_path' => '', // Path for text file logging
    ];
    
    /**
     * Initialize settings
     */
    public static function init() {
        add_action('admin_init', [__CLASS__, 'register_settings']);
    }
    
    /**
     * Register settings
     */
    public static function register_settings() {
        register_setting(
            'log_manager_settings_group',
            self::OPTION_NAME,
            [__CLASS__, 'sanitize_settings']
        );
        
        // Main settings section
        add_settings_section(
            'log_manager_main_section',
            __('Logging Configuration', 'log-manager'),
            '__return_empty_string',
            'log-manager-settings'
        );
        
        // Log destination field
        add_settings_field(
            'log_destination',
            __('Log Destination', 'log-manager'),
            [__CLASS__, 'render_log_destination_field'],
            'log-manager-settings',
            'log_manager_main_section'
        );
        
        // Text file path field
        add_settings_field(
            'textfile_path',
            __('Text File Path', 'log-manager'),
            [__CLASS__, 'render_textfile_path_field'],
            'log-manager-settings',
            'log_manager_main_section'
        );
    }
    
    /**
     * Sanitize settings - SIMPLE
     */
    public static function sanitize_settings($input) {
        $sanitized = [];
        
        // Log destination
        $sanitized['log_destination'] = in_array($input['log_destination'], ['database', 'textfile']) 
            ? $input['log_destination'] 
            : 'database';
        
        // Text file path - only sanitize, don't validate
        $sanitized['textfile_path'] = sanitize_text_field($input['textfile_path']);
        
        return $sanitized;
    }
    
    /**
     * Render log destination field - SIMPLE
     */
    public static function render_log_destination_field() {
        $settings = get_option(self::OPTION_NAME, self::$defaults);
        $current = $settings['log_destination'] ?? 'database';
        
        ?>
        <label style="display: block; margin-bottom: 10px;">
            <input type="radio" name="log_manager_settings[log_destination]" value="database" 
                   <?php checked($current, 'database'); ?>>
            <?php _e('Database', 'log-manager'); ?>
        </label>
        
        <label>
            <input type="radio" name="log_manager_settings[log_destination]" value="textfile" 
                   <?php checked($current, 'textfile'); ?>>
            <?php _e('Text File', 'log-manager'); ?>
        </label>
        
        <script>
        jQuery(document).ready(function($) {
            function toggleTextFilePath() {
                if ($('input[name="log_manager_settings[log_destination]"][value="textfile"]').is(':checked')) {
                    $('#textfile-path-field').show();
                } else {
                    $('#textfile-path-field').hide();
                }
            }
            
            $('input[name="log_manager_settings[log_destination]"]').change(toggleTextFilePath);
            toggleTextFilePath();
        });
        </script>
        <?php
    }
    
    /**
     * Render text file path field - SIMPLE
     */
    public static function render_textfile_path_field() {
        $settings = get_option(self::OPTION_NAME, self::$defaults);
        $current = $settings['textfile_path'] ?? '';
        
        ?>
        <div id="textfile-path-field" style="<?php echo ($settings['log_destination'] !== 'textfile') ? 'display: none;' : ''; ?>">
            <input type="text" name="log_manager_settings[textfile_path]" 
                   value="<?php echo esc_attr($current); ?>" 
                   class="regular-text" 
                   placeholder="<?php esc_attr_e('/full/path/to/folder', 'log-manager'); ?>">
            <p class="description">
                <?php _e('Enter folder path where log file will be created. Example:', 'log-manager'); ?><br>
                <code>/home/username/public_html/logs/</code><br>
                <code>C:\xampp\htdocs\mysite\logs\</code>
            </p>
        </div>
        <?php
    }
    
    /**
     * Render settings page - SIMPLE
     */
    public static function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Log Manager Settings', 'log-manager'); ?></h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('log_manager_settings_group');
                do_settings_sections('log-manager-settings');
                submit_button();
                ?>
            </form>
            
            <div style="margin-top: 30px; padding: 15px; background: #f6f7f7; border: 1px solid #ccd0d4;">
                <h3><?php _e('How it works:', 'log-manager'); ?></h3>
                <ul style="list-style: disc; margin-left: 20px;">
                    <li><strong>Database:</strong> Logs stored in WordPress database table</li>
                    <li><strong>Text File:</strong> New logs will be saved to <code>YYYY-MM-DD-logs.txt</code> in specified folder</li>
                    <li>Old logs remain in their original location when switching</li>
                    <li>Only new logs go to selected destination</li>
                </ul>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get settings
     */
    public static function get_settings() {
        $settings = get_option(self::OPTION_NAME, self::$defaults);
        return wp_parse_args($settings, self::$defaults);
    }
    
    /**
     * Get log destination
     */
    public static function get_log_destination() {
        $settings = self::get_settings();
        return $settings['log_destination'];
    }
    
    /**
     * Get text file path
     */
    public static function get_textfile_path() {
        $settings = self::get_settings();
        return $settings['textfile_path'];
    }
}