<?php
function wo_admin_edit_client_page() {
	if ( ! isset( $_REQUEST['id'] ) ) {
		return;
	}
	
	$message = null;
	if ( isset( $_POST['edit_client'] ) && wp_verify_nonce( $_POST['nonce'], 'edit_client_' . $_POST['edit_client'] ) ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		
		$update_client = wo_update_client( $_POST );
		$message       = __( 'Client Updated', 'wp-oauth' );
	}
	
	$client = wo_get_client( intval( $_REQUEST['id'] ) );
	if ( ! $client ) {
		exit( 'Client not found' );
	}
	
    wp_enqueue_style( 'wo_admin' );
    wp_enqueue_script( 'wo_admin' );
    wp_enqueue_script( 'wo_admin_select2' );
    wp_enqueue_style( 'wo_admin_select2' );
	?>
    <style>
        .content {
            display: flex;
            align-items: center;
        }

        .content label {
            font-weight: normal !important;
        }
    </style>
    <div class="wrap">

        <h2><?php _e( 'Edit Client', 'wp-oauth' ); ?>
            <small>( id: <?php echo esc_html( $client->ID ); ?> )</small>
            <a class="add-new-h2 "
               href="<?php echo admin_url( 'admin.php?page=wo_manage_clients' ); ?>"
               title="Batch"><?php _e( 'Back to SSO Integration', 'wp-oauth' ); ?></a>
        </h2>

        <hr/>
		
		<?php if ( ! is_null( $message ) ) : ?>
            <div class="notice notice-success is-dismissible">
                <p><?php echo esc_html( $message ); ?></p>
            </div>
		<?php endif; ?>

        <form class="wo-form" action="" method="post">
			
			<?php wp_nonce_field( 'edit_client_' . $client->ID, 'nonce' ); ?>
            <input type="hidden" name="edit_client" value="<?php echo esc_attr( $client->ID ); ?>"/>
            <input type="hidden" name="post_id" value="<?php echo esc_attr( $client->ID ); ?>"/>

            <div class="section group">

                <div class="col span_2_of_6">

                    <label class="checkbox-grid"> Allowed Grant Types
						<?php
						_e(
							'Choosing the correct grant type for your client is important. For security reasons, a single
							grant type should be used per client. To learn more about which grant type you will need,
							please visit <a href="https://wp-oauth.com/docs/general/grant-types/?utm_source=plugin-admin&utm_medium=settings-page"
							                title="Learn more about which grant type to use" target="_blank">
							                https://wp-oauth.com/docs/general/grant-types/
							                </a>.',
							'wp-oauth'
						);
						?>

                        </p>
                        <hr/>

                        <label> <strong>Authorization Code</strong>
                            <input type="checkbox" name="grant_types[]"
                                   value="authorization_code"
								<?php
								if ( in_array( 'authorization_code', $client->grant_types ) ) {
									echo ' checked';
								}
								?>
                            />
                            <small class="description">
                                Allows authorization code grant type for this client. This includes the implicit method.
                            </small>
                        </label>
                    </label>
                </div>

                <div class="col span_4_of_6">
                    <div class="wo-background">

                        <h3><?php _e( 'Client Information', 'wp-oauth' ); ?></h3>
                        <hr/>

                        <div class="section group">
                        <div class="col span_6_of_6">
                            <label> Client Name<p class="description">Enter a uniquely identifiable name for the SSO</p>
                                <input class="emuv-input" type="text" name="name"
                                value="<?php echo esc_attr($client->post_title); ?>" required/>
                            </label>

                            <label> Redirect URI<p class="description">Copy this from Freshworks SSO policy setup page</p>
                                <input class="emuv-input" type="text" name="redirect_uri"
                                value="<?php echo esc_attr(get_post_meta( $client->ID, 'redirect_uri', true )); ?>"/>
                            </label>

                            <hr/>
                            <div style="margin-top: 2.5em" class="advanced-options">
                                <h3>Map information to Freshworks SSO<p class="description">Copy the below information to your Freshworks SSO setup page and map to the relevant fields</p></h3>
                                <hr/>
                                <div class="container">
                                    <label> Client ID</label>
                                    <div class="content">
                                        <input style="display: none;" class="emuv-input" type="text" name="client_id" value="<?php echo esc_attr(get_post_meta( $client->ID, 'client_id', true )); ?>" readonly/>
                                        <label style="width: 93%" id="client_id" class="emuv-input"><?php echo esc_attr(get_post_meta( $client->ID, 'client_id', true )); ?></label>
                                        <button style="vertical-align: unset" class="dashicons-before dashicons-admin-page button" type="button" onclick="copy('client_id')" title="Copy"></button>                                
                                    </div>
                                </div>

                                <div class="container">
                                    <label> Client Secret</label>
                                    <div class="content">
                                        <input style="display: none;" class="emuv-input" type="text" name="client_secret" value="<?php echo esc_attr(get_post_meta( $client->ID, 'client_secret', true )); ?>" readonly/>
                                        <label style="width: 93%" id="client_secret" class="emuv-input"><?php echo esc_attr(get_post_meta( $client->ID, 'client_secret', true )); ?></label>
                                        <button style="vertical-align: unset" class="dashicons-before dashicons-admin-page button" type="button" onclick="copy('client_secret')" title="Copy"></button>
                                    </div>
                                </div>
                                <div class="container">
                                    <label> Authorization URL</label>
                                    <div class="content">
                                        <label style="width: 93%" id="authorization_url" class="emuv-input"><?php echo esc_attr(home_url('/oauth/authorize/')); ?></label>
                                        <button style="vertical-align: unset" class="dashicons-before dashicons-admin-page button" type="button" onclick="copy('authorization_url')" title="Copy"></button>
                                    </div>
                                </div>
                                <div class="container">
                                    <label> Access token URL</label>
                                    <div class="content">
                                        <label style="width: 93%" id="access_token_url" class="emuv-input"><?php echo esc_attr(home_url('/oauth/token/')); ?></label>
                                        <button style="vertical-align: unset" class="dashicons-before dashicons-admin-page button" type="button" onclick="copy('access_token_url')" title="Copy"></button>
                                    </div>
                                </div>
                                <?php
                                    $Redirect_URI = get_post_meta( $client->ID, 'redirect_uri', true );
                                    $Redirect_Parse_URL = parse_url($Redirect_URI);
                                    $Redirect_URL = $Redirect_Parse_URL['scheme']."://".$Redirect_Parse_URL['host'];
                                ?>
                                <div class="container">
                                    <label> Logout URL</label>
                                    <div class="content">
                                        <label style="width: 93%" id="logout_url" class="emuv-input"><?php echo esc_attr(home_url("/oauth/destroy/?post_logout_redirect_uri=$Redirect_URL")); ?></label>
                                        <button style="vertical-align: unset" class="dashicons-before dashicons-admin-page button" type="button" onclick="copy('logout_url')" title="Copy"></button>
                                    </div>
                                </div>
                                <div class="container">
                                    <label> User Info URL</label>
                                    <div class="content">
                                        <label style="width: 93%" id="user_info_url" class="emuv-input"><?php echo esc_attr(site_url('/oauth/me/')); ?></label>
                                        <button style="vertical-align: unset" class="dashicons-before dashicons-admin-page button" type="button" onclick="copy('user_info_url')" title="Copy"></button>
                                    </div>
                                </div>
                            </div>
                            <hr/>
                            <div style="margin-top: 2.5em" class="advanced-options">
                                <h3>Advanced Options</h3>
                                <hr/>                                
                                <label> Client Scope(s)
                                    <p class="description">
                                        Scopes can be assigned to restrict scopes. This value will also act as the
                                        default scope for this client. If you leave this field blank, the default
                                        scope will be <strong>"basic"</strong> and the client will have access to
                                        all available scopes. If you have multiple scopes, please separate with a
                                        single space.
                                    </p>
                                    <input class="emuv-input" type="text" name="scope"
                                    value="<?php echo esc_attr(get_post_meta( $client->ID, 'scope', true )); ?>"
                                    placeholder="basic"/>
                                </label>
                            </div>
                            <hr/>
                        </div>
                        </div>
                    </div>
                </div>
            </div>
			
			<?php submit_button( __( 'Update Client', 'wp-oauth' ) ); ?>

        </form>

    </div>
	<script type="text/javascript">
        function copy(id){
            // Select the content of the specified label
            var content = jQuery("#" + id).text();

            // Create a temporary textarea element to copy the content
            var textarea = jQuery("<textarea>")
                .css({
                    position: "fixed",
                    opacity: 0
                })
                .text(content)
                .appendTo("body");

            // Select the text in the textarea
            textarea[0].select();
            textarea[0].setSelectionRange(0, content.length);

            // Copy the selected text to clipboard
            document.execCommand("copy");

            // Remove the temporary textarea
            textarea.remove();
        }
    </script>
	<?php
}
