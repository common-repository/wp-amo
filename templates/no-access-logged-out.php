<h4><?php echo __('You need to be logged in to access this content.') ?></h4>


<?php	
$website_url = get_option( 'wpamo_website_url' );

echo '<a href="https://' .$website_url. '/site_signin_wp.cfm?wp_uri='.site_url().'&wp_return='.get_permalink().'">Login Here</a>' 
?>

