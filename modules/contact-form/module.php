<?php
/**
 * Custom contact form
 *
 * Form validation uses Bouncer: @link https://github.com/cferdinandi/bouncer
 * Form design inspired by: @link https://adamsilver.io/articles/form-design-from-zero-to-hero-all-in-one-blog-post/
 *
 * @package toolbelt
 */

/**
 * Templates
 * ---
 * Project Quote Form
 * Blank Form
 * Competition form
 * Feedback form (with star rating)
 * template inspiration - https://www.typeform.com/templates/
 * Survey Form
 *
 * New Field Ideas
 * ---
 * Range slider
 * Number (with min and max values?)
 * Hidden field
 * Star rating
 * Country list
 *
 * Wishlist
 * ---
 * Stripe and Paypal support.
 * Add support for extra blocks inside contact form.
 * Drag and drop items in multi field?
 * custom post type to temporarily store contact form messages.
 * daily wp_cron to delete old contact form messages.
 * weekly wp_cron to report spam emails.
 * [shorttags] for subject line to add different properties from the form.
 */

/**
 * Register a Contact Form block.
 *
 * @return void
 */
function toolbelt_contact_form_register_block() {

	// Skip block registration if Gutenberg is not enabled.
	if ( ! function_exists( 'register_block_type' ) ) {
		return;
	}

	$block_js = plugins_url( 'block.min.js', __FILE__ );
	if ( WP_DEBUG ) {
		$block_js = plugins_url( 'block.js', __FILE__ );
	}

	$block_name = 'toolbelt-contact-form-block';

	wp_register_script(
		$block_name,
		$block_js,
		array(
			'wp-blocks',
			'wp-i18n',
			'wp-element',
			'wp-components',
			'wp-compose',
			'wp-block-editor',
		),
		'1.0',
		true
	);

	// The main contact form.
	register_block_type(
		'toolbelt/contact-form',
		array(
			'editor_script' => 'toolbelt-contact-form-block',
			'render_callback' => 'toolbelt_contact_form_html',
			'attributes' => array(
				'align' => array(
					'default' => '',
					'enum' => array( 'wide', 'full' ),
					'type' => 'string',
				),
			),
		)
	);

	// Field render methods.
	register_block_type(
		'toolbelt/field-text',
		array(
			'parent' => array( 'toolbelt/contact-form' ),
			'render_callback' => 'toolbelt_contact_field_text',
		)
	);

	// Name.
	register_block_type(
		'toolbelt/field-name',
		array(
			'parent' => array( 'toolbelt/contact-form' ),
			'render_callback' => 'toolbelt_contact_field_name',
		)
	);

	// Subject.
	register_block_type(
		'toolbelt/field-subject',
		array(
			'parent' => array( 'toolbelt/contact-form' ),
			'render_callback' => 'toolbelt_contact_field_subject',
		)
	);

	// Email.
	register_block_type(
		'toolbelt/field-email',
		array(
			'parent' => array( 'toolbelt/contact-form' ),
			'render_callback' => 'toolbelt_contact_field_email',
		)
	);

	// Url.
	register_block_type(
		'toolbelt/field-url',
		array(
			'parent' => array( 'toolbelt/contact-form' ),
			'render_callback' => 'toolbelt_contact_field_url',
		)
	);

	// Date.
	register_block_type(
		'toolbelt/field-date',
		array(
			'parent' => array( 'toolbelt/contact-form' ),
			'render_callback' => 'toolbelt_contact_field_date',
		)
	);

	// Telephone number.
	register_block_type(
		'toolbelt/field-telephone',
		array(
			'parent' => array( 'toolbelt/contact-form' ),
			'render_callback' => 'toolbelt_contact_field_telephone',
		)
	);

	// Textarea.
	register_block_type(
		'toolbelt/field-textarea',
		array(
			'parent' => array( 'toolbelt/contact-form' ),
			'render_callback' => 'toolbelt_contact_field_textarea',
		)
	);

	// Checkbox.
	register_block_type(
		'toolbelt/field-checkbox',
		array(
			'parent' => array( 'toolbelt/contact-form' ),
			'render_callback' => 'toolbelt_contact_field_checkbox',
		)
	);

	// Checkbox Multi.
	register_block_type(
		'toolbelt/field-checkbox-multiple',
		array(
			'parent' => array( 'toolbelt/contact-form' ),
			'render_callback' => 'toolbelt_contact_field_checkbox_multi',
		)
	);

	// Radio buttons.
	register_block_type(
		'toolbelt/field-radio',
		array(
			'parent' => array( 'toolbelt/contact-form' ),
			'render_callback' => 'toolbelt_contact_field_radio',
		)
	);

	// Select box.
	register_block_type(
		'toolbelt/field-select',
		array(
			'parent' => array( 'toolbelt/contact-form' ),
			'render_callback' => 'toolbelt_contact_field_select',
		)
	);

}


