<?php

/**
 * Class for displaying addons page
 *
 * @since 3.1.1
 */
class Zoom_Video_Conferencing_Admin_Addons {

	public function __construct() {
	}

	public static function render() {
		?>
        <div class="video-conferencing-addons wrap">
            <h1 class="border-padd">Get more features to your Meetings !</h1>
            <div class="video-conferencing-addons-flex">
                <div class="video-conferencing-addons-box">
                    <div class="content">
                        <h2 style="line-height: 1.6;">WooCommerce Integration</h2>
                        <p>Integrate your Zoom Meetings directly to WooCommerce products. This integration allows you to seamlessly integrate your
                            WooCommerce product into a Zoom Meeting post created from your WordPress dashboard. To make zoom meeting a purchasable
                            product. You'll find an option in your <strong>Zoom Meeting > Add New page</strong>. Checking the option would convert
                            your normal meeting to purchasable meeting page.</p>
                        <a href="https://www.codemanas.com/downloads/zoom-meetings-for-woocommerce/" class="button button-primary">From $34</a>
                    </div>
                </div>
                <div class="video-conferencing-addons-box">
                    <div class="content">
                        <h2 style="line-height: 1.6;">WooCommerce Booking Integration</h2>
                        <p>Integrate your Zoom Meetings directly to WooCommerce booking products. Zoom Integration for WooCommerce Booking allows you
                            to automate your zoom meetings directly from your WordPress dashboard by linking zoom meetings to your WooCommerce Booking
                            products automatically when a Booking Order is created. Users will receive join links in their booking confirmation
                            emails.</p>
                        <p><strong>This plugin includes WooCommerce Bookings as well as integration with only WooCommerce also as a bundle.</strong>
                        </p>
                        <a href="https://www.codemanas.com/downloads/zoom-integration-for-woocommerce-booking/" class="button button-primary">From
                            $60</a>
                    </div>
                </div>
            </div>
        </div>
		<?php
	}
}