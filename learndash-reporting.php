<?php
/**
 * Plugin Name: LearnDash Reporting
 * Version: 1.0
 * Description:  
 * Author:  LDNinjas
 * Author URI:  ldninjas.com
 * Plugin URI: ldninjas.com
 * Text Domain: learndash-reporting
 */

if( ! defined( 'ABSPATH' ) ) exit;

/**
 * Class Learndash_Reporting
 */
class Learndash_Reporting {

    const VERSION = '1.0';

    /**
     * @var self
     */
    private static $instance = null;

    /**
     * @since 1.0
     * @return $this
     */
    public static function instance() {

        if ( is_null( self::$instance ) && ! ( self::$instance instanceof Learndash_Reporting ) ) {
            self::$instance = new self;
            self::$instance->setup_constants();
            self::$instance->includes();
            self::$instance->hooks();
        }

        return self::$instance;
    }

    /**
     * defining constants for plugin
     */
    public function setup_constants() {

        /**
         * Directory
         */
        define( 'LR_DIR', plugin_dir_path ( __FILE__ ) );
        define( 'LR_DIR_FILE', LR_DIR . basename ( __FILE__ ) );
        define( 'LR_INCLUDES_DIR', trailingslashit ( LR_DIR . 'includes' ) );
        define( 'LR_TEMPLATES_DIR', trailingslashit ( LR_DIR . 'templates' ) );
        define( 'LR_BASE_DIR', plugin_basename(__FILE__));

        /**
         * URLs
         */
        define( 'LR_URL', trailingslashit ( plugins_url ( '', __FILE__ ) ) );
        define( 'LR_ASSETS_URL', trailingslashit ( LR_URL . 'assets/' ) );

        /**
         * Text Domain
         */
        define( 'LR_TEXT_DOMAIN', 'learndash-reporting' );

        /**
         * version
         */
        define( 'LR_VERSION', self::VERSION );
    }

    /**
     * Includes
     */
    public function includes() {}

    /**
     * define hooks
     */
    private function hooks() {
        add_shortcode( 'learndash-reporting', [ $this, 'lr_reporting_shortcode' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'lr_enqueue_scripts' ] );
        add_action( 'wp_ajax_set_course_according_to_group', [ $this, 'lr_set_course_according_to_group' ] );
        add_action( 'wp_ajax_create_report', [ $this, 'lr_create_report' ] );
        add_action( 'wp_ajax_search_group', [ $this, 'lr_search_group' ] );
        add_action( 'wp_ajax_download_csv_report', [ $this, 'lr_download_csv_report' ] );
        add_action( 'init', [ $this, 'lr_download_csv' ] );
        add_action( 'wp_ajax_load_group_option', [ $this, 'lr_load_group_option' ] );
        // add_action( 'wp', function() {

        //     $role = get_role('student');
        //     var_dump( $role );
        // } );
    }

    /**
     * load more option
     */
    public function lr_load_group_option() {

        $response = [];
        global $wpdb;

        $user_id = get_current_user_id();
        $meta_key_pattern = 'learndash_group_leaders_%';
        $current_page = isset( $_POST['count'] ) ? intval( $_POST['count'] ) : 1;
        $limit = 5;
        $offset = ($current_page - 1) * $limit;

        $query = $wpdb->prepare(
            "SELECT meta_value
            FROM {$wpdb->usermeta}
            WHERE meta_key LIKE %s AND user_id = %d
            LIMIT %d OFFSET %d",
            $meta_key_pattern, 
            $user_id,
            $limit,
            $offset
        );

        $results = $wpdb->get_results($query);

        ob_start();
        
        if( ! empty( $results ) && is_array( $results ) ) {
            foreach( $results as $result ) {

                $group_id = isset( $result->meta_value ) ? $result->meta_value : 0;
                ?>
                <div class="lr-group-option" data-group_id="<?php echo $group_id; ?>"><?php echo get_the_title( $group_id ); ?></div>
                <?php
            }
        }

        $content = ob_get_contents();
        ob_get_clean();

        $response['content'] = $content;
        $response['status'] = 'true';
        echo json_encode( $response );
        wp_die();
    }