/**
 * Submit the form and send an email.
 *
 * @return void
 */
function toolbelt_contact_submit() {

	/**
	 * Check for the nonce and the hash.
	 * Both have to exist for the contact form to submit.
	 */
	$hash = filter_input( INPUT_POST, 'toolbelt-form-hash' );
	$nonce = filter_input( INPUT_POST, 'toolbelt-token' );
	if ( $hash && ! wp_verify_nonce( $nonce, 'toolbelt_contact_form_token' ) ) {
		wp_die( esc_html__( 'Contact Form timed out', 'wp-toolbelt' ) );
	}

	if ( ! $hash ) {
		return;
	}

	$post_id = filter_input( INPUT_POST, 'toolbelt-post-id', FILTER_SANITIZE_NUMBER_INT );

	/**
	 * Quit because there's nothing to do here.
	 */
	if ( false === $post_id ) {
		return;
	}

	$contact_post = get_post( $post_id );

	/**
	 * Quit because there's no post with this id.
	 */
	if ( null === $contact_post ) {
		return;
	}

	/**
	 * Get the allowed blocks and fields.
	 */
	$blocks = toolbelt_contact_get_blocks( $contact_post->post_content, $hash );
	$fields = toolbelt_contact_get_fields( $blocks );

	/**
	 * Get block attributes from the block data so that we can send the email
	 * with the relevant info.
	 */
	$to = toolbelt_contact_get_block_attribute( $blocks, 'to', get_option( 'admin_email' ) );
	$subject = toolbelt_contact_get_block_attribute( $blocks, 'subject', $contact_post->post_title );
	$subject = apply_filters( 'toolbelt_contact_form_subject', get_option( 'blogname' ) . ': ' . $subject );

	/**
	 * Grab additional data from the fields list. This will use the first item
	 * of the specified type that is found. It's assumed these are more
	 * important than the ones lower down the form.
	 */
	$message = toolbelt_contact_create_message( $fields );
	$comment_author = toolbelt_contact_get_field( $fields, 'name' );
	$subject = toolbelt_contact_get_field( $fields, 'subject', $subject );
	$from_email_address = toolbelt_contact_get_field( $fields, 'email', get_option( 'admin_email' ) );

	if ( empty( $message ) ) {
		return;
	}

	$headers = array(
		'From: "' . $comment_author . '" <' . $to . '>',
		'Reply-To: "' . $comment_author . '" <' . $from_email_address . '>',
		'Content-Type: text/html; charset=UTF-8',
	);

	/**
	 * Check for spam.
	 *
	 * If the Toolbelt spam module is activated then it will use the internal
	 * spam checking system to ensure content is safe to post.
	 */
	$is_spam = apply_filters( 'toolbelt_contact_form_spam', false );
	$is_spam_content = apply_filters( 'toolbelt_contact_form_spam_content', implode( ' ', array( $message, $subject, $comment_author ) ) );

	/**
	 * If is spam then prepend a message on the front of the content subject
	 * line.
	 */
	if ( $is_spam || $is_spam_content ) {

		$subject = '**SPAM** ' . $subject;

	}

	/**
	 * Work out where to redirect the page to.
	 *
	 * Redirecting the page stops the message from being sent again if the page
	 * is refreshed.
	 *
	 * It also gives us the opportunity to add a 'message sent' parameter to the
	 * url so that we can display a message telling the user whst has happened.
	 */
	$return_url = add_query_arg(
		array(
			'toolbelt-message' => 'sent',
		),
		get_permalink( $contact_post )
	);

	/**
	 * If the post is spam, and the feedback form post type is enabled then we
	 * won't actually send the spam message. The message can be seen in the
	 * admin instead.
	 */
	$send_email = true;

	/**
	 * Save the email to the database.
	 */
	if ( function_exists( 'toolbelt_contact_save_feedback' ) ) {

		toolbelt_contact_save_feedback(
			$to,
			$subject,
			$message,
			$is_spam || $is_spam_content,
			$fields
		);

		// The email has been saved so let's not send anything.
		$send_email = false;

	}

	/**
	 * Actually send the email.
	 */
	if ( $send_email ) {

		wp_mail(
			sanitize_email( $to ),
			esc_html( $subject ),
			wp_kses_post( $message ),
			$headers
		);

	}

	wp_safe_redirect( $return_url );

	die();

}

