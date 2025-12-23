<?php

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/template.php';
    require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
    require_once ABSPATH . 'wp-admin/includes/screen.php';
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class BXFT_Table extends WP_List_Table
{

    public function get_sortable_columns()
    {
        return array(
            'event_time' => array('event_time', true),
            'warning_level' => array('warning_level', false),
            'event_type' => array('event_type', false),
            'object_type' => array('object_type', false),
        );
    }

    public function get_total_items()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'event_db';

        if (!empty($_GET['s'])) {
            $like = '%' . $wpdb->esc_like($_GET['s']) . '%';
            return (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE ip_address LIKE %s OR event_type LIKE %s OR object_type LIKE %s OR message LIKE %s",
                $like,
                $like,
                $like,
                $like
            ));
        }
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
    }


    public function prepare_items()
    {
        $per_page = 5;
        $current_page = $this->get_pagenum();

        $data = $this->wp_list_table_data($per_page, $current_page);
        $total_items = $this->get_total_items();

        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page' => $per_page,
        ));

        $this->items = $data;

        $columns = $this->get_columns();
        $hidden = $this->get_hidden_columns();
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = array($columns, $hidden, $sortable);
    }

    public function wp_list_table_data($per_page, $page_number)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'event_db';

        $orderby = !empty($_GET['orderby']) ? esc_sql($_GET['orderby']) : 'id';
        $order = !empty($_GET['order']) && $_GET['order'] === 'asc' ? 'ASC' : 'DESC';

        $search = '';
        if (!empty($_GET['s'])) {
            $like = '%' . $wpdb->esc_like($_GET['s']) . '%';
            $search = $wpdb->prepare(
                " WHERE ip_address LIKE %s
                OR event_type LIKE %s
                OR object_type LIKE %s
                OR message LIKE %s",
                $like,
                $like,
                $like,
                $like
            );
        }

        $offset = ($page_number - 1) * $per_page;
        $sql = "SELECT * FROM $table $search ORDER BY $orderby $order LIMIT %d OFFSET %d";

        return $wpdb->get_results(
            $wpdb->prepare($sql, $per_page, $offset),
            ARRAY_A
        );
    }


    public function get_hidden_columns()
    {
        return array('id');
    }

    public function get_columns()
    {
        return array(
            'userid' => 'User',
            'ip_address' => 'IP Address',
            'event_time' => 'Date',
            'warning_level' => 'Warning Level',
            'event_type' => 'Event Type',
            'object_type' => 'Object Type',
            'message' => 'Message',
        );
    }

    public function column_userid($item)
    {
        if (empty($item['userid'])) {
            return '<em>Guest</em>';
        }

        $user_id = absint($item['userid']);
        $user = get_userdata($user_id);

        if (!$user) {
            return '<em>User Deleted</em>';
        }

        $roles = !empty($user->roles)
            ? implode(', ', array_map('ucfirst', $user->roles))
            : '—';

        return sprintf(
            '<span class="firstSpan">%s
            <span class="secondSpan">
                <b>Username:</b> %s<br>
                <b>Email:</b> %s<br>
                <b>Nickname:</b> %s
            </span>
        </span>',
            esc_html($roles),
            esc_html($user->user_login),
            esc_html($user->user_email),
            esc_html($user->display_name)
        );
    }


    public function column_default($item, $column_name)
    {
         if ( $column_name === 'message' ) {

        $full_message = $item['message'];

        $short = mb_substr( $full_message, 0, 20 );

        if ( mb_strlen( $full_message ) <= 20 ) {
            return $short;
        }

        return sprintf(
            '<span class="bxft-short">%s...</span>
             <span class="bxft-full" style="display:none;">%s</span>
             <a href="#" class="bxft-read-more">Read more</a>',
            $short,
            $full_message
        );
        }

        return $item[ $column_name ] ?? '—';
    }

}

function display_bxft_table()
{
    $bxft_table = new BXFT_Table();
    $bxft_table->prepare_items();
    ?>
    <style>
        .firstSpan {
            color: rgb(119, 162, 241)
        }

        .firstSpan .secondSpan {
            visibility: hidden;
            width: 500px;
            background-color: gray;
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 5px 0;
            position: absolute;
            z-index: 1;
        }

        .firstSpan:hover .secondSpan {
            visibility: visible;
        }
    </style>
    <div class="wrap">
        <h1 class="wp-heading-inline">Logs Dashboard</h1>
        <form method="get">
            <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>" />
            <?php
            $bxft_table->search_box('Search Logs', 'bxft-search');
            $bxft_table->display();
            ?>
        </form>
    </div>
    <script>
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('bxft-read-more')) {
            e.preventDefault();

            const link = e.target;
            const shortText = link.previousElementSibling.previousElementSibling;
            const fullText  = link.previousElementSibling;

            if (fullText.style.display === 'none') {
                shortText.style.display = 'none';
                fullText.style.display  = 'inline';
                link.textContent = 'Read less';
            } else {
                shortText.style.display = 'inline';
                fullText.style.display  = 'none';
                link.textContent = 'Read more';
            }
        }
    });
    </script>
    <?php
}

display_bxft_table();