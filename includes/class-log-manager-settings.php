<?php
/**
 * Plugin Settings Class File
 * 
 * 
 * @since 1.0.2
 * @package Log_Manager
 */
class Log_Manager_Settings
{
    /**
     * Constructor.
     *
     * @since 1.0.2
     *
     * @return void
     */
    public function __construct() {
       add_action('admin_init', [$this, 'sdw_register_settings_callback']);
    }

     /**
     * Register Log Manager plugin settings sub-menus
     *
     * @since 1.0.2
     *
     * @return void
     */
    public function sdw_register_settings_callback(){
         register_setting(
        'log_manager_settings', 
        'log_manager_storage_type',
        [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'database',
        ]
    );

    register_setting(
        'log_manager_settings',
        'log_manager_file_path',
        [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ]
    );
    }

    /**
     * Render Log Manager settings page.
     *
     * @since 1.0.2
     *
     * @return void
     */
    public static function sdw_log_manager_settings_render(){
        $storage = get_option('log_manager_storage_type', 'database');
        $file_path = get_option('log_manager_file_path', '');
    ?>
    <div class="wrap">
        <h1>Log Manager Settings</h1>

        <form method="post" action="options.php">
            <?php settings_fields('log_manager_settings'); ?>

            <table class="form-table">
                <tr>
                    <th>Store Logs In</th>
                    <td>
                        <label>
                            <input type="radio" name="log_manager_storage_type" value="database" <?php checked($storage, 'database'); ?>>
                            Database
                        </label><br>

                        <label>
                            <input type="radio" name="log_manager_storage_type" value="file"
                                <?php checked($storage, 'file'); ?>>
                            Text File
                        </label>
                    </td>
                </tr>

                <tr id="log-file-path-row">
                    <th>Text File Path</th>
                    <td>
                        <input type="text" name="log_manager_file_path" value="<?php echo esc_attr($file_path); ?>" class="regular-text" placeholder="Enter your folder path...">
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>
    </div>

    <script>
        (function () {
            const radios = document.querySelectorAll('input[name="log_manager_storage_type"]');
            const pathRow = document.getElementById('log-file-path-row');

            function togglePath() {
                const selected = document.querySelector('input[name="log_manager_storage_type"]:checked').value;
                pathRow.style.display = selected === 'file' ? 'table-row' : 'none';
            }

            radios.forEach(r => r.addEventListener('change', togglePath));
            togglePath(); // on page load
        })();
    </script>
    <?php
    }

}