<?php
/**
 * The Template for joining meeting via browser
 *
 * This template can be overridden by copying it to yourtheme/video-conferencing-zoom/join-web-browser.php.
 *
 * @package    Video Conferencing with Zoom API/Templates
 * @version     3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

global $zoom;

if ( video_conference_zoom_check_login() === "no_check" || video_conference_zoom_check_login() === "loggedin" ) {
	?>
    <div id="dpen-zoom-browser-meeting" class="dpen-zoom-browser-meeting-wrapper">
        <div id="dpen-zoom-browser-meeting--container">
            <div class="dpen-zoom-browser-meeting--info"></div>
            <form class="dpen-zoom-browser-meeting--meeting-form" id="dpen-zoom-browser-meeting-join-form" action="">
                <div class="form-group">
                    <input type="text" name="display_name" id="display_name" value="" placeholder="Name" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary" id="dpen-zoom-browser-meeting-join-mtg" data-meeting="<?php echo absint( $zoom['meeting_id'] ); ?>">
					<?php _e( 'Join', 'video-conferencing-with-zoom-api' ); ?>
                </button>
            </form>
        </div>
    </div>
	<?php
	wp_footer();
} else {
	echo "<h3>" . __( 'You do not have enough priviledge to access this page. Please login to continue or contact administrator.', 'video-conferencing-with-zoom-api' ) . "</h3>";
	die;
}
