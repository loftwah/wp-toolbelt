<?php
/**
 * Toolbelt Admin Screen.
 *
 * @package toolbelt
 */

/**
 * Add a "settings" link to Toolbelt in the plugin list.
 *
 * This function gets called for all plugins, so we need to check which plugin
 * is being tested, and only add the settings for the relevant one.
 *
 * @param array  $plugin_actions The current links for the plugin being checked.
 * @param string $plugin_file The filepath for the plugin being checked.
 */
function tb_admin_settings_link( $plugin_actions, $plugin_file ) {

	$new_actions = array();

	if ( basename( TB_PATH ) . '/index.php' === $plugin_file ) {

		$new_actions['sc_settings'] = sprintf(
			'<a href="%2$s">%1$s</a>',
			esc_html__( 'Settings', 'wp-toolbelt' ),
			esc_url( add_query_arg( array( 'page' => 'toolbelt' ), admin_url( 'options-general.php' ) ) )
		);

	}

	return array_merge( $new_actions, $plugin_actions );

}

add_filter( 'plugin_action_links', 'tb_admin_settings_link', 10, 2 );


/**
 * Add a Toolbelt admin menu item.
 */
function tb_admin_menu() {

	add_options_page(
		'Toolbelt', // Page title.
		'Toolbelt', // Menu title.
		'manage_options', // Author capability.
		'toolbelt', // Slug.
		'tb_admin_page'
	);

}

add_action( 'admin_menu', 'tb_admin_menu' );


/**
 * Display a module activation field.
 *
 * Displays a brief description and a link to the module docs.
 *
 * @param array $args List of parameters for the field.
 */
function tb_field( $args ) {

	$key = $args['key'];
	$options = get_option( 'toolbelt_options' );

	$checked = true;
	if ( empty( $options[ $key ] ) ) {
		$checked = false;
	}

?>

	<tr>
		<th class="check-column" scope="row">
			<input
				id="<?php echo esc_attr( $key ); ?>"
				name="toolbelt_options[<?php echo esc_attr( $key ); ?>]"
				<?php checked( $checked ); ?>
				type="checkbox" />
		</th>
		<td class="column-title column-primary">
			<strong><?php echo esc_html( $args['name'] ); ?></strong>
		</td>
		<td>
			<?php echo esc_html( $args['description'] ); ?>
		</td>
		<td>
			<a href="<?php echo esc_html( $args['docs'] ); ?>"><?php esc_html_e( 'Documentation', 'wp-toolbelt' ); ?></a>
		</td>
	</tr>

<?php

}


/**
 * Display the Toolbelt admin page.
 */
function tb_admin_page() {

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	tb_save_settings();

	require TB_PATH . 'admin/settings.php';

}


/**
 * Save Toolbelt settings.
 */
function tb_save_settings() {

	// Use the nonce to check to see if the form has been submitted.
	if ( filter_input( INPUT_POST, '_wpnonce' ) === null ) {
		return;
	}

	/**
	 * Check the admin referer.
	 * Quit automatically if the nonce is missing/ wrong.
	 */
	check_admin_referer( 'toolbelt_settings' );

	// Get the options and filter them.
	$options = filter_input( INPUT_POST, 'toolbelt_options', FILTER_DEFAULT, FILTER_FORCE_ARRAY );
	// Save the options.
	update_option( 'toolbelt_options', $options );

}
