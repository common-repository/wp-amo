<?php
namespace ArcStone\AMO;
//AMO MEMBER CENTER SHORTCODE

function amo_shortcode_member_center( $atts ){

	$amo_attribute = shortcode_atts( array(
        'amo_method' => 'AMOAssociation',
		'amo_api_url' => (get_option('amo_api_url') == '') ? "" : get_option('amo_api_url'),
        'amo_api_key' => (get_option('amo_api_key') == '') ? "" : get_option('amo_api_key'),
		'width' => '100%',
		'height' => '1000',
    ), $atts );

	$css_classes = array();
	$results_display = '';
	$api = new API ( AMO_API_KEY );
	$css_classes = array();	
	$website_url = get_option( 'wpamo_website_url' );
		
	$results = $api->processRequest( 'AMOAssociation' );

	
	if ( !is_user_logged_in()) {
		$results_display = 'Please <a href="https://' .$website_url. '/site_signin_wp.cfm?wp_uri='.site_url().'&wp_return='.get_permalink().'">log in</a> to access the Member Center!.';
	}
	else {
		if(empty($results)){
			$css_classes = array( 'amo-error' );
			$results_display = 'There Has Been An Issue';
		} else {
			foreach ($results as $results) {
    		$user_id = get_current_user_id(); 
            $key = 'amo_pk_association_individual'; 
            $single = true; 
            $pk_individual = get_user_meta( $user_id, $key, $single );	
    		$params = array(
						'pk_association_individual'	=>	$pk_individual,
						);
			
			$results_display .= '<iframe id="amo_member_center-iframe" src="' . esc_url($results['member_center_url']) . '" width="' . esc_attr( $amo_attribute['width'] ) . '" height="'  . esc_attr( $amo_attribute['height'] ) . '" frameborder="0" scrolling="yes"></iframe>';
			
}
		}
	}

	return AMODiv::do_output( $results_display, 'amo_member_center', $css_classes );	
}

add_shortcode( 'amo_member_center', __NAMESPACE__ . '\\amo_shortcode_member_center' );
?>