    /**
     * download csv
     */
    public function lr_download_csv() {

        global $wpdb;

        $get_download_options = get_option( 'lr-download-data' );
        $group_id = isset( $get_download_options['group_id'] ) ? $get_download_options['group_id'] : 0;

        if( $group_id ) {

            $course_id = isset( $get_download_options['course_id'] ) ? $get_download_options['course_id'] : 0;
            $given_course_id = isset( $get_download_options['course_id'] ) ? $get_download_options['course_id'] : 0;
            $group_users = learndash_get_groups_user_ids( $group_id );
            $activity_type = isset( $get_download_options['activity_type'] ) ? $get_download_options['activity_type'] : 0;            
            $data_type = isset( $get_download_options['data_type'] ) ? $get_download_options['data_type'] : '';

            if( 'complete' == $data_type ) {
                $activity_type = 'completed';
            }

            if( 'in-progress' == $data_type ) {
                $activity_type = 'in-progress';
            }

            if( 'not-started' == $data_type ) {
                $activity_type = 'not-started';
            }

            if( ! $group_id ) {
                wp_die();
            }

            if( 'not-started' == $activity_type ) {

                if( ! $course_id ) {
                    $access_type = "'access', 'group_progress'";
                }

                if( $course_id ) {
                    $access_type = "'course','access', 'group_progress'";
                }

                $where = "AND ( activity_status = 0 OR activity_status = 'NULL' OR activity_completed != 0 )";
                $status_text = 'Not started';
            }

            if( 'in-progress' == $activity_type ) {

                $access_type = "'course','group_progress'";
                $where = 'AND activity_started != 0 AND activity_completed = 0 AND activity_completed = "NULL"';
                $status_text = 'In progress';
            }

            if( 'completed' == $activity_type ) {

                $access_type = "'course','group_progress'";

                $where = "AND activity_completed != 0 AND activity_completed != 'NULL'";
                $status_text = 'Completed';
            }

            if( 'Select progress' == $activity_type && ! $course_id && $group_id ) {
                $access_type = "'group_progress'";
                $status_text = '';
            }

            if( 'Select progress' == $activity_type && $course_id && $group_id ) {
                $access_type = "'access','group_progress'";
                $status_text = '';
            }

            if( ! $group_users ) {
                wp_die();
            }

            if( $group_users ) {
                $group_users = implode( ",", $group_users );
            }

            if( ! $course_id ) {
                $course_id = learndash_get_group_courses_list( $group_id );
                $course_id = implode( ",", $course_id );
            }

            $table_name = $wpdb->prefix . 'learndash_user_activity';
            $query = $wpdb->prepare(
                "
                SELECT * 
                FROM $table_name
                WHERE user_id IN ( $group_users )
                AND ( course_id IN ( $course_id ) OR course_id = 0 )
                AND activity_type IN ( $access_type )
                $where
                GROUP BY user_id order by activity_id DESC
                "
            );

            $filtered_data = $wpdb->get_results($query);
            $log_data = [];

            if( $filtered_data ) {

                foreach( $filtered_data as $data ) {

                    $user_id = isset( $data->user_id ) ? intval( $data->user_id ) : 0;
                    $course_id = isset( $data->course_id ) ? intval( $data->course_id ) : 0;
                    if( ! $course_id ) {

                        $course_ids = learndash_get_group_courses_list( $group_id );

                        if( ! empty( $course_ids ) && is_array( $course_ids ) ) {
                            foreach( $course_ids as $course_id ) {

                                if( ! empty( $given_course_id ) && ( $given_course_id != $course_id ) ) {
                                    continue;
                                }

                                $progress = learndash_course_progress(
                                    array(
                                        'user_id'   => $user_id,
                                        'course_id' => $course_id,
                                        'array'     => true,
                                    )
                                );

                                $course_completion_text = '';
                                $course_completion = isset( $progress['completed'] ) ? $progress['completed'] : 0;
                                $course_percentage = isset( $progress['percentage'] ) ? $progress['percentage'] : 0;

                                if( ! $course_completion ) {
                                    $course_completion_text = 'Not started';
                                } elseif( $course_completion < $progress['total'] ) {
                                    $course_completion_text = 'In progress';                                    
                                } else {
                                    $course_completion_text = 'Completed';
                                }

                                if( ! empty( $status_text ) && $status_text != $course_completion_text ) {
                                    continue;
                                }

                                $enrolled_date = isset( $data->activity_updated ) ? $data->activity_updated : '';
                                if( $enrolled_date ) {
                                    $enrolled_date = date( 'Y-m-d', $enrolled_date );    
                                } else {
                                    $enrolled_date = isset( $data->activity_updated ) ? $data->activity_updated : '';
                                    $enrolled_date = date( 'Y-m-d', $enrolled_date );
                                }
                                
                                $query = $wpdb->prepare(
                                    "
                                    SELECT display_name
                                    FROM {$wpdb->users}
                                    WHERE ID = %d
                                    ",
                                    $user_id
                                );

                                $username = $wpdb->get_var($query);

                                $last_logged_in = get_user_meta( $user_id, 'learndash-last-login', true );

                                if( $last_logged_in ) {
                                    $formatted_date = date( 'd-m-Y', $last_logged_in );
                                } else {
                                    $formatted_date = 'Never';
                                }

                                $log_data[] = [
                                    'Stucent Name'   => ucwords( $username ),
                                    'Joining Date'   => $enrolled_date,
                                    'Course Name'    => get_the_title( $course_id ),
                                    'Percentage'     => $course_percentage.'%',
                                    'Last Accessed'  => $formatted_date,
                                    'Status'         => $course_completion_text,
                                ];
                            }
                        } 
                    } else {

                        $progress = learndash_course_progress(
                            array(
                                'user_id'   => $user_id,
                                'course_id' => $course_id,
                                'array'     => true,
                            )
                        );

                        $course_completion_text = '';
                        $course_completion = isset( $progress['completed'] ) ? $progress['completed'] : 0;
                        $course_percentage = isset( $progress['percentage'] ) ? $progress['percentage'] : 0;

                        if( ! $course_completion ) {
                            $course_completion_text = 'Not started';
                        } elseif( $course_completion < $progress['total'] ) {
                            $course_completion_text = 'In progress';
                        } else {
                            $course_completion_text = 'Completed';
                        }

                        if( ! empty( $status_text ) && $status_text != $course_completion_text ) {
                            continue;
                        }

                        $enrolled_date = isset( $data->activity_started ) ? $data->activity_updated : '';
                        
                        if( $enrolled_date ) {
                            $enrolled_date = date('Y-m-d', $enrolled_date );
                        } else {
                            $enrolled_date = isset( $data->activity_updated ) ? $data->activity_updated : '';
                            $enrolled_date = date( 'Y-m-d', $enrolled_date );
                        }
    
                        $query = $wpdb->prepare(
                            "
                            SELECT display_name
                            FROM {$wpdb->users}
                            WHERE ID = %d
                            ",
                            $user_id
                        );

                        $username = $wpdb->get_var($query);

                        $last_logged_in = get_user_meta( $user_id, 'learndash-last-login', true );

                        if( $last_logged_in ) {
                            $formatted_date = date( 'd-m-Y', $last_logged_in );
                        } else {
                            $formatted_date = 'Never';
                        }

                        $log_data[] = [
                            'Stucent Name'   => ucwords( $username ),
                            'Joining Date'   => $enrolled_date,
                            'Course Name'    => get_the_title( $course_id ),
                            'Percentage'     => $course_percentage.'%',
                            'Last Accessed'  => $formatted_date,  
                            'Status'         => $course_completion_text,
                        ];
                    }
                }
            }

            $filename = "reporting_data.csv";

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
            delete_option( 'lr-download-data' );
            exit;
        }
    }

