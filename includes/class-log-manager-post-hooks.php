<?php
/**
 * Post/Post-Type related event hooks class file
 * 
 * 
 * @since 1.0.1
 * @package Log_Manager
 */

class Log_Manager_Post_Hooks
{
    /**
     * Register post-related hooks for logging post lifecycle events.
     *
     * Hooks into:
     * - transition_post_status: Logs post creation, update, restore, publish, and trash events.
     * - before_delete_post: Logs permanent post deletion.
     *
     * @return void
     */
    public function __construct()
    {
        add_action('transition_post_status', [$this, 'sdw_post_changes_logs'], 10, 3);
        add_action('before_delete_post', [$this, 'sdw_post_delete_log'], 10, 1);
    }

    /**
     * Log post status transitions and content updates.
     *
     * This method captures and logs various post lifecycle events such as:
     * - Post creation (auto-draft to any valid status)
     * - Post publishing (draft to publish)
     * - Post updates (publish to publish)
     * - Post trashing
     * - Post restoration from trash
     *
     * The log entry includes user ID, IP address, post details,
     * severity level, event type, and a descriptive message.
     *
     *
     * @param string  $new_status New post status.
     * @param string  $old_status Old post status.
     * @param WP_Post $post       Post object.
     *
     * @return void
     */
    public function sdw_post_changes_logs($new_status, $old_status, $post)
    {
        // Avoid autosaves and revisions
        if (wp_is_post_autosave($post->ID) || wp_is_post_revision($post->ID)) {
            return;
        }

        // Ensure valid post object
        if (empty($post) || empty($post->ID)) {
            return;
        }

        /**
         * Resolve correct post ID
         * (In case WordPress passes a revision object)
         */
        $post_id = $post->ID;

        if ($post->post_type === 'revision' && !empty($post->post_parent)) {
            $post_id = $post->post_parent;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'event_db';

        $user_id = get_current_user_id();
        $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
        $time = date("Y/m/d");
        $title = get_the_title($post->ID);
        $type = get_post_type($post->ID);

        $event_type = '';
        $severity = 'notice';
        $message = '';

        /**
         * 1. Restore from trash
         */
        if ($old_status === 'trash' && $new_status !== 'trash') {

            $event_type = 'restored';
            $message = 'Post has been restored.';

            /**
             * 2. Move to trash
             */
        } elseif ($new_status === 'trash') {

            $event_type = 'trashed';
            $message = 'Post has been moved to trash.';

            /**
             * 3. New post created
             */
        } elseif ($old_status === 'auto-draft' && $new_status !== 'auto-draft') {

            $event_type = 'created';
            $message = 'New post has been created.';

            /**
             * 4. Any other status change (draft → publish, publish → private, etc.)
             */
        } elseif ($old_status !== $new_status) {

            $event_type = 'modified';

            if ($new_status === 'publish' && $old_status !== 'publish') {
                $message = 'Post has been published.';
            } elseif ($new_status === 'private') {
                $message = 'Post has been set to private.';
            } else {
                $message = sprintf(
                    'Post status changed from %s to %s.',
                    esc_html($old_status),
                    esc_html($new_status)
                );
            }

            /**
             * 5. Post content updated (same status)
             */
        } else {

            $event_type = 'modified';
            $message = 'Post content has been updated.';
        }

        $edit_post_url = get_edit_post_link($post);

        // Append post details
        $message .=
            '<br/>Post Title: <b>' . esc_html($title) . '</b>' .
            '<br/>Post ID: <b>' . absint($post->ID) . '</b>' .
            '<br/>Post Type: <b>' . esc_html($type) . '</b>' .
            '<br/>View post: <b><a href="' . esc_url($edit_post_url) . '" target="_blank">view post in editor</a></b>';

        // Append revisions URL if available
        if (wp_get_post_revisions($post->ID)) {
            $message .= '<br/>Post Revisions: <a href="' . esc_url(wp_get_post_revisions_url($post->ID)) . '" target="_blank">View revisions</a>';
        }

        // Insert log entry
        $wpdb->insert(
            $table,
            [
                'ip_address' => $ip,
                'userid' => $user_id,
                'event_time' => $time,
                'object_type' => 'Post',
                'severity' => $severity,
                'event_type' => $event_type,
                'message' => $message,
            ]
        );

    }


    /**
     * This method logs an event when a post is permanently deleted
     *
     * @param int $post_id ID of the post being deleted.
     *
     * @return void
     */
    function sdw_post_delete_log($post_id)
    {
        if (wp_is_post_revision($post_id)) {
            return;
        }

        $post = get_post($post_id);
        if (!$post) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'event_db';
        $wpdb->insert(
            $table,
            [
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'userid' => get_current_user_id(),
                'event_time' => date("Y/m/d"),
                'object_type' => 'Post',
                'severity' => 'notice',
                'event_type' => 'deleted',
                'message' => 'Permanently deleted the post. ' . '<br/>Post Title: <b>' . get_the_title($post->ID) . '</b><br> Post ID: <b>' . $post->ID . '</b> <br/>Post Type: <b>' . get_post_type($post->ID) . '</b>',
            ]
        );
    }

}