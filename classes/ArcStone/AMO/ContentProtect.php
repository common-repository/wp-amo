<?php
namespace ArcStone\AMO;
use \WP;

class ContentProtect {

	private $available_roles = array();

	public function __construct() {

		if ( is_admin() ) {
			$wp_roles = new \WP_Roles();
    		$this->available_roles = $wp_roles->roles;
			add_action( 'cmb2_admin_init', array( $this, 'metabox' ) );
		}

        add_action( 'template_redirect', array($this, 'protect') );
	}

	public function protect() {
		$can_view = false;
		$post_id = get_the_ID();

		$protect_roles_selected = get_post_meta( $post_id, 'amo_content_protect_roles_selected', true);
		$can_view = $this->can_user_view_content( get_current_user_id(), $post_id, $protect_roles_selected);

        $is_user_logged_in = is_user_logged_in();
        $no_access_logged_out = cmb2_get_option('amo_options', 'no-access-logged-out');
        $no_access_logged_in = cmb2_get_option('amo_options', 'no-access-logged-in');
        $url = get_permalink();

        if($post_id == $no_access_logged_out) {
            return false;
        }

        if(empty($protect_roles_selected)) {
        	return false;
        }

        if($can_view) {
        	return false;
        }

        if ( $is_user_logged_in && $no_access_logged_in ) {
            wp_redirect(get_permalink($no_access_logged_in) . '?amo_redirect_to=' . $url);
            exit;
        } elseif ( $is_user_logged_in && !$no_access_logged_in ) {
            wp_redirect(get_permalink($no_access_logged_in) . '?amo_redirect_to=' . $url);
            exit;
        } elseif ( !$is_user_logged_in && $no_access_logged_out ) {
            wp_redirect(get_permalink($no_access_logged_out) . '?amo_redirect_to=' . $url);
            exit;
        }

        wp_redirect(get_permalink($no_access_logged_out) . '?amo_redirect_to=' . $url);
        exit;
	}


	/* Filtering Post Thumbnail*/
	function filter_post_thumbnail( $has_thumbnail, $post, $thumbnail_id ) {
		$has_thumbnail = false;
		$post_id = get_the_ID();
		$protect_roles_selected = get_post_meta( $post_id, 'amo_content_protect_roles_selected', true);

		if ( empty( $protect_roles_selected ) ) {
			$has_thumbnail = true;
			return $has_thumbnail;
		} else {

			$has_thumbnail = false;

			$can_view = $this->can_user_view_content( get_current_user_id(), $post_id, $protect_roles_selected);

			/** User _can_ view the page, show it */
			if ( $can_view ) {
				$has_thumbnail = true;
				return $has_thumbnail;

			} else {

				$is_user_logged_in = is_user_logged_in();
				$no_access_logged_out = cmb2_get_option('amo_options', 'no-access-logged-out');
				$no_access_logged_in = cmb2_get_option('amo_options', 'no-access-logged-in');

				if ($is_user_logged_in && $no_access_logged_in ) {
					$has_thumbnail = false;
					return $has_thumbnail;
				} elseif ( $is_user_logged_in && !$no_access_logged_in ) {
					$has_thumbnail = false;
					return $has_thumbnail;
				} elseif ( !$is_user_logged_in && $no_access_logged_out ) {
					$has_thumbnail = false;
					return $has_thumbnail;
				} else {
					$has_thumbnail = false;
					return $has_thumbnail;
				}
			}

			$has_thumbnail = apply_filters( 'um_restrict_post_thumbnail', $has_thumbnail, $post, $thumbnail_id );
			return $has_thumbnail;
		}
	}

	/**
	 * This function protects the edit link so that users without permission do not accidentally see it
	 */
	public function protect_edit_link( $content ) {
		$can_view = $this->can_user_view_content();
		if ( $can_view ) {
			return $content;
		} else {
			return '';
		}
	}

	public function metabox() {

		$args = array( 'public' => true );
		$public_post_types = get_post_types( $args );

		$role_options = $this->convert_roles_to_options( $this->available_roles );

		$prefix = 'amo_content_protect_';
		$cmb = \new_cmb2_box( array(
							'id'			=>	$prefix . 'metabox',
							'title'			=>	'Content Restriction',
							'object_types'	=>	$public_post_types,
							'context'		=>	'normal',
							'priority'		=>	'high',
							'show_names'	=>	'true'
							)
		);


		$cmb->add_field( array(
							'name'		=>	'Restrict Content',
							'desc'		=>	'Only selected roles will have access to the content. If none selected, content will be public.',
							'id'		=>	$prefix . 'roles_selected',
							'type'		=>	'multicheck',
							'options'	=>	$this->convert_roles_to_options( $this->available_roles )
						));
	}

	private function convert_roles_to_options( $roles ) {

		$options = array();

		if ( $roles ) {
			foreach ( $roles as $role => $details ) {
				$options[$role] = $details['name'];
			}
		}

		return $options;
	}

	private function can_user_view_content( $user_id = '', $post_id = '', $roles = array() ) {

		if ( !$user_id ) {
			$user_id = get_current_user_id();
		}

		if ( !$post_id ) {
			$post_id = \get_the_ID();
		}

		if ( empty( $roles ) ) {
			$roles = get_post_meta( $post_id, 'amo_content_protect_roles_selected', true);
		}

		// the post is restricted
		if ( is_array( $roles ) ) {

			$post = get_post( $post_id );

			// if it's a feed or user is not loggedin, never viewable
			if ( is_feed() || !is_user_logged_in() ) {
				return false;

			// if is post's author or user can view restricted content, always viewable
			} elseif ( $post->post_author == $user_id || user_can( $user_id, 'restrict_content' ) ) {
				return true;

			} else {

				$can_view = false;
				$user = new \WP_User( $user_id );

				// does user have role
				foreach( $roles as $role ) {
					if ( in_array($role, $user->roles ) ){
						$can_view = true;
					}
				}

				return $can_view;
			}


		// if no roles set, content is viewable by anyone
		} else {
			return true;
		}

	}
}