    /**
     * download csv report
     */
    public function lr_download_csv_report() {

        $group_id = isset( $_POST['group_id'] ) ? intval( $_POST['group_id'] ) : 0;
        $course_id = isset( $_POST['course_id'] ) ? intval( $_POST['course_id'] ) : 0;
        $activity_type = isset( $_POST['type'] ) ? $_POST['type'] : '';
        $data_type = isset( $_POST['data_type'] ) ? $_POST['data_type'] : '';
        
        $download_data = [];

        $download_data['download'] = 'Yes';
        $download_data['group_id'] = $group_id;
        $download_data['course_id'] = $course_id;
        $download_data['activity_type'] = $activity_type;
        $download_data['data_type'] = $data_type;

        update_option( 'lr-download-data', $download_data );
        wp_die();
    }

    /**
     * get groups
     */
    public function lr_search_group() {

        global $wpdb;

        $user_id = get_current_user_id();
        $group_name = isset( $_POST['group_name'] ) ? str_replace( ' ', '-', $_POST['group_name'] ) : '';        

        $pattern = '%'.$group_name.'%';
        $sql = $wpdb->prepare(
            "SELECT ID FROM $wpdb->posts WHERE post_type = %s AND post_name LIKE %s",
            'groups',
            $pattern 
        );

        $results = $wpdb->get_col($sql);
        ob_start();
        if( ! empty( $results ) && is_array( $results ) ) {
            foreach( $results as $group_id ) {
                $group_id = intval( $group_id );
                $user_is_group_leader = get_user_meta( $user_id, 'learndash_group_leaders_'.$group_id, true );
                if( ! $user_is_group_leader ) {
                    continue;
                }
                ?>
                <option value="<?php echo $group_id; ?>"><?php echo get_the_title( $group_id ); ?></option>
                <?php
            }
        }
        $content = ob_get_contents();
        ob_get_clean();

        $response['content'] = $content;
        $response['status'] = 'true';
        echo json_encode( $response );
        wp_die();
    }

