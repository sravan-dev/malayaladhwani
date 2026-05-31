<?php
/**
 * Plugin Name: User Menu Manager
 * Description: Allows administrators to control which admin menu items are visible for specific users.
 * Version: 1.0
 * Author: Sravan
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class User_Menu_Manager {

    public function __construct() {
        // Add settings page
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        
        // Save settings
        add_action( 'admin_init', [ $this, 'save_settings' ] );
        
        // Hide menus for users
        add_action( 'admin_menu', [ $this, 'restrict_menu_items' ], 999 );
    }

    /**
     * Add the configuration page under 'Users'.
     */
    public function add_admin_menu() {
        add_users_page(
            'User Menu Manager',
            'Menu Permissions',
            'manage_options',
            'user-menu-manager',
            [ $this, 'render_admin_page' ]
        );
    }

    /**
     * Save the form data.
     */
    public function save_settings() {
        if ( ! isset( $_POST['umm_nonce'] ) || ! wp_verify_nonce( $_POST['umm_nonce'], 'umm_save_settings' ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( isset( $_POST['umm_user_id'] ) ) {
            $user_id = intval( $_POST['umm_user_id'] );
            $hidden_items = isset( $_POST['umm_hidden_items'] ) ? array_map( 'sanitize_text_field', $_POST['umm_hidden_items'] ) : [];
            
            update_user_meta( $user_id, 'umm_hidden_items', $hidden_items );
            
            // Redirect to avoid resubmission
            wp_redirect( add_query_arg( [ 'page' => 'user-menu-manager', 'user_id' => $user_id, 'updated' => 'true' ], admin_url( 'users.php' ) ) );
            exit;
        }
    }

    /**
     * Apply restrictions based on user meta.
     */
    public function restrict_menu_items() {
        // Fix: Do not restrict items if we are actively managing them on the settings page
        // otherwise they disappear from the list and cannot be un-hidden.
        if ( isset( $_GET['page'] ) && $_GET['page'] === 'user-menu-manager' ) {
            return;
        }

        if ( ! is_user_logged_in() ) {
            return;
        }

        $user_id = get_current_user_id();
        $hidden_items = get_user_meta( $user_id, 'umm_hidden_items', true );

        if ( ! empty( $hidden_items ) && is_array( $hidden_items ) ) {
            foreach ( $hidden_items as $slug ) {
                // SAFETY: Never allow hiding the 'users.php' menu if the user has capability to manage options,
                // otherwise they will lock themselves out of this plugin!
                if ( $slug === 'users.php' && current_user_can( 'manage_options' ) ) {
                    continue;
                }
                
                remove_menu_page( $slug );
            }
        }
    }


    /**
     * Render the settings page.
     */
    public function render_admin_page() {
        global $menu;
        
        // Get all currently visible menu items
        // We need to access the global $menu array to list available options.
        // Copying it to avoid modifying the actual display here.
        $available_menus = $menu;

        $selected_user_id = isset( $_GET['user_id'] ) ? intval( $_GET['user_id'] ) : 0;
        $saved_hidden_items = [];
        
        if ( $selected_user_id ) {
            $saved_hidden_items = get_user_meta( $selected_user_id, 'umm_hidden_items', true );
            if ( ! is_array( $saved_hidden_items ) ) {
                $saved_hidden_items = [];
            }
        }

        ?>
        <div class="wrap">
            <h1>User Menu Manager</h1>
            
            <!-- User Selection Form -->
            <form method="get" action="<?php echo admin_url('users.php'); ?>">
                <input type="hidden" name="page" value="user-menu-manager">
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="user_id">Select User</label></th>
                        <td>
                            <?php
                            wp_dropdown_users( [
                                'name' => 'user_id',
                                'id'   => 'umm_user_select', // Added ID for JS targeting
                                'selected' => $selected_user_id,
                                'show_option_none' => 'Select a user...',
                            ] );
                            ?>
                            <input type="submit" class="button" value="Select">
                            <p class="description">Select a user to manage their menu permissions.</p>
                        </td>
                    </tr>
                </table>
            </form>


            <?php if ( $selected_user_id ) : ?>
                <hr>
                <h2>Manage Menu Visibility for User ID: <?php echo $selected_user_id; ?></h2>
                
                <form method="post" action="">
                    <?php wp_nonce_field( 'umm_save_settings', 'umm_nonce' ); ?>
                    <input type="hidden" name="umm_user_id" value="<?php echo $selected_user_id; ?>">
                    
                    <table class="widefat fixed striped">
                        <thead>
                            <tr>
                                <th class="check-column"><input type="checkbox" id="cb-select-all-1"></th>
                                <th>Menu Name</th>
                                <th>Capability / Slug</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $available_menus as $item ) : ?>
                                <?php
                                $name = strip_tags( $item[0] );
                                $capability = $item[1];
                                $slug = $item[2];
                                $is_hidden = in_array( $slug, $saved_hidden_items );
                                ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="umm_hidden_items[]" value="<?php echo esc_attr( $slug ); ?>" 
                                            <?php checked( $is_hidden ); ?>>
                                    </td>
                                    <td><strong><?php echo $name; ?></strong></td>
                                    <td><code><?php echo $slug; ?></code></td>
                                    <td>
                                        <?php if ( $is_hidden ) : ?>
                                            <span class="dashicons dashicons-hidden" style="color: #d63638;"></span> <span style="color: #d63638;">Hidden</span>
                                        <?php else : ?>
                                            <span class="dashicons dashicons-visibility" style="color: #00a32a;"></span> Visible
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes">
                    </p>
                </form>
            <?php endif; ?>
        </div>
        <script>
            jQuery(document).ready(function($){
                 // Select All checkbox
                 $('#cb-select-all-1').click(function(){
                     $('input[name="umm_hidden_items[]"]').prop('checked', this.checked);
                 });

                 // Auto-submit on user change
                 $('#umm_user_select').change(function(){
                     $(this).closest('form').submit();
                 });
            });
        </script>

        <?php
    }
}

new User_Menu_Manager();
