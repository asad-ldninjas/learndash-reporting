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
    }

    /**
     * get groups
     */
    public function lr_search_group() {

        global $wpdb;

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
        $group_courses = learndash_get_group_courses_list( $group_id );
        
        if( $group_id && ! $course_id ) {
            ob_start();
            ?>
            <div class="lr-table-wrapper">
                <table>
                    <tr>
                        <th><?php echo __( 'Student Name', LR_TEXT_DOMAIN ); ?></th>
                        <th><?php echo __( 'Joining Date', LR_TEXT_DOMAIN ); ?></th>
                        <th><?php echo __( 'Course Name', LR_TEXT_DOMAIN ); ?></th>
                        <th><?php echo __( 'Status', LR_TEXT_DOMAIN ); ?></th>
                    </tr>
                <?php
                $group_users = learndash_get_groups_user_ids( $group_id );
                if( ! empty( $group_users ) && is_array( $group_users ) ) {
                    foreach( $group_users as $user_id ) {

                        $sql = $wpdb->prepare(
                            "SELECT display_name FROM $wpdb->users WHERE ID = %d",
                            $user_id
                        );
                        $display_name = $wpdb->get_var($sql);

                        if( ! empty( $group_courses ) && is_array( $group_courses ) ) {
                            foreach( $group_courses as $course_id ) {
                                
                                $progress = learndash_course_progress(
                                    array(
                                        'user_id'   => $user_id,
                                        'course_id' => $course_id,
                                        'array'     => true,
                                    )
                                );

                                $unique_class = '';
                                $course_completion_text = '';
                                $course_completion = isset( $progress['completed'] ) ? $progress['completed'] : 0;
                                if( ! $course_completion ) {
                                    $course_completion_text = 'NOT STARTED';
                                    $unique_class = 'lr-not-started';
                                } elseif( $course_completion < $progress['total'] ) {
                                    $course_completion_text = 'IN PROGRESS';
                                    $unique_class = 'lr-in-progress';
                                } else {
                                    $course_completion_text = 'COMPLETE';
                                    $unique_class = 'lr-completed';
                                }   
                                $enrolled_date = get_user_meta( $user_id, 'learndash_course_'.$course_id.'_enrolled_at', true );
                                if( ! $enrolled_date ) {
                                    $enrolled_date = get_user_meta( $user_id, 'learndash_group_'.$group_id.'_enrolled_at', true );                                    
                                }
                                ?>
                                <tr class="<?php echo $unique_class; ?> lr-table-body">
                                    <td><?php echo ucwords( $display_name ); ?></td>
                                    <td><?php echo date('Y-m-d', $enrolled_date ); ?></td>
                                    <td><?php echo get_the_title( $course_id ); ?></td>
                                    <td><?php echo $course_completion_text; ?></td>
                                </tr>
                                <?php
                            }
                        }
                    }
                }
                ?>
                </table>
            </div>
            <?php

            $content = ob_get_contents();
            ob_get_clean();

            $response['content'] = $content;
            $response['status'] = 'true';
            echo json_encode( $response );
            wp_die();
        }

        if( $group_id && $course_id ) {

            ob_start();
            ?>
            <div class="lr-table-wrapper">
                <table>
                    <tr>
                        <th><?php echo __( 'Student Name', LR_TEXT_DOMAIN ); ?></th>
                        <th><?php echo __( 'Joining Date', LR_TEXT_DOMAIN ); ?></th>
                        <th><?php echo __( 'Course Name', LR_TEXT_DOMAIN ); ?></th>
                        <th><?php echo __( 'Status', LR_TEXT_DOMAIN ); ?></th>
                    </tr>
                    <?php
                    $group_users = learndash_get_groups_user_ids( $group_id );
                    if( ! empty( $group_users ) && is_array( $group_users ) ) {
                        foreach( $group_users as $user_id ) {

                            $sql = $wpdb->prepare(
                                "SELECT display_name FROM $wpdb->users WHERE ID = %d",
                                $user_id
                            );
                            $display_name = $wpdb->get_var($sql);
                            // $enrolled_date = get_user_meta( $user_id, 'learndash_course_'.$course_id.'_enrolled_at', true );
                            // if( ! $enrolled_date ) {
                            //     $enrolled_date = get_user_meta( $user_id, 'learndash_group_'.$group_id.'_enrolled_at', true );        
                            //     if( ! $enrolled_date ) {
                            //         $enrolled_date = get_user_meta( $user_id, 'course_'.$course_id.'_access_from', true );
                            //     }      

                            //     if( ! $enrolled_date ) {
                                    
                            //         $enrolled_date = get_user_meta( $user_id, 'group_'.$group_id.'_access_from', true );
                            //     }

                            //     if( $enrolled_date ) {
                            //         $enrolled_date = date('Y-m-d', $enrolled_date );
                            //     } else {

                            //         $post_type = 'course';
                            //         $query = $wpdb->prepare(
                            //             "SELECT activity_started
                            //             FROM {$wpdb->prefix}learndash_user_activity
                            //             WHERE activity_type = %s
                            //             AND course_id = %d
                            //             AND user_id = %d",
                            //             $post_type,
                            //             $course_id,
                            //             $user_id
                            //         );
                            //         $enrolled_date = $wpdb->get_var($query);
                            //     }
                            // }

                            $post_type = 'course';
                            $query = $wpdb->prepare(
                                "SELECT activity_started
                                FROM {$wpdb->prefix}learndash_user_activity
                                WHERE activity_type = %s
                                AND course_id = %d
                                AND user_id = %d",
                                $post_type,
                                $course_id,
                                $user_id
                            );
                            $enrolled_date = $wpdb->get_var($query);
                            $enrolled_date = date('Y-m-d', $enrolled_date );
                            $progress = learndash_course_progress(
                                array(
                                    'user_id'   => $user_id,
                                    'course_id' => $course_id,
                                    'array'     => true,
                                )
                            );

                            $unique_class = '';
                            $course_completion_text = '';
                            $course_completion = isset( $progress['completed'] ) ? $progress['completed'] : 0;
                            if( ! $course_completion ) {
                                $course_completion_text = 'NOT STARTED';
                                $unique_class = 'lr-not-started';
                            } elseif( $course_completion < $progress['total'] ) {
                                $course_completion_text = 'IN PROGRESS';
                                $unique_class = 'lr-in-progress';
                            } else {
                                $course_completion_text = 'COMPLETE';
                                $unique_class = 'lr-completed';
                            } 

                            ?>
                            <tr class="<?php echo $unique_class; ?> lr-table-body">
                                <td><?php echo ucwords( $display_name ); ?></td>
                                <td><?php echo $enrolled_date; ?></td>
                                <td><?php echo get_the_title( $course_id ); ?></td>
                                <td><?php echo $course_completion_text; ?></td>
                            </tr>
                            <?php 
                        }
                    }
                    ?>
                </table>
            </div>
            <?php

            $content = ob_get_contents();
            ob_get_clean();

            $response['content'] = $content;
            $response['status'] = 'true';
            echo json_encode( $response );
            wp_die();
        }
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

        $rand = rand( 1000000, 1000000000 );

        wp_enqueue_style( 'frontend-css', LR_ASSETS_URL . 'css/frontend.css', [], $rand, null );
        wp_enqueue_script( 'lr-frontend', LR_ASSETS_URL . 'js/frontend.js', [ 'jquery' ], $rand, true );
        wp_localize_script( 'lr-frontend', 'LR', [
            'ajaxURL'       => admin_url( 'admin-ajax.php' ),
        ] );

        wp_enqueue_style( 'select2-css', LR_ASSETS_URL . 'css/select2.min.css', [], $rand, null );
        wp_enqueue_script( 'select2-js', LR_ASSETS_URL . 'js/select2.full.min.js', [ 'jquery' ], $rand, true );
    }

    /**
     * reporting shortcode
     */
    public function lr_reporting_shortcode() {

        return self::lr_get_shortcode_html();
    }

    /**
     * create a function to get shortcode html
     */
    public static function lr_get_shortcode_html() {

        ob_start();
        $groups = learndash_get_groups();
        $groups = array_column( $groups, 'ID' );
        ?>
        <div class="lr-main-wrapper">
            <select class="lr-group">
                <option value=""><?php echo __( 'Select a Group', LR_TEXT_DOMAIN ); ?></option>
            </select>
            <select class="lr-course">
                <option value=""><?php echo __( 'Select a Course', LR_TEXT_DOMAIN ); ?></option>
            </select>
            <input type="button" class="lr-button" value="<?php echo __( 'Create Report', LR_TEXT_DOMAIN ); ?>">
            <img src="<?php echo LR_ASSETS_URL.'images/spinner.gif' ?>" class="lr-loader">
        </div>
        <div class="lr-filter-wrapper">
            <select class="lr-filter">
                <option><?php echo __( 'Select Filter', LR_TEXT_DOMAIN ); ?></option>
                <option value="not-started"><?php echo __( 'NOT STARTED', LR_TEXT_DOMAIN ); ?></option>
                <option value="in-progress"><?php echo __( 'IN PROGRESS', LR_TEXT_DOMAIN ); ?></option>
                <option value="completed"><?php echo __( 'COMPLETE', LR_TEXT_DOMAIN ); ?></option>
            </select>
        </div>
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