    /**
     * create report according to instruction
     */ 
    public function lr_create_report() {

        $response = [];
        global $wpdb;

        $group_id = isset( $_POST['group_id'] ) ? intval( $_POST['group_id'] ) : 0;
        $course_id = isset( $_POST['course_id'] ) ? intval( $_POST['course_id'] ) : 0;
        $given_course_id = isset( $_POST['course_id'] ) ? intval( $_POST['course_id'] ) : 0;
        $group_users = learndash_get_groups_user_ids( $group_id );
        $activity_type = isset( $_POST['type'] ) ? $_POST['type'] : '';
        $data_type = isset( $_POST['data_type'] ) ? $_POST['data_type'] : '';

        if( 'complete' == $data_type ) {
            $activity_type = 'completed';
        }

        if( 'in-progress' == $data_type ) {
            $activity_type = 'in-progress';
        }

        if( 'not-started' == $data_type ) {
            $activity_type = 'not-started';
        }

        if( 'not-started' == $activity_type ) {
            
            if( ! $course_id ) {
                $access_type = "'access', 'group_progress'";
            }

            if( $course_id ) {
                $access_type = "'course','access', 'group_progress'";
            }

            $where = "AND ( activity_status = 0 OR activity_status = 'NULL' OR activity_completed != 0 )";
            $status_text = 'Not started';
        }

        if( 'in-progress' == $activity_type ) {
    
            $access_type = "'course','group_progress'";
            $where = 'AND activity_started != 0 AND activity_completed = 0 AND activity_completed = "NULL"';
            $status_text = 'In progress';
        }

        if( 'completed' == $activity_type ) {

            $access_type = "'course','group_progress'";

            $where = "AND activity_completed != 0 AND activity_completed != 'NULL'";
            $status_text = 'Completed';
        }

        if( 'Select progress' == $activity_type && ! $course_id && $group_id ) {
            $access_type = "'group_progress'";
            $status_text = '';
        }

        if( 'Select progress' == $activity_type && $course_id && $group_id ) {
            $access_type = "'access','group_progress'";
            $status_text = '';
        }

        if( ! $group_users ) {
            wp_die();
        }

        if( $group_users ) {
            $group_users = implode( ",", $group_users );
        }

        if( ! $course_id ) {
            $course_id = learndash_get_group_courses_list( $group_id );
            $course_id = implode( ",", $course_id );
        }

        $items_per_page = 10;
        $page_number = isset($_POST['paged']) ? (int) $_POST['paged'] : 1;
        $offset = ($page_number - 1) * $items_per_page;
        $table_name = $wpdb->prefix . 'learndash_user_activity';
        $count_query = $wpdb->prepare(
            "
            SELECT activity_id 
            FROM $table_name
            WHERE user_id IN ( $group_users )
            AND ( course_id IN ( $course_id ) OR course_id = 0 )
            AND activity_type IN ( $access_type )
            $where
            GROUP BY user_id order by activity_id DESC
               "
        );

        $total_count = $wpdb->get_results( $count_query );
        $total_data = count( $total_count );
        $total_count = ceil( count( $total_count ) / $items_per_page );

        $query = $wpdb->prepare(
            "
            SELECT * 
            FROM $table_name
            WHERE user_id IN ( $group_users )
            AND ( course_id IN ( $course_id ) OR course_id = 0 )
            AND activity_type IN ($access_type)
            $where
            GROUP BY user_id order by activity_id DESC
            LIMIT %d OFFSET %d
            ",
            $items_per_page, $offset
        );

        $filtered_data = $wpdb->get_results($query);
               
        ob_start();
        if( $filtered_data ) {

            ?>
            <style type="text/css">
                .lr-csv-btn {
                    display: inline-block;
                }
            </style>
            <div class="lr-table-wrapper">
                <input type="hidden" class="lr-total-pages" value="<?php echo $total_count; ?>">
                <input type="hidden" class="lr-group-id" value="<?php echo $group_id; ?>">
                <input type="hidden" class="lr-course-id" value="<?php echo $given_course_id; ?>">
                <table id="lr-table">
                    <tr>
                        <th>
                            <?php echo __( 'Student Name', LR_TEXT_DOMAIN ); ?>
                            <span class="dashicons dashicons-arrow-up lr-arrow-up" data-coulmn="0"></span>
                            <span class="dashicons dashicons-arrow-down lr-arrow-down" data-coulmn="0"></span>        
                        </th>
                        <th>
                            <?php echo __( 'Joining Date', LR_TEXT_DOMAIN ); ?>
                            <span class="dashicons dashicons-arrow-up lr-arrow-up" data-coulmn="1"></span>
                            <span class="dashicons dashicons-arrow-down lr-arrow-down" data-coulmn="1"></span>
                        </th>
                        <th><?php echo __( 'Course Name', LR_TEXT_DOMAIN ); ?></th>
                        <th>
                            <?php echo __( 'Status', LR_TEXT_DOMAIN ); ?>
                            <span class="dashicons dashicons-arrow-up lr-arrow-up" data-coulmn="3"></span>
                            <span class="dashicons dashicons-arrow-down lr-arrow-down" data-coulmn="3"></span>        
                        </th>
                        <th><?php echo __( 'Percentage', LR_TEXT_DOMAIN ); ?></th>
                        <th><?php echo __( 'Last Accessed', LR_TEXT_DOMAIN ); ?></th>
                        <th><?php echo __( 'Download Certificate', LR_TEXT_DOMAIN ); ?></th>
                    </tr>
                    <tbody>
                    <?php 
                    foreach( $filtered_data as $data ) {
                        
                        $user_id = isset( $data->user_id ) ? intval( $data->user_id ) : 0;
                        $course_id = isset( $data->course_id ) ? intval( $data->course_id ) : 0;
                        if( ! $course_id ) {

                            $course_ids = learndash_get_group_courses_list( $group_id );

                            if( ! empty( $course_ids ) && is_array( $course_ids ) ) {
                                foreach( $course_ids as $course_id ) {
                                    
                                    $query = $wpdb->prepare(
                                        "
                                        SELECT display_name
                                        FROM {$wpdb->users}
                                        WHERE ID = %d
                                        ",
                                        $user_id
                                    );

                                    $username = $wpdb->get_var($query);

                                    if( ! empty( $given_course_id ) && ( $given_course_id != $course_id ) ) {
                                        continue;
                                    }

                                    $progress = learndash_course_progress(
                                        array(
                                            'user_id'   => $user_id,
                                            'course_id' => $course_id,
                                            'array'     => true,
                                        )
                                    );

                                    $course_percentage = isset( $progress['percentage'] ) ? $progress['percentage'] : 0;
                                    $course_completion = isset( $progress['completed'] ) ? $progress['completed'] : 0;
                                    if( ! $course_completion ) {
                                        $course_completion_text = 'Not started';
                                    } elseif( $course_completion < $progress['total'] ) {
                                        $course_completion_text = 'In progress';                                    }
                                    else {
                                        $course_completion_text = 'Completed';
                                    }

                                    if( ! empty( $status_text ) && $status_text != $course_completion_text ) {
                                        continue;
                                    }

                                    $enrolled_date = isset( $data->activity_updated ) ? $data->activity_updated : '';
                                    $enrolled_date = date('d-m-Y', $enrolled_date );

                                    $download_text = '-';
                                    if( 'Completed' == $course_completion_text ) {
                                        $download_text = __( 'Download', LR_TEXT_DOMAIN );
                                    }

                                    $last_logged_in = get_user_meta( $user_id, 'learndash-last-login', true );

                                    if( $last_logged_in ) {
                                        $formatted_date = date( 'd-m-Y', $last_logged_in );
                                    } else {
                                        $formatted_date = 'Never';
                                    }

                                    ?>
                                    <tr>
                                        <td><?php echo ucwords( $username ); ?></td>
                                        <td><?php echo $enrolled_date; ?></td>
                                        <td><?php echo get_the_title( $course_id ); ?></td>
                                        <td><?php echo $course_completion_text; ?></td>
                                        <td><?php echo $course_percentage.'%'; ?></td>
                                        <td><?php echo $formatted_date; ?></td>
                                        <td><a href="<?php echo learndash_get_course_certificate_link( $course_id, $user_id ); ?>" target="_blank"><?php echo $download_text; ?></a></td>
                                    </tr>
                                    <?php
                                }
                            }
                        } else {
                                $progress = learndash_course_progress(
                                    array(
                                        'user_id'   => $user_id,
                                        'course_id' => $course_id,
                                        'array'     => true,
                                    )
                                );

                                $course_percentage = isset( $progress['percentage'] ) ? $progress['percentage'] : 0;
                                $course_completion_text = '';
                                $course_completion = isset( $progress['completed'] ) ? $progress['completed'] : 0;
                                if( ! $course_completion ) {
                                    $course_completion_text = 'Not started';
                                } elseif( $course_completion < $progress['total'] ) {
                                    $course_completion_text = 'In progress';
                                } else {
                                    $course_completion_text = 'Completed';
                                }

                                if( ! empty( $status_text ) && $status_text != $course_completion_text ) {
                                    continue;
                                }

                            $enrolled_date = isset( $data->activity_started ) ? $data->activity_updated : '';
                            if( ! $enrolled_date ) {
                                $enrolled_date = isset( $data->activity_updated ) ? $data->activity_updated : '';
                            }
                            $enrolled_date = date('d-m-Y', $enrolled_date );

                            $query = $wpdb->prepare(
                                "
                                SELECT display_name
                                FROM {$wpdb->users}
                                WHERE ID = %d
                                ",
                                $user_id
                            );

                            $username = $wpdb->get_var($query);
                            $download_text = '-';
                            if( 'Completed' == $course_completion_text ) {
                                $download_text = __( 'Download', LR_TEXT_DOMAIN );
                            }

                            $last_logged_in = get_user_meta( $user_id, 'learndash-last-login', true );
                            if( $last_logged_in ) {
                                $formatted_date = date( 'd-m-Y', $last_logged_in );
                            } else {
                                $formatted_date = 'Never';
                            }
                            
                            ?>
                            <tr>
                                <td><?php echo ucwords( $username ); ?></td>
                                <td><?php echo $enrolled_date; ?></td>
                                <td><?php echo get_the_title( $course_id ); ?></td>
                                <td><?php echo $course_completion_text; ?></td>
                                <td><?php echo $course_percentage.'%'; ?></td>
                                <td><?php echo $formatted_date; ?></td>
                                <td><a href="<?php echo learndash_get_course_certificate_link( $course_id, $user_id ); ?>" target="_blank"><?php echo $download_text; ?></a></td>
                            </tr>
                            <?php
                        }
                    }
                    ?>     
                    </tbody>               
                </table>
                <?php
                if( $total_data > 10 ) {
                    ?>
                    <div class="lr-pagination">
                        <input type="hidden" value="<?php echo $total_count; ?>" class="lr-last-page">
                        <span class="dashicons dashicons-controls-skipback lr-skipback"></span>
                        <span class="dashicons dashicons-arrow-left-alt2 lr-less-than"></span>
                        <span><?php echo $page_number.' out of '.$total_count; ?></span>
                        <span class="dashicons dashicons-arrow-right-alt2 lr-greater-than"></span>
                        <span class="dashicons dashicons-controls-skipforward lr-skipforward"></span>
                    </div>
                    <?php
                }
                ?>
            </div>
            <?php
        } else {

            ?>
            <div class="lr-table-wrapper">
                <table>
                    <tr>
                        <th><?php echo __( 'Student Name', LR_TEXT_DOMAIN ); ?></th>
                        <th><?php echo __( 'Joining Date', LR_TEXT_DOMAIN ); ?></th>
                        <th><?php echo __( 'Course Name', LR_TEXT_DOMAIN ); ?></th>
                        <th><?php echo __( 'Status', LR_TEXT_DOMAIN ); ?></th>
                    </tr>
                    <tr>
                        <td colspan="4"><?php echo __( 'There are no results for your search terms', LR_TEXT_DOMAIN ); ?></td>
                    </tr>
                </table>
            </div>
            <?php
        }

        $content = ob_get_contents();
        ob_get_clean();

        $response['content'] = $content;
        $response['status'] = 'true';
        echo json_encode( $response );
        wp_die();
    }
    /**
     * set course according to group
     */
    public function lr_set_course_according_to_group() {

        $response = [];
        $group_id = isset( $_POST['group_id'] ) ? intval( $_POST['group_id'] ) : 0;
        
        $group_courses = learndash_get_group_courses_list( $group_id );
        ob_start();
        if( ! empty( $group_courses ) && is_array( $group_courses ) ) {
            ?>
            <option value=""><?php echo __( 'Select a Course', LR_TEXT_DOMAIN ); ?></option>
            <?php
            foreach( $group_courses as $group_course ) {
                ?>
                <option value="<?php echo $group_course; ?>"><?php echo get_the_title( $group_course ); ?></option>
                <?php
            }
        }
        $content = ob_get_contents();
        ob_get_clean();

        $response['content'] = $content;
        $response['status'] = 'true';
        echo json_encode( $response );
        wp_die();
    }

