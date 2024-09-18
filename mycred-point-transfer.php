<?php
/**
 * Plugin Name: Mycred Point Transfer
 * Version: 1.0
 * Description: Transfer mycred point with to users
 * Author: LDninjas.com
 * Author URI: LDninjas.com
 * Plugin URI: LDninjas.com
 * Text Domain: mycred-point-transfer
 * License: GNU General Public License v2.0
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

if( ! defined( 'ABSPATH' ) ) exit;

/**
 * Class Mycred_Point_Transfer
 */
class Mycred_Point_Transfer {

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

        if ( is_null( self::$instance ) && ! ( self::$instance instanceof Mycred_Point_Transfer ) ) {
            self::$instance = new self;

            self::$instance->setup_constants();
            self::$instance->hooks();
            self::$instance->includes();
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
        define( 'MPT_DIR', plugin_dir_path ( __FILE__ ) );
        define( 'MPT_DIR_FILE', MPT_DIR . basename ( __FILE__ ) );
        define( 'MPT_INCLUDES_DIR', trailingslashit ( MPT_DIR . 'includes' ) );
        define( 'MPT_TEMPLATES_DIR', trailingslashit ( MPT_DIR . 'templates' ) );
        define( 'MPT_BASE_DIR', plugin_basename(__FILE__));

        /**
         * URLs
         */
        define( 'MPT_URL', trailingslashit ( plugins_url ( '', __FILE__ ) ) );
        define( 'MPT_ASSETS_URL', trailingslashit ( MPT_URL . 'assets/' ) );

        define( 'MPT_VERSION', self::VERSION );

        /**
         * Text Domain
         */
        define( 'MPT_TEXT_DOMAIN', 'mycred-point-transfer' );
    }

    /**
     * Plugin requiered files
     */
    public function includes() {

        $file = MPT_INCLUDES_DIR . 'admin.php';

        if( file_exists( $file ) ) {
            require_once $file;
        }
    }

    /**
     * Plugin Hooks
     */
    public function hooks() {
        add_shortcode( 'mycred_point_transfer', [ $this, 'mtc_mycred_point_transfer' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'mpt_enqueue_scripts' ] );
        add_action( 'wp_ajax_search_user', [ $this, 'mpt_search_user' ] );
        add_action( 'wp_ajax_transfer_mycred_point', [ $this, 'mpt_transfer_mycred_point' ] );
        add_action( 'wp_ajax_current_user_mycred_point', [ $this, 'mpt_mycred_current_user_point' ] );
        add_shortcode( 'manager_history', [ $this, 'mpt_manager_history' ] );
        add_action( 'wp_ajax_mpt_pagination', [ $this, 'mpt_next_pagination' ] );
        add_action( 'wp_ajax_territory_points', [ $this, 'mpt_territory_points' ] );
    }

