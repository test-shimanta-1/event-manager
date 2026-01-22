<?php
/**
 * Post/Post-Type and Taxonomy event hooks class file
 * 
 * @since 1.0.5
 * @package Log_Manager
 */
class Log_Manager_Post_Hooks
{
    /**
     * Store term data before update / delete
     */
    protected static $before_term_update = [];

    /**
     * Stored featured image data
     */
    protected static $featured_image_logged = [];

    /**
     * Store old thumbnail before save
     */
    protected static $old_thumbnails = [];

    /**
     * before post meta
     */
    protected static $before_post_meta = [];

    /**
     * Register post-related hooks for logging post lifecycle events.
     */
    public function __construct()
    {
        // Post Type lifecycle hooks
        add_action('transition_post_status', [$this, 'sdw_post_changes_logs'], 10, 3);
        add_action('before_delete_post', [$this, 'sdw_post_delete_log'], 10, 1);
        add_action('post_updated', [$this, 'sdw_post_updated_log'], 10, 3);

        // Taxonomy lifecycle hooks
        add_action('created_term', [$this, 'sdw_taxonomy_create_log'], 10, 3);
        add_action('edit_term', [$this, 'sdw_capture_term_before_update'], 10, 3);
        add_action('edited_term', [$this, 'sdw_taxonomy_update_log'], 10, 3);
        add_action('pre_delete_term', [$this, 'sdw_capture_term_before_delete'], 10, 2);
        add_action('delete_term', [$this, 'sdw_taxonomy_delete_log'], 10, 4);
        add_action('set_object_terms', [$this, 'sdw_taxonomy_assignment_log'], 10, 6);
        add_action('acf/save_post', [$this, 'sdw_capture_taxonomy_acf_before_save'], 5);
        add_action('acf/save_post', [$this, 'sdw_taxonomy_acf_after_log'], 20);

        // Featured image hooks
        add_action('pre_post_update', [$this, 'sdw_capture_old_featured_image'], 10, 2);
        add_action('save_post', [$this, 'sdw_handle_featured_image_change'], 20, 3);

        // featured media
        add_action('updated_post_meta', [$this, 'sdw_featured_image_meta_log'], 10, 4);
        add_action('added_post_meta', [$this, 'sdw_featured_image_meta_log'], 10, 4);
        add_action('deleted_post_meta', [$this, 'sdw_featured_image_meta_delete_log'], 10, 4);

    }

    /**
     * post meta data before update
     * 
     * @since 1.0.5
     */
    public function sdw_capture_post_meta_before($meta_id, $post_id, $meta_key, $meta_value)
    {
        self::$before_post_meta[$meta_id] = $meta_value;
    }

    /**
     * Capture old thumbnail before post update
     * 
     * @since 1.0.5
     */
    public function sdw_capture_old_featured_image($post_id, $data) {
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }
        self::$old_thumbnails[$post_id] = get_post_thumbnail_id($post_id);
    }

    /**
     * Handle featured image add / update / remove
     * 
     * @since 1.0.5
     */
    public function sdw_handle_featured_image_change($post_id, $post, $update)
    {
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        if (!$post || $post->post_type === 'revision') {
            return;
        }

        // Prevent duplicate logs per request
        if (isset(self::$featured_image_logged[$post_id])) {
            return;
        }
        self::$featured_image_logged[$post_id] = true;

        $new_thumbnail = get_post_thumbnail_id($post_id);
        $old_thumbnail = self::$old_thumbnails[$post_id] ?? 0;

        // No change
        if ((int) $old_thumbnail === (int) $new_thumbnail) {
            unset(self::$old_thumbnails[$post_id]);
            return;
        }

        // Removed
        if ($old_thumbnail && !$new_thumbnail) {
            $this->log_featured_image_removed($post);
        }

        // Added
        if (!$old_thumbnail && $new_thumbnail) {
            $this->log_featured_image_event($post, $new_thumbnail, 'assigned');
        }

        // Changed
        if ($old_thumbnail && $new_thumbnail && $old_thumbnail !== $new_thumbnail) {
            $this->log_featured_image_event($post, $new_thumbnail, 'modified');
        }

        // Cleanup
        unset(self::$old_thumbnails[$post_id]);
    }

    /**
     * Log featured image added / changed
     * 
     * @since 1.0.5
     */
    private function log_featured_image_event($post, $attachment_id, $event_type)
    {
        $media_url = wp_get_attachment_url($attachment_id);
        if (!$media_url) return;

        Log_Manager_Logger::insert([
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'userid'     => get_current_user_id(),
            'event_time' => current_time('mysql'),
            'object_type'=> 'Media',
            'severity'   => 'notice',
            'event_type' => $event_type,
            'message'    =>
                'Media ID <b>' . absint($attachment_id) . '</b> ' . esc_html($event_type) . ' as featured image.' .
                '<br/>Post ID: <b>' . absint($post->ID) . '</b>' .
                '<br/>Post Title: <b>' . esc_html($post->post_title) . '</b>' .
                '<br/>Media URL: <a href="' . esc_url($media_url) . '" target="_blank">View Media</a>',
        ]);
    }

    /**
     * Log featured image removed
     * 
     * @since 1.0.5
     */
    private function log_featured_image_removed($post)
    {
        Log_Manager_Logger::insert([
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'userid'     => get_current_user_id(),
            'event_time' => current_time('mysql'),
            'object_type'=> 'Media',
            'severity'   => 'notice',
            'event_type' => 'deleted',
            'message'    =>
                'Featured image removed from post.' .
                '<br/>Post ID: <b>' . absint($post->ID) . '</b>' .
                '<br/>Post Title: <b>' . esc_html($post->post_title) . '</b>' .
                '<br/>Edit Post: <a href="' . esc_url(get_edit_post_link($post->ID)) . '" target="_blank">Edit</a>',
        ]);
    }

    /**
     * Post status and content logging
     * 
     * @since 1.0.5
     */
    public function sdw_post_changes_logs($new_status, $old_status, $post)
    {
        if (wp_is_post_autosave($post->ID) || wp_is_post_revision($post->ID)) {
            return;
        }

        if (empty($post) || empty($post->ID)) return;

        $post_id = $post->ID;
        if ($post->post_type === 'revision' && !empty($post->post_parent)) {
            $post_id = $post->post_parent;
        }

        // ACF Field / Field Group logging
        if (in_array($post->post_type, ['acf-field', 'acf-field-group'], true)) {

            $type_label = ($post->post_type === 'acf-field')
                ? 'ACF Field'
                : 'ACF Field Group';

            Log_Manager_Logger::insert([
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'userid'     => get_current_user_id(),
                'event_time' => current_time('mysql'),
                'object_type'=> 'Settings',
                'severity'   => 'notice',
                'event_type' => 'modified',
                'message'    =>
                    $type_label . ' updated.' .
                    '<br/>Title: <b>' . esc_html($post->post_title) . '</b>' .
                    '<br/>ID: <b>' . absint($post->ID) . '</b>' .
                    '<br/><a href="' . esc_url(get_edit_post_link($post)) . '" target="_blank">Edit</a>',
            ]);

            return;
        }


        $user_id = get_current_user_id();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $time = current_time('mysql');
        $title = get_the_title($post->ID);
        $type = get_post_type($post->ID);

        $event_type = '';
        $severity = 'notice';
        $message = '';

        if ($old_status === 'trash' && $new_status !== 'trash') {
            $event_type = 'restored';
            $message = 'Post has been restored.';
        } elseif ($new_status === 'trash') {
            $event_type = 'trashed';
            $message = 'Post has been moved to trash.';
        } elseif ($old_status === 'auto-draft' && $new_status !== 'auto-draft') {
            $event_type = 'created';
            $message = 'New post has been created.';
        } elseif ($old_status !== $new_status) {
            $event_type = 'modified';
            if ($new_status === 'publish' && $old_status !== 'publish') {
                $message = 'Post has been published.';
            } elseif ($new_status === 'private') {
                $message = 'Post has been set to private.';
            } else {
                $message = sprintf('Post status changed from %s to %s.', esc_html($old_status), esc_html($new_status));
            }
        } else {
            $event_type = 'modified';
            $message = 'Post content has been updated.';
        }

        $edit_post_url = get_edit_post_link($post);

        $message .=
            '<br/>Post Title: <b>' . esc_html($title) . '</b>' .
            '<br/>Post ID: <b>' . absint($post->ID) . '</b>' .
            '<br/>Post Type: <b>' . esc_html($type) . '</b>' .
            '<br/>View post: <b><a href="' . esc_url($edit_post_url) . '" target="_blank">view post in editor</a></b>';

        Log_Manager_Logger::insert([
            'ip_address' => $ip,
            'userid'     => $user_id,
            'event_time' => $time,
            'object_type'=> 'Post',
            'severity'    => $severity,
            'event_type' => $event_type,
            'message'    => $message,
        ]);
    }

    /**
     * Logs permanent deletion of a post.
     * 
     * @since 1.0.5
     */
    function sdw_post_delete_log($post_id)
    {
        if (wp_is_post_revision($post_id)) return;

        $post = get_post($post_id);
        if (!$post) return;

        Log_Manager_Logger::insert([
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'userid'     => get_current_user_id(),
            'event_time' => current_time('mysql'),
            'object_type'=> 'Post',
            'severity'   => 'notice',
            'event_type' => 'deleted',
            'message'    =>
                'Permanently deleted the post.' .
                '<br/>Post Title: <b>' . get_the_title($post->ID) . '</b>' .
                '<br/>Post ID: <b>' . $post->ID . '</b>' .
                '<br/>Post Type: <b>' . get_post_type($post->ID) . '</b>',
        ]);
    }

    /**
     * Logs taxonomy term creation
     * 
     * @since 1.0.5
     */
    public function sdw_taxonomy_create_log($term_id, $tt_id, $taxonomy)
    {
        $term = get_term($term_id, $taxonomy);
        if (!$term || is_wp_error($term)) return;

        $term_link = get_edit_term_link($term_id, $taxonomy);

        Log_Manager_Logger::insert([
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'userid'     => get_current_user_id(),
            'event_time' => current_time('mysql'),
            'object_type'=> 'Taxonomy',
            'severity'   => 'notice',
            'event_type' => 'created',
            'message'    =>
                'Taxonomy term created.' .
                '<br/>Name: <b><a href="' . esc_url($term_link) . '" target="_blank">' . esc_html($term->name) . '</a></b>' .
                '<br/>ID: <b>' . $term_id . '</b>' .
                '<br/>Taxonomy: <b>' . esc_html($taxonomy) . '</b>',
        ]);
    }

    /**
     * Capture term before update
     * 
     */
    public function sdw_capture_term_before_update($term_id, $tt_id, $taxonomy)
    {
        $term = get_term($term_id, $taxonomy);
        if (!$term || is_wp_error($term)) return;

        self::$before_term_update[$term_id] = [
            'name' => $term->name,
            'slug' => $term->slug,
            'parent' => (int) $term->parent,
            'description' => $term->description,
        ];
    }

    /**
     * Logs taxonomy updates safely
     */
    public function sdw_taxonomy_update_log($term_id, $tt_id, $taxonomy)
    {
        if (!isset(self::$before_term_update[$term_id])) return;

        $before = self::$before_term_update[$term_id];
        $after = get_term($term_id, $taxonomy);
        if (!$after || is_wp_error($after)) return;

        $term_link = get_edit_term_link($term_id, $taxonomy);
        $changes = [];

        // Parent change
        if ($before['parent'] !== (int)$after->parent && $after->parent) {
            $parent = get_term($after->parent, $taxonomy);
            if ($parent && !is_wp_error($parent)) {
                $parent_link = get_edit_term_link($parent->term_id, $taxonomy);
                $changes[] =
                    'Category <b><a href="' . esc_url($term_link) . '" target="_blank">' . esc_html($after->name) . '</a></b> (ID ' . $term_id .
                    ') assigned as child of <b><a href="' . esc_url($parent_link) . '" target="_blank">' . esc_html($parent->name) . '</a></b> (ID ' . $parent->term_id . ')';
            }
        }

        // Name change
        if ($before['name'] !== $after->name) {
            $changes[] =
                'Taxonomy name changed from <b>' . esc_html($before['name']) . '</b> to <b><a href="' . esc_url($term_link) . '" target="_blank">' . esc_html($after->name) . '</a></b> (ID ' . $term_id . ')';
        }

        // Slug change
        if ($before['slug'] !== $after->slug) {
            $changes[] =
                'Slug changed for <b><a href="' . esc_url($term_link) . '" target="_blank">' . esc_html($after->name) . '</a></b> (ID ' . $term_id . '): <b>' . esc_html($before['slug']) . '</b> → <b>' . esc_html($after->slug) . '</b>';
        }

        // Description change
        if ($before['description'] !== $after->description) {
            $old_desc = trim($before['description']) === '' ? '""' : esc_html($before['description']);
            $new_desc = trim($after->description) === '' ? '""' : esc_html($after->description);
            $changes[] =
                'Description updated for <b><a href="' . esc_url($term_link) . '" target="_blank">' . esc_html($after->name) . '</a></b> (ID ' . $term_id . ')<br/>' .
                '<b>Old:</b> ' . $old_desc . '<br/><b>New:</b> ' . $new_desc;
        }

        if (!empty($changes)) {
            Log_Manager_Logger::insert([
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'userid' => get_current_user_id(),
                'event_time' => current_time('mysql'),
                'object_type' => 'Taxonomy',
                'severity' => 'notice',
                'event_type' => 'modified',
                'message' => implode('<br/>', $changes),
            ]);
        }

        unset(self::$before_term_update[$term_id]);
    }

    /**
     * Stores taxonomy term data before deletion.
     *
     * @param int    $term_id  Term ID.
     * @param string $taxonomy Taxonomy slug.
     * 
     * @since 1.0.1
     * @return void
     */
    public function sdw_capture_term_before_delete($term_id, $taxonomy)
    {
        self::$before_term_update['delete_' . $term_id] = get_term($term_id, $taxonomy);
    }

    /**
     * Logs deletion of a taxonomy term.
     *
     * @param int    $term_id      Term ID.
     * @param int    $tt_id        Term taxonomy ID.
     * @param string $taxonomy     Taxonomy slug.
     * @param object $deleted_term Deleted term object.
     * 
     * @since 1.0.1
     * @return void
     */
    public function sdw_taxonomy_delete_log($term_id, $tt_id, $taxonomy, $deleted_term)
    {
        $term = self::$before_term_update['delete_' . $term_id] ?? $deleted_term;
        if (!$term)
            return;

        Log_Manager_Logger::insert([
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'userid' => get_current_user_id(),
            'event_time' => current_time('mysql'),
            'object_type' => 'Taxonomy',
            'severity' => 'warning',
            'event_type' => 'deleted',
            'message' =>
                'Taxonomy term deleted.<br/>Name: <b>' . esc_html($term->name) .
                '</b><br/>ID: <b>' . $term_id . '</b>',
        ]);

        unset(self::$before_term_update['delete_' . $term_id]);
    }

    /**
     * Logs taxonomy term assignment or removal on an object.
     *
     * @param int    $object_id  Object (post) ID.
     * @param array  $terms      Assigned terms.
     * @param array  $tt_ids     New term taxonomy IDs.
     * @param string $taxonomy   Taxonomy slug.
     * @param bool   $append     Append mode flag.
     * @param array  $old_tt_ids Old term taxonomy IDs.
     * 
     * @since 1.0.1
     * @return void
     */
    public function sdw_taxonomy_assignment_log($object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids)
    {
        $post = get_post($object_id);
        if (!$post) {
            return;
        }

        // Skip autosave & revisions
        if (wp_is_post_autosave($object_id) || wp_is_post_revision($object_id)) {
            return;
        }

        /**
         * Convert term_taxonomy_ids → term_ids
         */
        global $wpdb;

        $get_term_ids = function ($tt_ids) use ($wpdb) {
            if (empty($tt_ids)) {
                return [];
            }

            $placeholders = implode(',', array_fill(0, count($tt_ids), '%d'));

            return $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT term_id FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id IN ($placeholders)",
                    $tt_ids
                )
            );
        };

        $new_term_ids = $get_term_ids((array) $tt_ids);
        $old_term_ids = $get_term_ids((array) $old_tt_ids);

        $added_ids   = array_diff($new_term_ids, $old_term_ids);
        $removed_ids = array_diff($old_term_ids, $new_term_ids);

        if (empty($added_ids) && empty($removed_ids)) {
            return;
        }

        $messages = [];

        if (!empty($added_ids)) {
            $added_terms = get_terms([
                'taxonomy'   => $taxonomy,
                'include'    => $added_ids,
                'hide_empty' => false,
            ]);

            if (!is_wp_error($added_terms)) {
                $names = wp_list_pluck($added_terms, 'name');
                $messages[] =
                    '<b>' . esc_html(implode(', ', $names)) . '</b> added to the post.';
            }
        }

        if (!empty($removed_ids)) {
            $removed_terms = get_terms([
                'taxonomy'   => $taxonomy,
                'include'    => $removed_ids,
                'hide_empty' => false,
            ]);

            if (!is_wp_error($removed_terms)) {
                $names = wp_list_pluck($removed_terms, 'name');
                $messages[] =
                    '<b>' . esc_html(implode(', ', $names)) . '</b> removed from the post.';
            }
        }

        $messages[] =
            'Taxonomy: <b>' . esc_html($taxonomy) . '</b>' .
            '<br/>Post Title: <b>' . esc_html($post->post_title) . '</b>' .
            '<br/>Post ID: <b>' . absint($object_id) . '</b>' .
            '<br/><a href="' . esc_url(get_edit_post_link($post)) . '" target="_blank">View post in editor</a>';

        Log_Manager_Logger::insert([
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'userid'     => get_current_user_id(),
            'event_time' => current_time('mysql'),
            'object_type'=> 'Taxonomy',
            'severity'   => 'notice',
            'event_type' => 'assigned',
            'message'    => implode('<br/>', $messages),
        ]);
    }



    /**
     * Stores taxonomy ACF field values before saving.
     *
     * @param string $post_id ACF post ID (term_x).
     * 
     * @since 1.0.1
     * @return void
     */
    public function sdw_capture_taxonomy_acf_before_save($post_id)
    {
        if (strpos($post_id, 'term_') !== 0)
            return;

        $term_id = (int) str_replace('term_', '', $post_id);

        $fields = get_fields($post_id);
        if (!$fields) {
            self::$before_term_update['acf_' . $term_id] = [];
            return;
        }

        self::$before_term_update['acf_' . $term_id] = $fields;
    }

    /**
     * Logs changes in taxonomy ACF fields after save.
     *
     * @param string $post_id ACF post ID (term_x).
     * 
     * @since 1.0.1
     * @return void
     */
    public function sdw_taxonomy_acf_after_log($post_id)
    {
        if (strpos($post_id, 'term_') !== 0) {
            return;
        }

        $term_id = (int) str_replace('term_', '', $post_id);
        $term = get_term($term_id);

        if (!$term || is_wp_error($term)) {
            return;
        }

        $term_link = get_edit_term_link($term_id, $term->taxonomy);
        $new_fields = get_fields($post_id) ?: [];
        $old_fields = self::$before_term_update['acf_' . $term_id] ?? [];

        if (!$new_fields) {
            return;
        }

        $changes = [];
        foreach ($new_fields as $field_key => $new_value) {
            $old_value = $old_fields[$field_key] ?? null;
            if ($old_value == $new_value) {
                continue;
            }

            $field_object = get_field_object($field_key, $post_id);
            $label = $field_object['label'] ?? $field_key;
            $old = ($old_value === null || $old_value === '') ? '""' : esc_html(print_r($old_value, true));
            $new = ($new_value === null || $new_value === '') ? '""' : esc_html(print_r($new_value, true));

            $changes[] =
                'Field <b>' . esc_html($label) . '</b> updated for taxonomy ' .
                '<b><a href="' . esc_url($term_link) . '" target="_blank">' .
                esc_html($term->name) . '</a></b> (ID ' . $term_id . ')<br/>' .
                '<b>Old:</b> ' . $old . '<br/>' .
                '<b>New:</b> ' . $new;
        }

        if (!$changes) {
            return;
        }

        Log_Manager_Logger::insert([
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'userid' => get_current_user_id(),
            'event_time' => current_time('mysql'),
            'object_type' => 'Taxonomy',
            'severity' => 'notice',
            'event_type' => 'modified',
            'message' => implode('<br/><br/>', $changes),
        ]);
    }

    /**
     * post update log
     * 
     * @since 1.0.5
     */
    public function sdw_post_updated_log($post_id, $post_after, $post_before)
    {
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        if ($post_after->post_type === 'revision') {
            return;
        }

        $changes = [];

        if ($post_before->post_title !== $post_after->post_title) {
            $changes[] = 'Title changed from <b>' .
                esc_html($post_before->post_title) .
                '</b> to <b>' .
                esc_html($post_after->post_title) .
                '</b>';
        }

        if ($post_before->post_excerpt !== $post_after->post_excerpt) {
            $changes[] = 'Excerpt updated.';
        }

        if ($post_before->post_name !== $post_after->post_name) {
            $changes[] = 'Slug changed from <b>' .
                esc_html($post_before->post_name) .
                '</b> to <b>' .
                esc_html($post_after->post_name) .
                '</b>';
        }

        if (!$changes) {
            return;
        }

        Log_Manager_Logger::insert([
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'userid'     => get_current_user_id(),
            'event_time' => current_time('mysql'),
            'object_type'=> 'Post',
            'severity'   => 'notice',
            'event_type' => 'modified',
            'message'    =>
                implode('<br/>', $changes) .
                '<br/>Post ID: <b>' . $post_id . '</b>' .
                '<br/>Post Title: <b>' . esc_html($post_after->post_title) . '</b>',
        ]);
    }

    /**
     * featurted media
     */
    public function sdw_featured_image_meta_log($meta_id, $post_id, $meta_key, $meta_value)
{
    if ($meta_key !== '_thumbnail_id') {
        return;
    }

    $post = get_post($post_id);
    if (!$post || $post->post_type === 'revision') {
        return;
    }

    Log_Manager_Logger::insert([
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
        'userid'     => get_current_user_id(),
        'event_time' => current_time('mysql'),
        'object_type'=> 'Media',
        'severity'   => 'notice',
        'event_type' => 'assigned',
        'message'    =>
            'Featured image assigned.' .
            '<br/>Post: <b>' . esc_html($post->post_title) . '</b>' .
            '<br/>Post ID: <b>' . $post_id . '</b>' .
            '<br/>Media ID: <b>' . absint($meta_value) . '</b>',
    ]);
}

public function sdw_featured_image_meta_delete_log($meta_id, $post_id, $meta_key, $_meta_value)
{
    if ($meta_key !== '_thumbnail_id') {
        return;
    }

    $post = get_post($post_id);
    if (!$post) {
        return;
    }

    Log_Manager_Logger::insert([
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
        'userid'     => get_current_user_id(),
        'event_time' => current_time('mysql'),
        'object_type'=> 'Media',
        'severity'   => 'notice',
        'event_type' => 'deleted',
        'message'    =>
            'Featured image removed.' .
            '<br/>Post: <b>' . esc_html($post->post_title) . '</b>' .
            '<br/>Post ID: <b>' . $post_id . '</b>',
    ]);
}



}