add_action( 'init', 'toolbelt_contact_submit' );


/**
 * Get the Contact Form blocks from the post content.
 *
 * @param string $content The post content.
 * @param string $hash The contact form hash.
 * @return array<mixed>
 */
function toolbelt_contact_get_blocks( $content, $hash ) {

	if ( ! $content ) {
		return array();
	}

	$content_blocks = parse_blocks( $content );

	$blocks = array();

	foreach ( $content_blocks as $index => $block ) {

		// If this is a toolbelt contact form block.
		if ( 'toolbelt/contact-form' === $block['blockName'] ) {

			/**
			 * If it's this specific contact form block.
			 *
			 * The hash 'should' be unique, but may not always be. In the case
			 * that it's not unique settings will be taken from the first form
			 * found with the same hash value.
			 *
			 * Since the hash is the same the attributes will be the same as
			 * well, so this shouldn't be a problem.
			 */
			if ( toolbelt_contact_hash( $block['attrs'] ) === $hash ) {

				$blocks[] = $block;

			}
		}
	}

	return $blocks;

}


/**
 * Combine all of the contact form fields from the post blocks.
 *
 * @param array<mixed> $blocks The list of blocks to check.
 * @return array<mixed>
 */
function toolbelt_contact_get_fields( $blocks ) {

	$fields = array();

	foreach ( $blocks as $index => $block ) {

		$fields = array_merge( toolbelt_contact_parse_fields( $block ), $fields );

	}

	return $fields;

}


/**
 * Get a specific attribute for the current block.
 *
 * @param array<mixed> $blocks The blocks to get the attributes from.
 * @param string       $attribute The name of the attribute to search for.
 * @param mixed        $default The default value if none is set in the attribute.
 * @return mixed
 */
function toolbelt_contact_get_block_attribute( $blocks, $attribute = '', $default = null ) {

	$value = '';

	if ( isset( $blocks[0]['attrs'][ $attribute ] ) ) {
		$value = $blocks[0]['attrs'][ $attribute ];
	}

	if ( empty( $value ) && null !== $default ) {
		$value = $default;
	}

	return $value;

}


/**
 * Convert an arrazy of fields and values into a text string to send as an email
 * message.
 *
 * @param array<mixed> $fields The list of fields to search through.
 * @return string
 */
function toolbelt_contact_create_message( $fields ) {

	$email = array();

	/**
	 * Loop through the fields and compare to the POST data.
	 */
	foreach ( $fields as $field => $data ) {

		$email[] = '<strong>' . esc_html( $data['label'] ) . ' <em>(' . esc_html( $data['type'] ) . ')</em></strong>';
		$email[] = toolbelt_contact_sanitize( $data );
		// Add a blank line separating the different fields.
		$email[] = '';

	}

	return implode( '<br />' . "\r\n", $email );

}


/**
 * Get field value from list of fields.
 *
 * @param array<mixed> $fields List of fields to search through.
 * @param string       $key Field key to return.
 * @param mixed        $default The default value for the field.
 * @return mixed
 */
function toolbelt_contact_get_field( $fields, $key = '', $default = null ) {

	if ( ! $key ) {
		return '';
	}

	$value = '';

	foreach ( $fields as $field ) {

		if ( $key === $field['type'] ) {

			$value = $field['value'];

		}
	}

	/**
	 * Ensure we have a default set before using it.
	 */
	if ( null !== $default && empty( $value ) ) {
		$value = $default;
	}

	return $value;

}


/**
 * Generate a contact form hash.
 *
 * This needs to be repeatable (a pure function) so that we can match form
 * properties between the front and backend.
 *
 * @param array<string|int> $atts The contact form attributes.
 * @return string
 */
function toolbelt_contact_hash( $atts ) {

	$atts = shortcode_atts(
		array(
			'subject' => '',
			'to' => '',
			'submitButtonText' => esc_html__( 'Submit', 'wp-toolbelt' ),
		),
		$atts,
		'contact-form-hash'
	);

	/**
	 * The WordPress json encode function can return false.
	 * This ensures it is always a string.
	 */
	$json_hash_string = wp_json_encode( $atts );
	if ( ! $json_hash_string ) {
		$json_hash_string = 'Toolbelt';
	}

	return sha1( $json_hash_string );

}


/**
 * Parse the contact form fields and generate a list of data I can more easily
 * consume.
 *
 * @param array<mixed> $blocks List of inner blocks to parse.
 * @return array<mixed>
 */
function toolbelt_contact_parse_fields( $blocks ) {

	$fields = array();

	/**
	 * Return cos there's no fields to look at.
	 */
	if ( empty( $blocks['innerBlocks'] ) ) {
		return $fields;
	}

	foreach ( $blocks['innerBlocks'] as $block ) {

		$atts = shortcode_atts(
			array(
				'label' => '',
				'options' => array(),
			),
			$block['attrs']
		);

		// Get the field type.
		$type = str_replace( 'toolbelt/field-', '', $block['blockName'] );

		/**
		 * Get the field label.
		 * Get the default first, and then replace with the custom value if set.
		 */
		$default_props = toolbelt_contact_field_defaults( $type );
		$label = '';
		if ( ! empty( $default_props['label'] ) ) {
			$label = $default_props['label'];
		}
		if ( ! empty( $atts['label'] ) ) {
			$label = $atts['label'];
		}

		/**
		 * Ignore fields without a label.
		 */
		if ( ! empty( $label ) || empty( $type ) ) {

			$name = toolbelt_contact_get_field_name( $label );

			if ( 'checkbox-multiple' === $type ) {
				$value = filter_input( INPUT_POST, $name, FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );
			} else {
				$value = filter_input( INPUT_POST, $name );
			}

			$fields[ $name ] = array(
				'label' => $label,
				'options' => $atts['options'],
				'type' => $type,
				'value' => $value,
			);

		}
	}

	return $fields;

}


/**
 * Generate a unique, reusable, field name for the current field.
 *
 * @param string $label The label ised for the current field.
 * @return string
 */
function toolbelt_contact_get_field_name( $label ) {

	return 'tbc-' . sanitize_title( $label );

}


/**
 * Sanitize the data input before sending it.
 *
 * @param array<mixed> $data The data object for the sanitized value.
 * @return string
 */
function toolbelt_contact_sanitize( $data ) {

	$value = $data['value'];

	switch ( $data['type'] ) {

		case 'text':
			$value = wp_kses_post( $value );
			break;

		case 'url':
			$value = esc_url( $value );
			break;

		case 'checkbox':
			$value = esc_html( $value );

			if ( 'on' === $value ) {
				$value = esc_html__( 'Yes', 'wp-toolbelt' );
			} else {
				$value = esc_html__( 'No', 'wp-toolbelt' );
			}
			break;

		case 'textarea':
			$value = wp_kses_post( $value );
			$value = nl2br( $value );

			break;

		case 'checkbox-multiple':
			/**
			 * For the multiple checkbox list we will loop through all
			 * checkboxes and display whether they were selected or not.
			 */
			$html = array();
			foreach ( $data['options'] as $option ) {
				$html[] = sprintf(
					'<strong>%1$s:</strong> %2$s',
					esc_html( $option ),
					in_array( sanitize_title( $option ), $value, true ) ? esc_html__( 'Yes', 'wp-toolbelt' ) : esc_html__( 'No', 'wp-toolbelt' )
				);
			}

			$value = implode( '<br />', $html );

			break;

		case 'radio':
		case 'select':
			/**
			 * For select and radio options we need to loop through the
			 * available options to find the one that was selected.
			 */
			foreach ( $data['options'] as $option ) {
				if ( sanitize_title( $option ) === $value ) {
					$value = esc_html( $option );
				}
			}

			break;

		default:
			$value = esc_html( $value );
			break;

	}

	if ( empty( $value ) ) {
		$value = esc_html__( 'No value supplied', 'wp-toolbelt' );
	}

	return $value;

}


/**
 * The post type is registered on init (priority 11) so this needs to be called
 * after since it tries to load the post taxonomies.
 *
 * @return void
 */
add_action( 'init', 'toolbelt_contact_form_register_block', 12 );

toolbelt_register_block_category();


/**
 * Display the contact form styles on the front end.
 *
 * @return void
 */
function toolbelt_contact_form_styles() {

	if ( ! has_block( 'toolbelt/contact-form' ) ) {
		return;
	}

	toolbelt_styles( 'contact-form' );
	toolbelt_global_script( 'bouncer.polyfills.min' );

}

add_action( 'wp_print_styles', 'toolbelt_contact_form_styles' );


/**
 * Display the contact form script on the front end.
 *
 * @return void
 */
function toolbelt_contact_form_script() {

	if ( ! has_block( 'toolbelt/contact-form' ) ) {
		return;
	}

	toolbelt_scripts( 'contact-form' );

}

add_action( 'wp_footer', 'toolbelt_contact_form_script' );


require 'module-fields.php';

require 'module-cpt.php';

/**
 * Only include in the admin.
 * We don't want regular site visitors using this.
 */
if ( is_admin() ) {
	require 'module-ajax.php';
}