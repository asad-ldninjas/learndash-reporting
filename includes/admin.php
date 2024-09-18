<?php
/**
 * Myrtle Learning - Admin Hooks
 *
 */
if( ! defined( 'ABSPATH' ) ) exit;

class MPT_ADMIN_MODULE {

    private static $instance;

    /**
     * Create class instance
     */
    public static function instance() {

        if( is_null( self::$instance ) && ! ( self::$instance instanceof MPT_ADMIN_MODULE ) ) {

            self::$instance = new MPT_ADMIN_MODULE;
            self::$instance->hooks();
            self::$instance->includes();
        }

        return self::$instance;
    }

    /**
     * include files
     */
    private function includes() {
    }

    /**
     * Define hooks
     */
    private function hooks() {
        add_action( 'admin_menu', [ $this, 'mpt_add_menu_page' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'mpt_enqueue_admin_scripts' ] );
        add_action( 'admin_post_mpt_setting_action', [ $this, 'mpt_update_settings' ] );
        add_action( 'edit_user_profile', [ $this, 'mpt_add_users_dropdown' ] );
        add_action( 'show_user_profile', [ $this, 'mpt_add_users_dropdown' ] );
        add_action( 'personal_options_update', [ $this, 'mpt_update_users_field' ] );
        add_action( 'edit_user_profile_update', [ $this, 'mpt_update_users_field' ] );
        add_action( 'init', [ $this, 'mpt_override_mycred_log_file' ] );
        add_filter( 'mycred_log_column_headers', [ $this, 'mpt_mycred_log_column_headers' ], 100, 3 );
        add_action( 'wp_ajax_custom_date_range', [ $this, 'mpt_custom_date_range' ] );
        add_action( 'init', [ $this, 'mpt_download_csv' ] );
        add_action( 'admin_footer', [ $this, 'mpt_apply_css' ] );
        add_action( 'wp_ajax_update_territory_users', [ $this, 'mpt_update_territory_users' ] );
        add_action( 'wp_ajax_download_log_data', [ $this, 'mpt_update_log_data' ] );
    }   