    /**
     *  create a function to get username
     */
    public static function mpt_get_user_name( $user_id ) {

        global $wpdb;

        $users_table = $wpdb->prefix.'users';
        $result = $wpdb->get_results( "SELECT user_login FROM $users_table
            WHERE ID = $user_id " );
        $user_name = isset( $result[0]->user_login ) ? $result[0]->user_login : '';
        return $user_name;
    }

    /**
     * get territory user point value
     */
    public function mpt_territory_points() {

        $response = [];

        $territory_id = isset( $_POST['territory_id'] ) ? intval( $_POST['territory_id'] ) : 0;
        $user_point = mycred_get_users_balance( $territory_id, 'mycred_default' );
        $response['content'] = $user_point;
        echo json_encode( $response );
        wp_die();
    }

    /**
     * Next Pagination
     */
    public function mpt_next_pagination() {

        global $wpdb;

        $page = isset( $_POST['page_no'] ) ? intval( $_POST['page_no'] ) : 1;
        $user_id = get_current_user_id();
        $response = [];

        $table_name = $wpdb->prefix . 'myCRED_log';
        $per_page = 20;
        $offset = ( $page - 1 ) * $per_page;

        $query = $wpdb->prepare(
            "SELECT * FROM $table_name
            WHERE user_id = %d
            LIMIT %d OFFSET %d",
            $user_id,
            $per_page,
            $offset
        );

        $manager_data = $wpdb->get_results( $query );

        ob_start();
        if( $manager_data ) {

            $custom_table_name = $wpdb->prefix . 'mpt_custom_data';

            foreach( $manager_data as $data ) {

                $sender_id = isset( $data->user_id ) ? intval( $data->user_id ) : 0;
                $time = isset( $data->time ) ? $data->time : '';

                $custom_query = $wpdb->prepare(
                    "SELECT * FROM $custom_table_name WHERE user_id = %s AND dates = %s ",
                    $sender_id,
                    $time
                );

                $get_extra_data = $wpdb->get_results($custom_query);

                $recipient_name = '';
                $territory_name = '';

                if( $get_extra_data ) {
 
                    $territory_name = isset( $get_extra_data[0]->territory_id ) ? $get_extra_data[0]->territory_id : '';
                    $recipient_name = isset( $get_extra_data[0]->recipient_id ) ? intval( $get_extra_data[0]->recipient_id ) : '';
                    $recipient_name = self::mpt_get_user_name( $recipient_name );
                    $territory_name = get_user_meta( $territory_name, 'acf_territory', true );
                }


                /**
                 * sender name
                 */
                $sender_name = self::mpt_get_user_name( $sender_id );

                /**
                 * strtotime to date 
                 */
                $date = date( 'Y-m-d', $data->time );
                ?>
                <tr>
                    <td><?php echo $territory_name; ?></td>
                    <td><?php echo $sender_name; ?></td>
                    <td><?php echo $recipient_name; ?></td>
                    <td><?php echo $data->creds; ?></td>
                    <td><?php echo $date; ?></td>
                    <td><?php echo 'Rose Point'; ?></td>
                    <td><?php echo $data->entry; ?></td>
                </tr>
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
     * create a shortcode to display the manager point tansfer history
     */
    public function mpt_manager_history() {

        global $wpdb;

        if( ! is_user_logged_in() ) {
            return __( 'You need to be logged in to access this page.', MPT_TEXT_DOMAIN );
        }

        $user_id = get_current_user_id();
        $user = get_userdata( $user_id );
        
        ob_start();

        if( $user ) {

            $user_roles = $user->roles;
            $table_name = $wpdb->prefix . 'myCRED_log';
            $page = 1;
            $per_page = 20;
            $offset = ( $page - 1 ) * $per_page;

            $query = $wpdb->prepare(
                "SELECT * FROM $table_name
                WHERE user_id = %d
                ORDER BY id DESC
                LIMIT %d OFFSET %d",
                $user_id,
                $per_page,
                $offset
            );

            $manager_data = $wpdb->get_results( $query );
            
            if( in_array( 'ag_manager', $user_roles ) || in_array( 'shop_manager', $user_roles ) || in_array( 'administrator', $user_roles ) ) {
 
                $actual_data = $wpdb->get_results( "SELECT * FROM $table_name
                WHERE user_id = $user_id" );

                if( ! empty( $manager_data ) && is_array( $manager_data ) ) {
                    ?>
                    <table id="mpt-manager-data-export">
                        <thead>
                            <tr>
                                <th><?php echo __( 'From Territory', MPT_TEXT_DOMAIN ); ?></th>
                                <th><?php echo __( 'Sender', MPT_TEXT_DOMAIN ); ?></th>
                                <th><?php echo __( 'Recipeint', MPT_TEXT_DOMAIN ); ?></th>
                                <th><?php echo __( 'Amount', MPT_TEXT_DOMAIN ); ?></th>
                                <th><?php echo __( 'Date', MPT_TEXT_DOMAIN ); ?></th>
                                <th><?php echo __( 'Point Type', MPT_TEXT_DOMAIN ); ?></th>
                                <th><?php echo __( 'Reference', MPT_TEXT_DOMAIN ); ?></th>
                            </tr>
                        </thead>
                        <tbody class="mpt-manager-data">
                    <?php
                    $custom_table_name = $wpdb->prefix . 'mpt_custom_data';

                    foreach( $manager_data as $data ) {

                        $sender_id = isset( $data->user_id ) ? intval( $data->user_id ) : 0;
                        $time = isset( $data->time ) ? $data->time : '';

                        $custom_query = $wpdb->prepare(
                            "SELECT * FROM $custom_table_name WHERE user_id = %s AND dates = %s ",
                            $sender_id,
                            $time
                        );

                        $get_extra_data = $wpdb->get_results($custom_query);

                        $recipient_name = '';
                        $territory_name = '';
                        if( $get_extra_data ) {

                            $territory_name = isset( $get_extra_data[0]->territory_id ) ? $get_extra_data[0]->territory_id : '';
                            $recipient_name = isset( $get_extra_data[0]->recipient_id ) ? intval( $get_extra_data[0]->recipient_id ) : '';
                            $recipient_name = self::mpt_get_user_name( $recipient_name );
                            $territory_name = Mycred_Point_Transfer::mpt_get_user_name( $territory_name );
                        }
                        /**
                         * sender name
                         */
                        $sender_name = self::mpt_get_user_name( $sender_id );

                        /**
                         * strtotime to date 
                         */
                        $date = date( 'Y-m-d', $data->time );
                        ?>
                        <tr>
                            <td><?php echo $territory_name; ?></td>
                            <td><?php echo $sender_name; ?></td>
                            <td><?php echo $recipient_name; ?></td>
                            <td><?php echo $data->creds; ?></td>
                            <td><?php echo $date; ?></td>
                            <td><?php echo 'Rose Point'; ?></td>
                            <td><?php echo $data->entry; ?></td>
                        </tr>
                        <?php
                    }
                    ?>
                    </tbody>
                    </table>
                    <?php
                    if( is_array( $actual_data ) ) {

                        $actual_count = count( $actual_data ); 
                        if( $actual_count > 20 ) { 
                            ?>
                            <div class="mpt-pagination-wrapper">
                                <span class="dashicons dashicons-arrow-left-alt2 mpt-left" data-from="0">&lt;</span>
                                <span class="dashicons dashicons-arrow-right-alt2 mpt-right" data-to="10" data-page="1">&gt;</span>
                            </div>
                            <?php
                        }
                    }
                }
            }
        }

        $content = ob_get_contents();
        ob_get_clean();
        return $content;
    }

    /**
     * transfer mycred points
     */
    public function mpt_transfer_mycred_point() {

        global $wpdb;

        $response = [];

        if( ! wp_verify_nonce( $_POST['mpt_nounce'], 'mpt_ajax_nonce' ) ) {

            $response['message'] = __( 'point not transfer', 'mycred-point-transfer' );
            $response['status'] = 'false';

            echo json_encode( $response );
            wp_die();
        }

        $user_id = isset( $_POST['user_id'] ) ? $_POST['user_id'] : 0;
        $recipent_id = isset( $_POST['recipant_id'] ) ? intval( $_POST['recipant_id'] ) : 0;
        $user_total_balance = intval( get_user_meta( $recipent_id, 'mycred_default', true ) );

        $user_data_one_name = self::mpt_get_user_name( get_current_user_id() );
        $user_data_two_name = self::mpt_get_user_name( $user_id );

        if( empty( $user_id ) ) {

            $response['message'] = __( 'user id not found', 'mycred-point-transfer' );
            $response['status'] = 'false';

            echo json_encode( $response );
            wp_die();
        }

        $get_settings = get_option( 'mpt_settings' );
        $minimum_value = isset( $get_settings['mpt-minimum'] ) ? intval( $get_settings['mpt-minimum'] ) : 0;
        $increamental_value = isset( $get_settings['mpt-increamental'] ) ? intval( $get_settings['mpt-increamental'] ) : 0;
        $increamental_msg = isset( $get_settings['mpt-increamental-msg'] ) ? $get_settings['mpt-increamental-msg'] : 'You can enter the point like '.$minimum_value.' OR gab by '.$increamental_value.'';
        $minimum_msg = isset( $get_settings['mpt-minimum-msg'] ) ? $get_settings['mpt-minimum-msg'] : 'Must be equal to or greater than '.$minimum_value.' points..';

        $point_value = isset( $_POST['value'] ) ? intval( $_POST['value'] ) : 0;

        if( empty( $point_value ) ) {

            $response['message'] = __( 'Please enter the number of points', 'mycred-point-transfer' );
            $response['status'] = 'false';

            echo json_encode( $response );
            wp_die();
        }

        if( empty( $user_total_balance ) || $user_total_balance ) {

            if( $user_total_balance < $point_value ) {

                $response['message'] = __( 'You do not have enough points', 'mycred-point-transfer' );
                $response['status'] = 'false';

                echo json_encode( $response );
                wp_die();
            }
        }

        if( ! empty( $minimum_value ) ) {

            if( $point_value < $minimum_value ) {

                $response['message'] = $minimum_msg;
                $response['status'] = 'false';

                echo json_encode( $response );
                wp_die();
            }
        }

        if( $increamental_value ) {

            $check_increament = $point_value - $minimum_value;
            $divide_increamental = $check_increament / $increamental_value;

            if( ! is_int( $divide_increamental ) ) {

                $response['message'] = $increamental_msg;
                $response['status'] = 'false';

                echo json_encode( $response );
                wp_die();
            }
        }

        mycred_add( 'Transfer', $user_id, $point_value );
        mycred_subtract( 'Transfer', $recipent_id, $point_value );
        
        $current_timestamp = wp_date( "Y-m-d H:i:s" );
        $date = strtotime( $current_timestamp );

        $table_name = $wpdb->prefix . 'myCRED_log';
        $data = array(
            'ref'       => 'Transfer',
            'ref_id'    => 0,
            'user_id'   => get_current_user_id(),
            'creds'     => $point_value,
            'ctype'     => 'mycred_default',
            'time'      => $date,
            'entry'     => 'Point Transfer from ' . $user_data_one_name . ' to ' . $user_data_two_name,
            'data'      => ''
        );

        $inserted = $wpdb->insert( $table_name, $data );

        $custom_name = $wpdb->prefix . 'mpt_custom_data';
        $custom_data = array(
            'user_id'		=> get_current_user_id(),
            'dates'			=> $date,
            'territory_id' 	=> $recipent_id,
            'recipient_id'	=> $user_id
        );

        $wpdb->insert( $custom_name, $custom_data );
        echo json_encode( $response );
        wp_die();
    }

    /**
     * search user
     */
    public function mpt_search_user() {

        global $wpdb;

        $response = [];

        if( ! wp_verify_nonce( $_POST['mpt_nounce'], 'mpt_ajax_nonce' ) ) {

            $response['message'] = __( 'data not found', 'mycred-point-transfer' );
            $response['status'] = 'false';

            echo json_encode( $response );
            wp_die();
        }

        $employe_id = isset( $_POST['emp_id'] ) ? $_POST['emp_id'] : '';
        $f_name = isset( $_POST['f_name'] ) ? $_POST['f_name'] : '';
        $l_name = isset( $_POST['l_name'] ) ? $_POST['l_name'] : '';
        $territory_id = isset( $_POST['tri_id'] ) ? $_POST['tri_id'] : '';
        
        if( ! $employe_id && ! $f_name && ! $l_name && ! $territory_id ) {

            $response['message'] = __( 'data not found', 'mycred-point-transfer' );
            $response['status'] = 'false';

            echo json_encode( $response );
            wp_die();
        }

        $em_where = '';
        $f_n_where = '';
        $l_n_where = '';
        $f_l_n_where = '';
        $OR = '';
        $key = '';
        $val = '';

        if( $employe_id && ! $f_name && ! $l_name && ! $territory_id ) {
            $em_where = "meta_key = 'acf_employee' AND meta_value = $employe_id";
            $OR = 'OR';
        }
        if( $f_name && ! $l_name && ! $territory_id ) {

            if( $employe_id ) {

                $nick_name = self::mtc_get_user_nick_name( 'first_name', 'acf_employee', $f_name, $employe_id );
                $f_l_n_where = "$OR meta_key = 'first_name' AND meta_value = '$f_name'";
            } else {
                
                $f_n_where = "$OR meta_key = 'first_name' AND meta_value = '$f_name'";
                $OR = 'OR';
            }
        }
        if( $l_name && ! $f_name && ! $territory_id ) {

            if( $employe_id ) {

                $nick_name = self::mtc_get_user_nick_name( 'last_name', 'acf_employee', $l_name, $employe_id );
                $f_l_n_where = "$OR meta_key = 'nickname' AND meta_value = '$nick_name'";

            } else {
                $l_n_where = "$OR meta_key = 'last_name' AND meta_value = '$l_name'";
            }
        }
        if( $l_name && $f_name && ! $employe_id && ! $territory_id ) {

            $nick_name = self::mtc_get_user_nick_name( 'first_name', 'last_name', $f_name, $l_name );
            $f_l_n_where = "$OR meta_key = 'nickname' AND meta_value = '$nick_name'";
        }
        if( $l_name && $f_name && $employe_id && ! $territory_id ) {

            $args = array(
                'meta_query' => array(
                    'relation' => 'AND',
                    array(
                        'key'     => 'first_name',
                        'value'   => $f_name,
                        'compare' => '='
                    ),
                    array(
                        'key'     => 'last_name',
                        'value'   => $l_name,
                        'compare' => '='
                    ),
                    array(
                        'key'     => 'acf_employee',
                        'value'   => $employe_id,
                        'compare' => '='
                    )
                )
            );

            $user_query = new WP_User_Query($args);
            $users = $user_query->get_results();

            if (!empty($users)) {

                $user = $users[0];
                $nick_name = get_user_meta($user->ID, 'nickname', true);
                $f_l_n_where = "$OR meta_key = 'nickname' AND meta_value = '$nick_name'";
            }
        }
        if( $territory_id && ! $employe_id && ! $f_name && ! $l_name ) {
            $em_where = "meta_key = 'acf_territory' AND meta_value = '$territory_id'";
            $OR = 'OR';
        }
        if( $territory_id && $employe_id && ! $f_name && ! $l_name ) {

            $args = array(
                'meta_query' => array(
                    'relation' => 'AND',
                    array(
                        'key'     => 'acf_employee',
                        'value'   => $employe_id,
                        'compare' => '='
                    ),
                    array(
                        'key'     => 'acf_territory',
                        'value'   => $territory_id,
                        'compare' => '='
                    )
                )
            );

            $user_query = new WP_User_Query($args);
            $users = $user_query->get_results();

            if ( !empty( $users ) ) {

                $user = $users[0];
                $nick_name = get_user_meta($user->ID, 'nickname', true);
                $f_l_n_where = "$OR meta_key = 'nickname' AND meta_value = '$nick_name'";
            }
        }
        if( $territory_id && ! $employe_id && $f_name && ! $l_name ) {

            $args = array(
                'meta_query' => array(
                    'relation' => 'AND',
                    array(
                        'key'     => 'acf_territory',
                        'value'   => $territory_id,
                        'compare' => '='
                    ),
                    array(
                        'key'     => 'first_name',
                        'value'   => $f_name,
                        'compare' => '='
                    )
                )
            );

            $user_query = new WP_User_Query($args);
            $users = $user_query->get_results();

            if ( !empty( $users ) ) {

                $key = 'acf_territory';
                $val = $territory_id;
                $f_l_n_where = "$OR meta_key = 'first_name' AND meta_value = '$f_name'";
            }
        }
        if( $territory_id && ! $employe_id && ! $f_name && $l_name ) {

            $args = array(
                'meta_query' => array(
                    'relation' => 'AND',
                    array(
                        'key'     => 'acf_territory',
                        'value'   => $territory_id,
                        'compare' => '='
                    ),
                    array(
                        'key'     => 'last_name',
                        'value'   => $l_name,
                        'compare' => '='
                    )
                )
            );

            $user_query = new WP_User_Query($args);
            $users = $user_query->get_results();

            if ( !empty( $users ) ) {

                $key = 'acf_territory';
                $val = $territory_id;
                $f_l_n_where = "$OR meta_key = 'last_name' AND meta_value = '$l_name'";
            }
        }
        if( $territory_id && $employe_id && $f_name && $l_name ) {

            $args = array(
                'meta_query' => array(
                    'relation' => 'AND',
                    array(
                        'key'     => 'acf_territory',
                        'value'   => $territory_id,
                        'compare' => '='
                    ),
                    array(
                        'key'     => 'last_name',
                        'value'   => $l_name,
                        'compare' => '='
                    ),
                    array(
                        'key'     => 'first_name',
                        'value'   => $f_name,
                        'compare' => '='
                    ),
                    array(
                        'key'     => 'acf_employee',
                        'value'   => $employe_id,
                        'compare' => '='
                    )
                )
            );

            $user_query = new WP_User_Query($args);
            $users = $user_query->get_results();

            if ( !empty( $users ) ) {

                $user = $users[0];
                $nick_name = get_user_meta($user->ID, 'nickname', true);
                $f_l_n_where = "$OR meta_key = 'nickname' AND meta_value = '$nick_name'";
            }
        }
        if( $territory_id && $employe_id && $f_name && ! $l_name ) {

            $args = array(
                'meta_query' => array(
                    'relation' => 'AND',
                    array(
                        'key'     => 'acf_territory',
                        'value'   => $territory_id,
                        'compare' => '='
                    ),
                    array(
                        'key'     => 'acf_employee',
                        'value'   => $employe_id,
                        'compare' => '='
                    ),
                    array(
                        'key'     => 'first_name',
                        'value'   => $f_name,
                        'compare' => '='
                    )
                )
            );

            $user_query = new WP_User_Query($args);
            $users = $user_query->get_results();

            if ( !empty( $users ) ) {

                $user = $users[0];
                $nick_name = get_user_meta($user->ID, 'nickname', true);
                $f_l_n_where = "$OR meta_key = 'nickname' AND meta_value = '$nick_name'";
            }
        }
        if( $territory_id && $employe_id && ! $f_name && $l_name ) {

            $args = array(
                'meta_query' => array(
                    'relation' => 'AND',
                    array(
                        'key'     => 'acf_territory',
                        'value'   => $territory_id,
                        'compare' => '='
                    ),
                    array(
                        'key'     => 'last_name',
                        'value'   => $l_name,
                        'compare' => '='
                    ),
                    array(
                        'key'     => 'acf_employee',
                        'value'   => $employe_id,
                        'compare' => '='
                    )
                )
            );

            $user_query = new WP_User_Query($args);
            $users = $user_query->get_results();

            if ( !empty( $users ) ) {

                $user = $users[0];
                $nick_name = get_user_meta($user->ID, 'nickname', true);
                $f_l_n_where = "$OR meta_key = 'nickname' AND meta_value = '$nick_name'";
            }
        }
        if( $territory_id && ! $employe_id && $f_name && $l_name ) {

            $args = array(
                'meta_query' => array(
                    'relation' => 'AND',
                    array(
                        'key'     => 'acf_territory',
                        'value'   => $territory_id,
                        'compare' => '='
                    ),
                    array(
                        'key'     => 'last_name',
                        'value'   => $l_name,
                        'compare' => '='
                    ),
                    array(
                        'key'     => 'first_name',
                        'value'   => $f_name,
                        'compare' => '='
                    )
                )
            );

            $user_query = new WP_User_Query($args);
            $users = $user_query->get_results();

            if ( !empty( $users ) ) {

                $user = $users[0];
                $nick_name = get_user_meta($user->ID, 'nickname', true);
                $f_l_n_where = "$OR meta_key = 'nickname' AND meta_value = '$nick_name'";
            }
        }

        $get_settings = get_option( 'mpt_settings' );
        $role_to_exclude = isset( $get_settings['mpt-exclude-users'] ) ? $get_settings['mpt-exclude-users'] : []; 
        $table_name = $wpdb->prefix.'usermeta';
        $result = $wpdb->get_results( "SELECT user_id FROM $table_name
            WHERE $em_where $f_n_where $l_n_where $f_l_n_where GROUP BY user_id" );

        if( empty( $result ) ) {

            $response['message'] = __( 'data not found', 'mycred-point-transfer' );
            $response['status'] = 'false';

            echo json_encode( $response );
            wp_die();
        }
        $no = 0;
        ob_start();
        ?>
        <div class="mpt-main-wrapper">
        <?php
        foreach( $result as $user_id ) {
            
            $u_id = intval( $user_id->user_id );
        
            if( $key ) {
            	$user_val = get_user_meta( $u_id, $key, true );

            	if( $user_val ) {
            		if( $user_val != $val ) {
            			continue;
            		}
            	}            	
            }

            $get_capability = get_user_meta( $u_id, $wpdb->prefix.'capabilities', true );
            if( ! empty( $get_capability ) && is_array( $get_capability ) ) {
                $get_capability = array_keys( $get_capability );
            }

            if( is_array( $get_capability ) && is_array( $role_to_exclude ) ) {
                $check_capability = array_intersect( $get_capability, $role_to_exclude );
                if( $check_capability ) {
                    continue;
                }
            }

            $no++;
            $em_id = get_user_meta( $u_id, 'acf_employee', true );
            $first_name = get_user_meta( $u_id, 'first_name', true );
            $last_name = get_user_meta( $u_id, 'last_name', true );
            $username = get_user_meta( $u_id, 'nickname', true );
            $user_terr = get_usermeta( $u_id, 'acf_territory', true );
            ?>
            <div class="mpt-inner-wrapper">
                <div class="mpt-name-label">
                    <?php echo $first_name.' '; ?>
                    <?php echo $last_name; ?>
                </div>
                <div class="mpt-user-label">
                    <?php echo __( 'Terr: ', MPT_TEXT_DOMAIN ); ?>
                    <?php echo $user_terr; ?>
                </div>
                <div class="mpt-user-label">
                    <?php echo __( 'User: ', MPT_TEXT_DOMAIN ); ?>
                    <?php echo $em_id.' | '; ?>
                    <?php echo $username; ?>
                </div>
                <div class="mpt-transfer-label">
                    <div class="mpt-amount-wrapper">
                        <input type="number" placeholder="<?php echo '(enter amount)'; ?>" class="mpt-point-input mpt-point-<?php echo $u_id; ?>">
                    </div>
                    <div class="mpt-send-wrapper">
                        <input type="button" class="mpt-send-btn mpt-user-id" data-user_id="<?php echo $u_id; ?>" value="<?php echo __( 'SEND', MPT_TEXT_DOMAIN ); ?>">
                    </div>
                    <div class="mpt-clear-both"></div>
                </div>
            </div>
            <?php
        }
        ?>
        </div>
        <?php

        $content = ob_get_contents();

        if( 0 == $no ) {
           $content = __( 'data not found', 'mycred-point-transfer' );
        }
        ob_get_clean();
        $response['content'] = $content;
        $response['status'] = 'true';
        echo json_encode( $response );
        wp_die();
    }

    /**
     * enqueue scripts
     */
    public function mpt_enqueue_scripts() {

        $rand = rand( 1000000, 1000000000 );
        wp_enqueue_style( 'frontend-css', MPT_ASSETS_URL . 'css/frontend.css', [], $rand, null );
        wp_enqueue_script( 'mpt-frontend', MPT_ASSETS_URL . 'js/frontend.js', [ 'jquery' ], $rand, true );
        wp_localize_script( 'mpt-frontend', 'MPT', [
            'ajaxURL'       => admin_url( 'admin-ajax.php' ),
            'security'      => wp_create_nonce( 'mpt_ajax_nonce' )
        ] );
    }

    /**
     * create a function to get user id
     */
    public static function mtc_get_user_nick_name( $key_1, $key_2, $val_1, $val_2 ) {

        $nick_name = '';

        $args = array(
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key'     => $key_1,
                    'value'   => $val_1,
                    'compare' => '='
                ),
                array(
                    'key'     => $key_2,
                    'value'   => $val_2,
                    'compare' => '='
                )
            )
        );

        $user_query = new WP_User_Query($args);
        $users = $user_query->get_results();

        if( ! empty( $users ) ) {

            $user_id = $users[0];
            $user_id = $user_id->ID;
            $nick_name = get_usermeta( $user_id, 'nickname', true );
        }

        return $nick_name;
    }

    /**
     * create a shortcode to transfer mycred point
     */
    public function mtc_mycred_point_transfer() {

        global $wpdb;

        if( ! is_user_logged_in() ) {
            return __( 'You need to be logged in to access this page.', MPT_TEXT_DOMAIN );
        }

        $user_id = get_current_user_id();
        $territory_users = get_user_meta( $user_id, 'mpt_manager_users', true ); 
        $capabilities = $wpdb->prefix.'capabilities';
        $user_capability = array_keys( get_user_meta( $user_id, $capabilities, true ) );

        $get_settings = get_option( 'mpt_settings' );

        if( is_array( $user_capability ) && is_array( $get_settings['mpt-roles'] ) ) {

            $result = array_intersect( $user_capability, $get_settings['mpt-roles'] );

            if( empty( $result ) ) {
                return __( 'You do not have access to view the page.', MPT_TEXT_DOMAIN );
            }
        }

        ob_start();
        ?>
        <div class="mpt-wrap">
            <div class="mtp-point-transfer-wrapper">
                <div class="territory-user-wrapper">
                    <?php
                    if( ! empty( $territory_users ) && is_array( $territory_users ) ) {
                        ?>
                        <select class="mpt-recipant-id">
                            <option value=""><?php echo __( 'Select a Territory', MPT_TEXT_DOMAIN ); ?></option>
                        <?php
                        foreach( $territory_users as $user ) {

                            $user_f_name = self::mpt_get_user_name( $user );
                            ?>
                            <option value="<?php echo $user; ?>"><?php echo $user_f_name; ?></option>
                            <?php
                        }
                        ?>
                        </select>
                        <span class="territory-user-points"></span>
                        <?php
                    } else {
                        ?>
                        <div class="mpt-territory-message">
                            <?php echo __( 'Please select territory users from the edit profile', 'mycred-point-transfer' ); ?>
                        </div>
                        <?php
                    }
                    ?>
                </div>
                <div class="mpt-search-section">
                    <input type="text" class="mpt-territory-id" placeholder="<?php echo __( 'Territory#', MPT_TEXT_DOMAIN ) ?>">
                    <input type="text" class="mpt-employee-id" placeholder="<?php echo __( 'Employee ID', MPT_TEXT_DOMAIN ) ?>">
                    <input type="text" class="mpt-f-name" placeholder="<?php echo __( 'First Name', MPT_TEXT_DOMAIN ) ?>">
                    <input type="text" class="mpt-l-name" placeholder="<?php echo __( 'Last Name', MPT_TEXT_DOMAIN ) ?>">
                    <input type="button" value="<?php echo __( 'SEARCH',MPT_TEXT_DOMAIN ); ?>" class="mpt-search-btn mpt-search-btn-sml">
                    <input type="button" value="<?php echo __( 'CLEAR',MPT_TEXT_DOMAIN ); ?>" class="mpt-clear-btn">
                    <div class="mpt-clear-both"></div>
                </div>
                    <div class="mpt-data-section">
                    <div class="mpt-table-result-head"></div>
                    <div class="mpt-table-data"></div>
                </div>
            </div>
        </div>
        <?php
        $content = ob_get_contents();
        ob_get_clean();
        return $content;
    }

    /**
     *  Get Current user point
     */

    public function mpt_mycred_current_user_point() {

        $response = [];
        $user_id = isset( $_POST['user_id'] ) ? $_POST['user_id'] : 0;

        if( ! $user_id ) {
            
            $response['status'] = 'false';
            wp_die();
        }

        $user_point = mycred_get_users_balance( $user_id, 'mycred_default' );

        if( $user_point ) {

            $response['status'] = 'true';
            $response['user_point'] = $user_point;

            echo json_encode( $response );
            wp_die();
        }
        echo json_encode( $response );
        wp_die();
    }
}

/**
 * Display admin notifications if dependency not found.
 */
function MPT_ready() {

    if( !is_admin() ) {
        return;
    }

    if( ! class_exists( 'myCRED_Core' ) ) {
        deactivate_plugins ( plugin_basename ( __FILE__ ), true );
        $class = 'notice is-dismissible error';
        $message = __( 'Mycred Sync Points add-on requires mycred plugin is to be activated', 'mycred-point-transfer' );
        printf ( '<div id="message" class="%s"> <p>%s</p></div>', $class, $message );
    }
}

/**
 * @return bool
 */
function MPT() {
    if ( ! class_exists( 'myCRED_Core' ) ) {
        add_action( 'admin_notices', 'MPT_ready' );
        return false;
    }

    return Mycred_Point_Transfer::instance();
}
add_action( 'plugins_loaded', 'MPT' );

/**
 * create table on plugin activation
 */
register_activation_hook( __FILE__, 'mpt_create_custom_table' );

    function mpt_create_custom_table() {

        global $wpdb;
        $table_name = $wpdb->base_prefix . 'mpt_custom_data';
        $query = $wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table_name));

        if (!$wpdb->get_var($query) == $table_name) {

            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE {$wpdb->base_prefix}mpt_custom_data (
                 ID INT PRIMARY KEY AUTO_INCREMENT,
                 user_id BIGINT(255),
                 dates VARCHAR(65535),
                 territory_id BIGINT(255),
                 recipient_id BIGINT(255)
             ) $charset_collate;";
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }