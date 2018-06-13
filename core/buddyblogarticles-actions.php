<?php
/**
 * Action handler.
 *
 * @package buddyblog
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles various BuddyBlog Actions
 */
class BuddyBlogPhotos_Actions {

	/**
	 * Class instance.
	 *
	 * @var BuddyBlog_Actions
	 */
	private static $instance = null;

	/**
	 * Constructor.
	 */
	private function __construct() {

		add_action( 'bp_init', array( $this, 'register_form' ), 7 );
		add_action( 'bp_actions', array( $this, 'publish' ) );
		add_action( 'bp_actions', array( $this, 'unpublish' ) );
		add_action( 'bp_actions', array( $this, 'delete' ) );
	}

	/**
	 * Get Singleton Instance
	 *
	 * @return BuddyBlog_Actions
	 */
	public static function get_instance() {

		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Create Posts screen
	 */
	public function create() {
		// No Need to implement it, BP Simple FEEditor takes care of this.
	}

	/**
	 * Edit Posts screen
	 */
	public function edit() {
		// No Need to implement it, BP Simple FEEditor takes care of this.
	}

	/**
	 * Delete Post screen
	 */
	public function delete() {

		if ( ! ( bp_is_buddyblogphotos_component() && bp_is_current_action( 'delete' ) ) ) {
			return;
		}

		$post_id = bp_action_variable( 0 );

		if ( ! $post_id ) {
			return;
		}

		if ( buddyblogphotos_user_can_delete( $post_id, get_current_user_id() ) ) {

			wp_delete_post( $post_id, true );
			bp_core_add_message( __( 'Post deleted successfully' ), 'buddyblogphotos' );
			// redirect.
			wp_redirect( buddyblogphotos_get_home_url() ); // hardcoding bad.
			exit( 0 );

		} else {
			bp_core_add_message( __( 'You should not perform unauthorized actions', 'buddyblogphotos' ), 'error' );
		}

	}

	/**
	 * Publish Post
	 */
	public function publish() {

		if ( ! ( bp_is_buddyblogphotos_component() && bp_is_current_action( 'publish' ) ) ) {
			return;
		}

		$id = bp_action_variable( 0 );

		if ( ! $id ) {
			return;
		}

		if ( buddyblogphotos_user_can_publish( get_current_user_id(), $id ) ) {

			wp_publish_post( $id );// change status to publish.
			bp_core_add_message( __( 'Post Published', 'buddyblogphotos' ) );
		}

		bp_core_redirect( buddyblogphotos_get_home_url() );
	}

	/**
	 * Unpublish a post
	 */
	public function unpublish() {

		if ( ! ( bp_is_buddyblogphotos_component() && bp_is_current_action( 'unpublish' ) ) ) {
			return;
		}

		$id = bp_action_variable( 0 );

		if ( ! $id ) {
			return;
		}

		if ( buddyblogphotos_user_can_unpublish( get_current_user_id(), $id ) ) {

			$post                = get_post( $id, ARRAY_A );
			$post['post_status'] = 'draft';
			wp_update_post( $post );
			// unpublish.
			bp_core_add_message( __( 'Post unpublished', 'buddyblogphotos' ) );

		}

		bp_core_redirect( buddyblogphotos_get_home_url() );

	}

	/**
	 * This gets called when a post is saved/updated in the database
	 * after create/edit action handled by BP simple front end post plugin
	 *
	 * @param int                      $post_id post id.
	 * @param boolean                  $is_new is new post.
	 * @param BPSimpleBlogPostEditForm $form_object form.
	 */
	public function on_save( $post_id, $is_new, $form_object ) {

		$post_redirect = buddyblogphotos_get_option( 'post_update_redirect' );

		$url = '';

		if ( 'archive' == $post_redirect ) {
			$url = buddyblogphotos_get_home_url();
		} elseif ( $post_redirect == 'single' && get_post_status( $post_id ) == 'publish' ) {
			// go to single post.
			$url = get_permalink( $post_id );
		}

		if ( $url ) {
			bp_core_redirect( $url );
		}
	}

	/**
	 * Register post form for Posting/editing
	 *
	 * @return null
	 */
	public function register_form() {

		// make sure the Front end simple post plugin is active.
		if ( ! function_exists( 'bp_new_simple_blog_post_form' ) ) {
			return;
		}

		$post_status = buddyblogphotos_get_option( 'post_status' );
		$user_id     = get_current_user_id();

		if ( ! buddyblogphotos_user_can_post( $user_id ) ) {
			$post_status = 'draft';
		}

		$settings = array(
			'post_type'             => buddyblogphotos_get_posttype(),
			'post_status'           => $post_status,
			'comment_status'        => buddyblogphotos_get_option('show_comment_option') ? 'closed' : buddyblogphotos_get_option( 'comment_status' ),
			'show_comment_option'   => buddyblogphotos_get_option( 'show_comment_option' ),
			'custom_field_title'    => '', // we are only using it for hidden field, so no need to show it.
			'custom_fields'         => array(
				'_is_buddyblogphotos_post' => array(
					'type'    => 'hidden',
					'label'   => '',
					'default' => 1,
				),
			),
			'allow_upload'          => buddyblogphotos_get_option( 'allow_upload' ),
			'upload_count'          => 0,
			'has_post_thumbnail'    => 1,
			'current_user_can_post' => current_user_can( buddyblogphotos_get_option( 'post_cap' ) ),
			'update_callback'       => array( $this, 'on_save' ),
		);

		if ( buddyblogphotos_get_option( 'enable_taxonomy' ) ) {

			$taxonomies = array();
			$tax        = buddyblogphotos_get_option( 'allowed_taxonomies' );

			if ( ! empty( $tax ) ) {

				foreach ( (array) $tax as $tax_name ) {
					$view = 'checkbox';
					// is_taxonomy_hierarchical($tax_name);

					$taxonomies[ $tax_name ] = array(
						'taxonomy'  => $tax_name,
						'view_type' => 'checkbox', // currently only checkbox.
					);

				}
			}

			if ( ! empty( $taxonomies ) ) {
				$settings['tax'] = $taxonomies;
			}
		}

		// use it to add extra fields or filter the post type etc.
		$settings = apply_filters( 'buddyblogphotos_post_form_settings', $settings );

		bp_new_simple_blog_post_form( 'buddyblogphotos-user-posts', $settings ); // the blog form.

	}
}

// instantiate.
BuddyBlogPhotos_Actions::get_instance();
