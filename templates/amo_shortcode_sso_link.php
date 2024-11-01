<?php
namespace ArcStone\AMO;
//AMO LOGIN LINK SHORTCODE

function amo_shortcode_sso_link( $atts ){

	$amo_attribute = shortcode_atts( array(
										'login_text_value' => 'Log In',
										'logout_text_value' => 'Log Out',
										'after_login_url' => '',
									), $atts );
									
									
	$website_url = get_option( 'wpamo_website_url' );
	
	if ($amo_attribute['after_login_url'] != '') {
		$after_login_url = site_url( esc_url( $atts['after_login_url'] ));
		
	}
	else {
		//$after_login_url = get_permalink();
		$after_login_url = get_query_var('amo_redirect_to');
	}
								

	$css_classes = array();
	if ( !is_user_logged_in()) {
		$results_display = '<a href="https://' .$website_url. '/site_signin_wp.cfm?wp_uri='.site_url().'&wp_return='.$after_login_url.'" class="amo-sso-link">'.$amo_attribute['login_text_value'].'</a>';
	}
	else {
		$results_display = '<a href="'.wp_logout_url().'" class="amo-sso-link">'.$amo_attribute['logout_text_value'].'</a>';
	}

	return $results_display;
}

	add_shortcode( 'amo_sso_link', __NAMESPACE__ . '\\amo_shortcode_sso_link' );
?>