    /**
     * apply css
     */
    public function mpt_apply_css() {

        global $wpdb;

        $log_type = isset( $_GET['show'] ) ? $_GET['show'] : '';

        if( 'daterange' == $log_type ) {

            global $wpdb;
            $get_custom_range = get_option( 'mpt_custom_date' );
            $start_date = isset( $get_custom_range['start'] ) ? $get_custom_range['start'] : '';
            $end_date = isset( $get_custom_range['end'] ) ? $get_custom_range['end'] : '';

            $query = $wpdb->prepare("
                SELECT *
                FROM {$wpdb->prefix}myCRED_log
                WHERE time BETWEEN %d AND %d
                ORDER BY ID DESC
                ", $start_date, $end_date);

            $custom_results = $wpdb->get_results($query);
            $custom_count = count( $custom_results );
            
            if( $custom_count < 10 ) {
                ?>
                <style type="text/css">
                    .pagination-links {
                        display: none !important;
                    }
                </style>
                <?php
            }
        }
    }

    /**
     * update log filter data
     */
    public function mpt_update_log_data() {

        $start_date = isset( $_POST['start_date'] ) ? $_POST['start_date'] : '';
        $end_date = isset( $_POST['end_date'] ) ? $_POST['end_date'] : wp_date( "Y-m-d H:i:s" );
        $reference = isset( $_POST['reference'] ) ? $_POST['reference'] : '';
        $order = isset( $_POST['order'] ) ? $_POST['order'] : '';
        $time_frame = isset( $_POST['show'] ) ? $_POST['show'] : '';

        if( 'today' == $time_frame ) {

            $currentDate = new DateTime();
            $previousDate = clone $currentDate;
            $start_date = $previousDate->format( 'Y-m-d' );
            $end_date = $previousDate->modify('+1 day');
            $end_date = $end_date->format( 'Y-m-d' );
        } 

        if( 'yesterday' == $time_frame ) {

            $currentDate = new DateTime();

            $previousDate = clone $currentDate;
            $start_date = $previousDate->modify('-1 day');
            $start_date = $start_date->format( 'Y-m-d' );
            $end_date = $previousDate->format( 'Y-m-d' );
        } 

        if( 'thisweek' == $time_frame ) {

            $start_date = '';
            $end_date = '';

            $currentDate = new DateTime();
            $firstDayOfWeek = clone $currentDate;
            $firstDayOfWeek->modify('this week');

            $end_date = $currentDate->format('Y-m-d');
            $start_date = $firstDayOfWeek->format('Y-m-d');
        } 

        if( 'thismonth' == $time_frame ) {

            $currentDate = new DateTime();
            $firstDateOfMonth = clone $currentDate;
            $firstDateOfMonth->modify('first day of this month');

            $end_date = $currentDate->format( 'Y-m-d' );
            $start_date = $firstDateOfMonth->format( 'Y-m-d' );
        } 

        $log_date_array = [];

        $log_date_array['start_date'] = $start_date;
        $log_date_array['end_date'] = $end_date;
        $log_date_array['reference'] = $reference;
        $log_date_array['order'] = $order;
        $log_date_array['timeframe'] = $time_frame;
        update_option( 'mpt_log_filter_date', $log_date_array );
        exit();
    }

    /**
     * create a function to user id using using user name
     */
    public function mpt_get_user_id( $user_name ) {

        global $wpdb;
        $users_table = $wpdb->prefix.'users';
        $result = $wpdb->get_results( "SELECT ID FROM $users_table
            WHERE user_login = '$user_name' " );
        $user_id = isset( $result[0]->ID ) ? intval( $result[0]->ID ) : 0;
        return $user_id;
    }
    /**
     * update bterritory user
     */
    public function mpt_update_territory_users() {

        $user_data = isset( $_POST['user_data'] ) ? $_POST['user_data']  : [];
        if( ! $user_data ) {

            $response['status'] = 'false';
            echo json_encode( $response );
            wp_die();
        }

        if( ! empty( $user_data ) && is_array( $user_data ) ) {

            foreach( $user_data as $data ) {

                $username = isset( $data[0][0] ) ? $data[0][0] : '';
                $manager_id = self::mpt_get_user_id( $username );
                
                $territory_users_array = [];
                if( ! empty( $data ) && is_array( $data ) ) {
                    foreach( $data as $territory ) {

                        $outputArray = array_map(function($value) {
                            return str_replace(['"', '\\'], '', $value);
                        }, $territory );

                        $territory_user_names = [];
                        $teri_user_id = [];

                        if( ! empty( $outputArray ) && is_array( $outputArray ) ) {
                            foreach( $outputArray as $index => $terr_data ) {

                                if( 0 == $index ) {
                                    continue;
                                }
                                $t_user_id = self::mpt_get_user_id( $terr_data );
                                
                                if( ! $t_user_id ) {
                                    continue;
                                }
                                $territory_user_names[] = $terr_data;
                                $teri_user_id[] = $t_user_id;
                            }

                            update_user_meta( $manager_id, 'mpt_manager_users', $teri_user_id );

                            if( is_array( $territory_user_names ) ) {
                                $territory_users_name = implode( ',', $territory_user_names );
                            }
                            update_user_meta( $manager_id, 'mpt_territory_users', $territory_users_name );
                        }
                    }
                }
            }
        }
        $response['status'] = 'true';
        echo json_encode( $response );
        wp_die();
    }

    /**
     * apply css
     */
    public function mpt_download_csv() {

        global $wpdb;

        $get_filter_data = get_option( 'mpt_log_filter_date' );

        if( $get_filter_data ) {

            $start_date = isset( $get_filter_data['start_date'] ) ? $get_filter_data['start_date'] : '';
            $end_date = isset( $get_filter_data['end_date'] ) ? $get_filter_data['end_date'] : date( "Y-m-d" );
            $refrence = isset( $get_filter_data['reference'] ) ? $get_filter_data['reference'] : '';
            $order = isset( $get_filter_data['order'] ) ? $get_filter_data['order'] : 'DESC';
            $timeframe = isset( $get_filter_data['timeframe'] ) ? $get_filter_data['timeframe'] : '';
            if( $start_date ) {

                $dateTime = new DateTime( $start_date );
                $previousDate = $dateTime->format( "Y-m-d" );
                $previousDate = strtotime( $previousDate );

                $end_time = new DateTime( $end_date );
                $end_time->modify( "+1 day" );
                $nextDate = $end_time->format( "Y-m-d" );
                $nextDate = strtotime( $nextDate );

                if( $refrence ) {
                    $ref = "AND ref = '$refrence'";
                } else {
                    $ref = '';
                }

                $orderby = 'ID';

                $query = $wpdb->prepare("
                    SELECT *
                    FROM {$wpdb->prefix}myCRED_log
                    WHERE time BETWEEN %d AND %d
                    $ref
                    ORDER BY $orderby $order
                    ", $previousDate, $nextDate );

                $result = $wpdb->get_results( $query );
            } else {

                $orderby = 'ID';

                if( $refrence ) {

                    $query = $wpdb->prepare("
                        SELECT *
                        FROM {$wpdb->prefix}myCRED_log
                        WHERE ref = '".$refrence."'
                        ORDER BY $orderby $order
                        " );
                }

                $result = $wpdb->get_results( $query );                
            }
                $log_data = [];

                if ( !empty( $result ) && is_array( $result ) ) {

                    foreach ( $result as $data ) {

                        $date = isset( $data->time ) ? $data->time : 0;
                        $new_date = new DateTime();
                        $new_date->setTimestamp( $date );
                        $converted_date = $new_date->format( 'F j Y g:i a' );
                        $refrence = isset( $data->entry ) ? $data->entry : '';
                        $amount = isset( $data->creds ) ? $data->creds : 0;
                        $user_id = isset( $data->user_id ) ? intval($data->user_id) : 0;
                        $time = isset( $data->time ) ? $data->time : '';
                        $get_extra_data = get_user_meta( $user_id, 'mpt-user-extra-data-' . $time, true );

                        if( $get_extra_data ) {

                            $territory = isset( $get_extra_data[0] ) ? $get_extra_data[0] : '';
                            $recipeint = isset( $get_extra_data[1] ) ? $get_extra_data[1] : '';
                        } else {
                            
                            global $wpdb;
                            
                            $custom_table_name = $wpdb->prefix . 'mpt_custom_data';
                            $query = $wpdb->prepare(
                                "SELECT * FROM $custom_table_name WHERE user_id = %s AND dates = %s ",
                                $user_id,
                                $time
                            );
                            $results = $wpdb->get_results($query);

                            $territory = isset( $results[0]->territory_id ) ? $results[0]->territory_id : '';
                            $recipeint = isset( $results[0]->recipient_id ) ? intval( $results[0]->recipient_id ) : '';                      
                        }

                        $sender_name = Mycred_Point_Transfer::mpt_get_user_name($user_id);
                        $recipient_name = Mycred_Point_Transfer::mpt_get_user_name($recipeint);
                        $recipient_name = str_replace( "T-", "", $recipient_name );
                        $territory_name = Mycred_Point_Transfer::mpt_get_user_name($territory);
                        $territory_name = str_replace( "T-", "", $territory_name );

                        $log_data[] = [
                            'From Territory' => $territory_name,
                            'Sender' => $sender_name,
                            'Recipient' => $recipient_name,
                            'Amount' => $amount,
                            'Date' => $converted_date,
                            'Point Type' => 'Rose Point',
                            'Reference' => $refrence,
                        ];
                    }
                }

            $filename = "log_data.csv";

            header("Content-Disposition: attachment; filename=\"$filename\"");
            header("Content-Type: text/csv");

            $out = fopen("php://output", 'w');

            $flag = false;

            foreach ( $log_data as $row ) {
                if ( !$flag ) {

                    fputcsv( $out, array_keys( $row ) );
                    $flag = true;
                }

                fputcsv( $out, array_values( $row ) );
            }
            fclose($out);
            delete_option( 'mpt_log_filter_date' );
            exit;
        }
    }

    /**
     * update from and to date
     */
    public function mpt_custom_date_range() {

        $start_date = isset( $_POST['start_date'] ) ? $_POST['start_date'] : '';
        $end_date = isset( $_POST['end_date'] ) ? $_POST['end_date'] : date( "Y-m-d" );

        $dateTime = new DateTime( $start_date );
        $previousDate = $dateTime->format( "Y-m-d" );
        $previousDate = strtotime( $previousDate );

        $end_time = new DateTime( $end_date );
        $end_time->modify( "+1 day" );
        $nextDate = $end_time->format( "Y-m-d" );
        $nextDate = strtotime( $nextDate );

        $dates = [];
        $dates['start'] = $previousDate;
        $dates['end'] = $nextDate;
        update_option( 'mpt_custom_date', $dates );
        wp_die();
    }

    /**
     * update mycred log table headers
     */
    public function mpt_mycred_log_column_headers( $headers, $headers_1, $headers_2 ) {

        $headers = [
            'territory'     => 'From Territory',
            'username'      => 'Sender',
            'recipeint'     => 'Recipeint',
            'creds'         => 'Amount',
            'time'          => 'Date',
            'point_type'    => 'Point Type',
            'entry'         => 'Reference'
        ];
        return $headers;
    }

    /**
     * override mycred file
     */
    public function mpt_override_mycred_log_file() {

        copy( MPT_INCLUDES_DIR.'class.query-log.php', WP_PLUGIN_DIR.'/mycred/includes/classes/class.query-log.php' );
    }

    /**
     * update users 
     */
    public function mpt_update_users_field( $user_id ) {

        $users = isset( $_POST['mpt-users'] ) ? $_POST['mpt-users'] : '';
        $new_user_name = [];

        if( ! empty( $users ) && is_array( $users ) ) {
            foreach( $users as $user ) {

                $new_user_id = intval( $user );
                $user_name = Mycred_Point_Transfer::mpt_get_user_name( $new_user_id );
                $new_user_name[] = $user_name;
            }
        }

        $new_user_name = implode( ',', $new_user_name );
        update_user_meta( $user_id, 'mpt_territory_users', $new_user_name );
        update_user_meta( $user_id, 'mpt_manager_users', $users );
    }

    /**
     * Add user's dropdown on user edit profile
     */
    public function mpt_add_users_dropdown( $user ) {

        $user_id = isset( $user->data->ID ) ? intval( $user->data->ID ) : 0;
        $user = get_userdata( $user_id );

        if( $user ) {
            $user_roles = $user->roles;

            if( in_array( 'ag_manager', $user_roles ) || in_array( 'shop_manager', $user_roles ) || in_array( 'administrator', $user_roles ) ) {

                $terri_user = get_user_meta( $user_id, 'mpt_manager_users', true ); 
                
                $args = array(
                    'role' => 'territory_drone',
                );

                $territory_users = get_users( $args );
                ?>
                <div class="mpt-admin-wrapper">
                    <h3><?php echo __( 'Select Territory Users', MPT_TEXT_DOMAIN ); ?></h3>
                    <?php
                    if( !empty( $territory_users ) && is_array( $territory_users ) ) {
                        ?>
                        <select class="mpt-users-select-2" name="mpt-users[]" multiple>
                            <option value=""><?php echo __( 'Select a User', MPT_TEXT_DOMAIN ); ?></option>
                            <?php
                            foreach( $territory_users as $territory_user ) {
                                $user_id = isset( $territory_user->data->ID ) ? intval( $territory_user->data->ID ) : 0;
                                $user_f_name = get_user_by( 'id', $user_id );
                                ?>
                                <option value="<?php echo $user_id; ?>"<?php if( is_array( $terri_user ) ) {
                                    if( in_array( $user_id, $terri_user ) ) {
                                    echo 'selected';

                                    }
                                }?>><?php echo $user_f_name->data->display_name; ?></option>
                                <?php
                            }
                            ?>
                        </select>
                        <?php
                    }
                    ?>
                </div>
                <?php
            }
        }
    }

    /**
     * update settings
     */
    public function mpt_update_settings() {

        if( isset( $_POST['mpt-settings-submit'] ) && check_admin_referer( 'mpt_setting_nonce', 'mpt_setting_nonce_field' ) ) {

            $user_roles = isset( $_POST['mpt-select'] ) ? $_POST['mpt-select'] : '';
            $user_exclude_roles = isset( $_POST['mpt-user-exclude'] ) ? $_POST['mpt-user-exclude'] : '';
            $minimum_amount = isset( $_POST['mpt-minimum'] ) ? $_POST['mpt-minimum'] : 0;
            $increamental_amount = isset( $_POST['mpt-increamental'] ) ? $_POST['mpt-increamental'] : 0;
            $mpt_increamental_msg = isset( $_POST['mpt-increamental-message'] ) ? $_POST['mpt-increamental-message'] : '';
            $mpt_minimum_msg = isset( $_POST['mpt-minimum-message'] ) ? $_POST['mpt-minimum-message'] : '';
            $mpt_successfull_msg = isset( $_POST['mpt-successfull-message'] ) ? $_POST['mpt-successfull-message'] : '';

            $data = [];
            $data['mpt-roles'] = $user_roles;
            $data['mpt-minimum'] = $minimum_amount;
            $data['mpt-increamental'] = $increamental_amount;
            $data['mpt-increamental-msg'] = $mpt_increamental_msg;
            $data['mpt-minimum-msg'] = $mpt_minimum_msg;
            $data['mpt-exclude-users'] = $user_exclude_roles;

            update_option( 'mpt_settings', $data );

            /**
             * Redirect to the HTTP Referer
             */
            wp_redirect( add_query_arg( 'message', 'mpt_updated', $_POST['_wp_http_referer'] ) );
        }
    }

    /**
     * enqueue admin scripts
     */
    public function mpt_enqueue_admin_scripts() {

        $current_screen = get_current_screen();
        $rand = rand( 1000000, 1000000000 );
        $site_url = site_url();
        $date_range_url = $site_url.'/wp-admin/admin.php?page=mycred&show=daterange';

        wp_enqueue_style( 'select2-min-css', MPT_ASSETS_URL . 'css/select2.min.css', [], $rand, null );
        wp_enqueue_style( 'admin-css', MPT_ASSETS_URL . 'css/backend.css', [], $rand, null );
        wp_enqueue_script( 'select2-min-js', MPT_ASSETS_URL. 'js/select2.full.min.js', [ 'jquery' ], $rand, true );
        wp_enqueue_script( 'admin-js', MPT_ASSETS_URL. 'js/admin.js', [ 'jquery' ], $rand, true );
        wp_localize_script( 'admin-js', 'MPT', [
            'ajaxURL'         => admin_url( 'admin-ajax.php' ),
            'custom_date_url' => $date_range_url
        ] );
    }

    /**
     * Add menu page
     */
    public function mpt_add_menu_page() {

        add_menu_page(
            __( 'Point Transfer Settings', MPT_TEXT_DOMAIN ),
            __( 'Point Transfer Settings', MPT_TEXT_DOMAIN ),
            'manage_options',
            'mpt-transfer',
            [ $this, 'mpt_menu_callback' ],
            '',
            6
        );
    }

    /**
     * menu callback
     */
    public function mpt_menu_callback() {

        global $wp_roles;
        
        $all_roles = $wp_roles->roles;
        $get_settings = get_option( 'mpt_settings' );

        $minimun_number = isset( $get_settings['mpt-minimum'] ) ? $get_settings['mpt-minimum'] : 0;
        $increamental_number = isset( $get_settings['mpt-increamental'] ) ? $get_settings['mpt-increamental'] : 0;
        $roles = isset( $get_settings['mpt-roles'] ) ? $get_settings['mpt-roles'] : 0;
        $increamental_msg = isset( $get_settings['mpt-increamental-msg'] ) ? $get_settings['mpt-increamental-msg'] : '';
        $minimum_msg = isset( $get_settings['mpt-minimum-msg'] ) ? $get_settings['mpt-minimum-msg'] : '';
        $exclude_users = isset( $get_settings['mpt-exclude-users'] ) ? $get_settings['mpt-exclude-users'] : [];
        ?>
        <h1 class="mpt-admin-setting-title"><?php echo __( 'Mycred Point Transfer Settings' ); ?></h1>
        <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>">
            <div class="mpt-admin-wrapper">
                <div class="mpt-userrole-wrap">
                    <div class="mpt-title">
                        <?php echo __( 'Select User Role (to whom search form should be displayed)', MPT_TEXT_DOMAIN ); ?>
                    </div>
                    <div class="mpt-value">
                        <select class="mpt-select-2" name="mpt-select[]" multiple>
                            <?php

                            if( $all_roles && is_array( $all_roles ) ) {
                                foreach( $all_roles as $key => $all_role ) {
                                    ?>
                                <option value="<?php echo $key; ?>" <?php if( is_array( $roles ) ) {
                                    if( in_array( $key, $roles ) ) {
                                    echo 'selected';

                                    }
                                }?>><?php echo $all_role['name']; ?></option>
                                    <?php
                                }
                            }

                            ?>
                        </select>
                    </div>
                </div>
                <div class="mpt-userrole-wrap">
                    <div class="mpt-title">
                        <?php echo __( 'Select User Role to Exclude from search results', MPT_TEXT_DOMAIN ); ?>
                    </div>
                    <div class="mpt-value">
                        <select class="mpt-select-2" name="mpt-user-exclude[]" multiple>
                            <?php

                            if( $all_roles && is_array( $all_roles ) ) {
                                foreach( $all_roles as $key => $all_role ) {
                                    ?>
                                    <option value="<?php echo $key; ?>" <?php if( is_array( $roles ) ) {
                                        if( in_array( $key, $exclude_users ) ) {
                                            echo 'selected';

                                        }
                                    }?>><?php echo $all_role['name']; ?></option>
                                    <?php
                                }
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="mpt-minimum-wrap">
                    <div class="mpt-title">
                        <?php echo __( 'Enter minimum amount', MPT_TEXT_DOMAIN ); ?>
                    </div>
                    <div class="mpt-value">
                        <input type="number" name="mpt-minimum" value="<?php echo $minimun_number; ?>">
                    </div>
                </div>
                <div class="mpt-increamental-wrap">
                    <div class="mpt-title">
                        <?php echo __( 'Enter increamental amount', MPT_TEXT_DOMAIN ); ?>
                    </div>
                    <div class="mpt-value">
                        <input type="number" name="mpt-increamental" value="<?php echo $increamental_number; ?>">
                    </div>
                </div>
                <div class="mpt-increamental-message-wrap">
                    <div class="mpt-title">
                        <?php echo __( 'Enter Increamental Error Message', MPT_TEXT_DOMAIN ); ?>
                    </div>
                    <div class="mpt-value">
                        <textarea name="mpt-increamental-message" cols="50" rows="5" placeholder="<?php echo __( 'Type Message...', MPT_TEXT_DOMAIN ); ?>"><?php echo $increamental_msg; ?></textarea>
                    </div>
                </div>
                <div class="mpt-minimum-message-wrap">
                    <div class="mpt-title">
                        <?php echo __( 'Enter Minimum Error Message', MPT_TEXT_DOMAIN ); ?>
                    </div>
                    <div class="mpt-value">
                        <textarea name="mpt-minimum-message" cols="50" rows="5" placeholder="<?php echo __( 'Type Message...', MPT_TEXT_DOMAIN ); ?>"><?php echo $minimum_msg; ?></textarea>
                    </div>
                </div>
                <?php
                wp_nonce_field( 'mpt_setting_nonce', 'mpt_setting_nonce_field' );
                ?>
                <input type="hidden" name="action" value="mpt_setting_action">
                <input type="submit" value="<?php echo __( 'Update', MPT_TEXT_DOMAIN ); ?>" class="button button-primary mpt-settings-submit" name="mpt-settings-submit">

                <div class="mpt-progress-bar">
                    <div class="progress" style="height:24px;"></div>
                </div>

                <div class="mpt-progress-wrapper" data-start="0" data-end="100">
                    <label for="images" class="mpt-drop-container" id="dropcontainer">
                        <span class="mpt-drop-title"><?php echo __( 'Drop files here', UNR_TEXT_DOMAIN );?> </span>
                        <input type="file" class="mpt-upload-file" accept=".csv">
                    </label>
                    <input type="button" class="button button-primary mpt-territory-button" value="<?php echo __( 'Update Territory User', UNR_TEXT_DOMAIN ); ?>">
                </div>

            </div>
        </form>
        <?php
    }
}

/**
 * Initialize MPT_ADMIN_MODULE
 */
MPT_ADMIN_MODULE::instance();