<?php
/**
 * My Account navigation
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/myaccount/navigation.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 9.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

do_action( 'woocommerce_before_account_navigation' );
?>

<nav class="woocommerce-MyAccount-navigation" aria-label="<?php esc_html_e( 'Account pages', 'woocommerce' ); ?>">
	<ul>
		<?php foreach ( wc_get_account_menu_items() as $endpoint => $label ) : ?>
			<li class="<?php echo wc_get_account_menu_item_classes( $endpoint ); ?>">
				<a href="<?php echo esc_url( wc_get_account_endpoint_url( $endpoint ) ); ?>" <?php echo wc_is_current_account_menu_item( $endpoint ) ? 'aria-current="page"' : ''; ?>>
					<?php
						if( 'dashboard' == $endpoint ){
							echo '<svg height="24" width="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><defs><style>.a{fill:none;stroke:#000;stroke-linecap:round;stroke-miterlimit:10}</style></defs><circle cx="12" cy="12" r="11.5" class="a"/><path fill="none" stroke="#000" stroke-linecap="round" stroke-linejoin="round" d="M13.414 13.414a2 2 0 01-2.828-2.828c.781-.781 8.132-5.3 8.132-5.3s-4.518 7.347-5.304 8.128z"/><path d="M3.5 12H5m.99-6.01l1.06 1.06M12 3.5V5m8.5 7H19m1.633 7.6A14.708 14.708 0 0012 17a14.708 14.708 0 00-8.633 2.6" class="a"/></svg>';

						} else if( 'orders' == $endpoint ){
							echo '<svg height="24" width="24" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg"><g data-name="Layer 24"><path d="M60.92 40.79L55.25 27a1 1 0 00-.92-.62H43.82a1 1 0 00-1 1V45.6h-2.39V19.17a1 1 0 00-1-1h-5a12.73 12.73 0 00-25.43 0H4a1 1 0 00-1 1V51a1 1 0 001 1h2.68a5.44 5.44 0 0010.7 0h2.39a5.44 5.44 0 0010.7 0h16.18a5.44 5.44 0 0010.7 0H60a1 1 0 001-1v-9.83a1 1 0 00-.08-.38zm-2.41-.62h-7V28.38h2.2zM42.82 50H30.47a5.48 5.48 0 00-1.08-2.36h13.43zm-23 0h-2.44a5.47 5.47 0 00-1.07-2.36h4.53A5.47 5.47 0 0019.77 50zm1.89-41.58A10.75 10.75 0 1111 19.17 10.76 10.76 0 0121.71 8.42zM5 20.17h4a12.73 12.73 0 0025.39 0h4v25.34H5zM5 47.6h2.76A5.48 5.48 0 006.68 50H5zm7 6.8a3.44 3.44 0 01-.77-6.8h1.57a3.44 3.44 0 01-.8 6.8zm13.08 0a3.44 3.44 0 01-.77-6.8h1.54a3.44 3.44 0 01-.77 6.8zm26.92 0a3.45 3.45 0 113.45-3.4A3.45 3.45 0 0152 54.4zm5.35-4.4a5.44 5.44 0 00-10.7 0h-1.83V28.38h4.64v12.79a1 1 0 001 1H59V50z"/><path d="M19 24.41a1 1 0 00.76.35 1 1 0 00.77-.4l7-9.18a1 1 0 10-1.6-1.2l-6.2 8.18-3.25-3.82A1 1 0 1015 19.63z"/></g></svg>';

						} else if( 'downloads' == $endpoint ){
							echo '<svg height="24" width="24" viewBox="0 0 50 50" xmlns="http://www.w3.org/2000/svg"><path fill="none" d="M0 0h50v50H0z"/><path fill="none" stroke="#000" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10" stroke-width="2" d="M32 35h9.098a7.902 7.902 0 100-15.803c-.02 0-.038.003-.058.003.061-.494.103-.994.103-1.504 0-6.71-5.439-12.15-12.15-12.15-5.229 0-9.672 3.309-11.386 7.941a6.008 6.008 0 00-10.26 4.244c0 .085.01.167.013.251C3.695 18.995 1 22.344 1 26.331A8.669 8.669 0 009.67 35H18"/><path fill="none" stroke="#000" stroke-linecap="round" stroke-miterlimit="10" stroke-width="2" d="M30 41l-5 5-5-5m5-15v19.668"/></svg>';

						} else if( 'edit-address' == $endpoint ){
							echo '<svg height="24" width="24" fill="none" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg"><path fill="#000" d="M56.82 23.14a1 1 0 001-1V14a1 1 0 00-.67-.95L52 11.29V8a5 5 0 00-5-5H11.18a5 5 0 00-5 5v48a5 5 0 005 5H47a5 5 0 005-5v-8.23h4.78a1 1 0 001-1v-8.15a1 1 0 00-.67-.94L52 35.91v-.46h4.78a1 1 0 001-1v-8.14a1 1 0 00-.67-.94L52 23.6v-.46h4.82zm-1-8.43v6.43H52V13.4l3.82 1.31zM50 56a3 3 0 01-3 3H11.18a3 3 0 01-3-3V8a3 3 0 013-3H47a3 3 0 013 3v48zm5.78-16.67v6.44H52V38l3.78 1.33zm0-12.31v6.43H52v-7.74l3.78 1.31z"/><path fill="#000" d="M18.17 31.55a14.682 14.682 0 0021.92-.05 1 1 0 00.23-.9A11.52 11.52 0 0033 22.45a6.39 6.39 0 10-7.78 0 11.48 11.48 0 00-7.3 8.05 1 1 0 00.25 1.05zm6.54-14.16a4.4 4.4 0 114.42 4.39 4.4 4.4 0 01-4.42-4.39zm4.37 6.39h.06a9.53 9.53 0 019.1 6.78 12.68 12.68 0 01-18.26 0 9.51 9.51 0 019.1-6.78zm-7.52 18.11a1 1 0 100 2h15.09a1 1 0 100-2H21.56zm22.12 7.18H14.54a1 1 0 000 2h29.14a1 1 0 100-2z"/></svg>';

						} else if( 'edit-account' == $endpoint ){
							echo '<svg width="24" height="24" fill="none" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg"><path fill="#000" d="M32 2.75A29.25 29.25 0 1061.25 32 29.28 29.28 0 0032 2.75zm0 2.5a26.73 26.73 0 0120.93 43.38 22.76 22.76 0 00-15.12-12.94 10.59 10.59 0 10-11.62 0 22.76 22.76 0 00-15.12 12.94A26.73 26.73 0 0132 5.25zm0 29.68a8.09 8.09 0 118.09-8.09A8.1 8.1 0 0132 34.93zm0 23.82a26.651 26.651 0 01-19.07-8 20.32 20.32 0 0138.14 0 26.652 26.652 0 01-19.07 8z"/></svg>';

						} else if( 'customer-logout' == $endpoint ){
							echo '<svg height="24" width="24" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg"><g data-name="Layer 77"><path d="M40.36 9.8a2 2 0 00-1.24 3.8C63.48 22 57.79 57.55 32 58a22.77 22.77 0 01-7.12-44.4 2 2 0 10-1.24-3.8A26.77 26.77 0 0032 62c30.33-.54 37-42.27 8.36-52.2z"/><path d="M32 25.06a2 2 0 002-2V4a2 2 0 00-4 0v19.06a2 2 0 002 2z"/></g></svg>';
						}

						echo esc_html( $label ); 
					?>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</nav>

<?php do_action( 'woocommerce_after_account_navigation' ); ?>