    /**
     * enqueue scripts
     */
    public function lr_enqueue_scripts() {

        $rand = rand( 1, 99999999999 );

        wp_enqueue_style( 'lr-frontend-css', LR_ASSETS_URL . 'css/lr-frontend_2.css', [], $rand, null );
        wp_enqueue_script( 'lr-frontend-js', LR_ASSETS_URL . 'js/lr-frontend.js', [ 'jquery' ], $rand, true );
        wp_localize_script( 'lr-frontend-js', 'LR', [
            'ajaxURL'       => admin_url( 'admin-ajax.php' ),
            'baseURL'       => get_permalink(),
        ] );
    }

    /**
     * reporting shortcode
     */
    public function lr_reporting_shortcode( $atts ) {

        $user_id = get_current_user_id();

        if( ! $user_id ) {
            return;
        }

        $user = get_userdata( $user_id );

        if( in_array( 'administrator', $user->roles ) || in_array( 'group_leader', $user->roles ) ) {

            $data_type = isset( $atts['data_type'] ) ? $atts['data_type'] : '';
            return self::lr_get_shortcode_html( $data_type );
        }
    }

    /**
     * create a function to get shortcode html
     */
    public static function lr_get_shortcode_html( $data_type ) {

        global $wpdb;

        $disabled = '';

        if( $data_type ) {
            $disabled = 'disabled';
        }

        $user_id = get_current_user_id();

        $meta_key_pattern = 'learndash_group_leaders_%';

        $option_count = $wpdb->prepare(
            "SELECT meta_value
            FROM {$wpdb->usermeta}
            WHERE meta_key LIKE %s AND user_id = %d",
            $meta_key_pattern, 
            $user_id
        );

        $option_count_result = $wpdb->get_results( $option_count );
        $option_count_result = count( $option_count_result );
        
        $limit = 5;

        $query = $wpdb->prepare(
            "SELECT meta_value
            FROM {$wpdb->usermeta}
            WHERE meta_key LIKE %s AND user_id = %d
            LIMIT %d",
            $meta_key_pattern, 
            $user_id,
            $limit
        );

        $results = $wpdb->get_results($query);

        ob_start();
        ?>
        <div class="lr-main-wrapper">
            <input type="hidden" value="1" class="lr-pagination-page">
            <div class="lr-group-dropdown-wrapper lr-parent"> 
                <div class="lr-group-dropdown-header">
                    <div class="lr-select-text-wrap"><?php echo __( 'Select a group', LR_TEXT_DOMAIN ); ?></div>
                    <div class="dashicons dashicons-arrow-down-alt2 lr-group-down-arrow"></div>        
                </div>
                <div class="lr-group-dropdown-content">
                    <?php 
                    ?>
                    <div class="lr-inner-wrapper">
                        <?php
                        if( ! empty( $results ) && is_array( $results ) ) {                            
                            ?>
                            <div class="lr-select-group-text">
                                <?php echo __( 'Select a group', LR_TEXT_DOMAIN ); ?>
                            </div>
                            <?php
                            foreach( $results as $result ) {
                                $group_id = isset( $result->meta_value ) ? $result->meta_value : 0;
                                ?>
                                <div class="lr-group-option" data-group_id="<?php echo $group_id; ?>"><?php echo get_the_title( $group_id ); ?></div>                                
                                <?php
                            }
                        }

                        if( $option_count_result > count( $results ) ) {
                            ?>
                            <div class="lr-load-more" data-number="1" date-total-data="<?php echo $option_count_result; ?>"><?php echo __( 'Load more' ); ?></div>
                            <?php
                        }
                        ?>
                    </div>
                </div>
            </div>
            <div class="lr-parent">
                <select class="lr-course">
                    <option value=""><?php echo __( 'Select a Course', LR_TEXT_DOMAIN ); ?></option>
                </select>
            </div>
            <div class="lr-parent">
                <select class="lr-filter" <?php echo $disabled; ?>>
                    <option><?php echo __( 'Select progress', LR_TEXT_DOMAIN ); ?></option>
                    <option value="not-started"><?php echo __( 'Not started', LR_TEXT_DOMAIN ); ?></option>
                    <option value="in-progress"><?php echo __( 'In progress', LR_TEXT_DOMAIN ); ?></option>
                    <option value="completed"><?php echo __( 'Completed', LR_TEXT_DOMAIN ); ?></option>
                </select>
            </div>
            <div class="lr-parent">
                <input type="button" class="lr-button" value="<?php echo __( 'Generate Report', LR_TEXT_DOMAIN ); ?>" data-type="<?php echo $data_type; ?>">
                <img src="<?php echo LR_ASSETS_URL.'images/spinner.gif' ?>" class="lr-loader">
            </div>
        </div>
        <div class="lr-csv-wrapper">
            <button class="lr-csv-btn"><?php echo __( 'Download Report', LR_TEXT_DOMAIN ); ?></button>
        </div>
        <div class="lr-filter-wrapper"></div>
        <?php
        $content = ob_get_contents();
        ob_get_clean();
        return $content;
    }
}

/**
 * Display admin notifications if dependency not found.
 */
function learndash_reporting_ready() {

    if( ! is_admin() ) {

        return;
    }

    if( ! class_exists( 'SFWD_LMS' ) ) {

        deactivate_plugins ( plugin_basename ( __FILE__ ), true );
        $class = 'notice is-dismissible error';
        $message = __( 'LearnDash Reporting add-on requires LearnDash to be activated', 'LR_TEXT_DOMAIN' );
        printf ( '<div id="message" class="%s"> <p>%s</p></div>', $class, $message );
    }
}

/**
 * @return bool
 */
function LearnDash_reporting() {

    if ( ! class_exists( 'SFWD_LMS' ) ) {
        
        add_action( 'admin_notices', 'learndash_reporting_ready' );
        return false;
    }

    return Learndash_Reporting::instance();
}
add_action( 'plugins_loaded', 'LearnDash_reporting' );