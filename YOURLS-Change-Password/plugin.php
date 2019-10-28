<?php
/*
Plugin Name: Change Password
Plugin URI: https://github.com/vvanasten/YOURLS-Change-Password
Description: Users can change their password via the admin interface
Version: 1.0
Author: Vaughn Van Asten <vaughn.vanasten@gmail.com>
Author URI: http://github.com/vvanasten
*/

// No direct call
if( ! defined( 'YOURLS_ABSPATH' ) ) die();

/**
 * Set default password requirements. Default is minimum 6 characters.
 * You may also enable the following:
 * - Require at least one digit
 * - Require at lesat one special character
 * - Require both uppercase and lowercase letters
 * 
 * You can change these options in your config.php file.
 * This example enables everything and a minimum of 8 characters:
 * 
 * define('VVA_CHANGE_PASSWORD_MINIMUM_LENGTH', 8 );
 * define('VVA_CHANGE_PASSWORD_USE_DIGITS', TRUE );
 * define('VVA_CHANGE_PASSWORD_USE_SPECIAL', TRUE );
 * define('VVA_CHANGE_PASSWORD_USE_UPPERCASE', TRUE );
 */
if( ! defined( 'VVA_CHANGE_PASSWORD_MINIMUM_LENGTH' ) )
	define( 'VVA_CHANGE_PASSWORD_MINIMUM_LENGTH', 6 );

if( ! defined( 'VVA_CHANGE_PASSWORD_USE_DIGITS' ) )
	define( 'VVA_CHANGE_PASSWORD_USE_DIGITS', FALSE );

if( ! defined( 'VVA_CHANGE_PASSWORD_USE_SPECIAL' ) )
	define( 'VVA_CHANGE_PASSWORD_USE_SPECIAL', FALSE );

if( ! defined( 'VVA_CHANGE_PASSWORD_USE_UPPERCASE' ) )
	define( 'VVA_CHANGE_PASSWORD_USE_UPPERCASE', FALSE );

/**
 * Add hooks required for plugin
 */
yourls_add_action( 'plugins_loaded',	'vva_change_password_register_page' );
yourls_add_filter( 'logout_link',		'vva_change_password_logout_link' );
yourls_add_filter( 'admin_sublinks',	'vva_change_password_admin_sublinks' );

/**
 * Register the change password page
 */
function vva_change_password_register_page()
{
	yourls_register_plugin_page( 'change_password', 'Changer son mot de passe', 'vva_change_password_display_page' );
}

/**
 * Add the change password link next to logout so it makes sense in the UI
 * 
 * @param string $logout_link
 * @return string $logout_link
 */
function vva_change_password_logout_link ( $logout_link )
{
	$admin_pages = yourls_list_plugin_admin_pages();
	$change_password_url = $admin_pages[ 'change_password' ][ 'url' ];
	
	$logout_link = rtrim( $logout_link, ')' );
	/** $logout_link .= sprintf( ' | <a href="%s">Changer son mot de passe</a>)', $change_password_url );
	*/
	return $logout_link;
}

/**
 * Remove change password link from sublist of manage plugins since we're
 * adding it to the logout link
 * 
 * @param array $admin_sublinks
 * @return array $admin_sublinks
 */
function vva_change_password_admin_sublinks( $admin_sublinks )
{
	unset( $admin_sublinks[ 'plugins' ][ 'change_password' ] );
	
	return $admin_sublinks;
}

/**
 * Display the change password page
 */
function vva_change_password_display_page()
{	
	// verify we have all necessary features
	if ( ! vva_change_password_verify_capabilities() ) return;
	
	$error_message		= NULL;
	$form_submitted		= FALSE;
	$password_changed	= FALSE;
	
	// if a form was submitted check for errors & minimum requirements
	if ( isset ( $_REQUEST[ 'submit' ] ) )
	{
		$error_message = vva_change_password_get_form_errors();
		
		$form_submitted = TRUE;
	}
	
	// if the new password meets requirements update it
	if ( $form_submitted && empty( $error_message ) )
	{
		$password_changed = vva_change_password_write_file( $_REQUEST[ 'new_password' ] );
		
		if ( ! $password_changed ) return;
	}
	
	// show password updated message or the form
	if ( $password_changed )
	{
		?>
		<div id="password_updated" class="success">
			<p>Le mot de passe a été mis à jour.</p>
			<p><a href="<?php echo yourls_admin_url( 'index.php' ) ?>">Continuer</a></p>
		</div>
		<?php
	}
	else
	{
		vva_change_password_display_form( $error_message );
	}
}

/**
 * Display update password form
 * 
 * @param string $error_message
 */
function vva_change_password_display_form( $error_message = NULL )
{
	?>
	<h2>Changer son mot de passe</h2>
	<p>Votre nouveau mot de passe doit:</p>
	<ul>
		<li>Avoir un minimum de <?php echo VVA_CHANGE_PASSWORD_MINIMUM_LENGTH; ?> caractères.</li>
		<?php 
		if ( VVA_CHANGE_PASSWORD_USE_DIGITS ) echo '<li>Utilisez au moins un chiffre.</li>';
		if ( VVA_CHANGE_PASSWORD_USE_SPECIAL ) echo '<li>Utilisez au moins un caractère spécial.</li>';
		if ( VVA_CHANGE_PASSWORD_USE_UPPERCASE ) echo '<li>Utilisez au moins une majuscule et/ou une minuscule.</li>';
		?>
	</ul>
	<div id="change_password">
		<form method="post" action="">
			<?php if ( ! empty( $error_message ) ) printf( '<p class="error">%s</p>', $error_message ); ?>
			<p>
				<label for="current_password">Mot de passe actuel</label><br />
				<input type="password" id="current_password" name="current_password" size="30" class="text" />
			</p>
			<p>
				<label for="new_password">Nouveau mot de passe</label><br />
				<input type="password" id="new_password" name="new_password" size="30" class="text" />
			</p>
			<p>
				<label for="confirm_new_password">Confirmation</label><br />
				<input type="password" id="confirm_new_password" name="confirm_new_password" size="30" class="text" />
			</p>
			<p>
				<input type="submit" id="submit" name="submit" value="Changer le mot de passe" class="button" />
			</p>
		</form>
	</div>
	<?php
}

/**
 * Verify submitted form meets requirements
 * - Current password must be correct
 * - New password must match confirm new password
 * - Minimum length met
 * - If set, have one digit
 * - If set, have one special character
 * - If set, have at least one uppercase and one lowercase letter
 * 
 * @return string $error_message
 */
function vva_change_password_get_form_errors()
{
	$error_message = NULL;
	
	if ( ! yourls_check_password_hash( YOURLS_USER, $_REQUEST[ 'current_password' ] ) )
	{
		$error_message .= 'Erreur: votre mot de passe actuel est faux<br />';
	}
	
	if ( $_REQUEST[ 'new_password' ] !== $_REQUEST[ 'confirm_new_password' ] )
	{
		$error_message .= 'Erreur: mauvaise confirmation du nouveau mot de passe<br />';
	}
	
	if ( strlen( $_REQUEST[ 'new_password' ] ) < VVA_CHANGE_PASSWORD_MINIMUM_LENGTH )
	{
		$error_message .= sprintf( 'Erreur: un minimum de %d caractères est requis<br />', VVA_CHANGE_PASSWORD_MINIMUM_LENGTH );
	}
	
	if ( VVA_CHANGE_PASSWORD_USE_DIGITS && ! preg_match( '/[0-9]+/', $_REQUEST[ 'new_password' ] ) )
	{
		$error_message .= 'Erreur: au moins un chiffre est requis dans le mot de passe<br />';
	}
	
	if ( VVA_CHANGE_PASSWORD_USE_SPECIAL && ! preg_match( '/[\W_]+/', $_REQUEST[ 'new_password' ] ) )
	{
		$error_message .= 'Erreur: au moins un caractère spécial est requis<br />';
	}
	
	if ( VVA_CHANGE_PASSWORD_USE_UPPERCASE &&
		( ! preg_match( '/[a-z]+/', $_REQUEST[ 'new_password' ] ) || ! preg_match( '/[A-Z]+/', $_REQUEST[ 'new_password' ] ) )
		)
	{
		$error_message .= 'Erreur: au moins une majuscule/minuscule est requise<br />';
	}
	
	return $error_message;
}

/**
 * Update current user's password in config file
 * 
 * Borrowed heavily from yourls_hash_passwords_now()
 * 
 * @param string $new_password
 * @return boolean
 */
function vva_change_password_write_file( $new_password )
{
	$configdata = file_get_contents( YOURLS_CONFIGFILE );
	if ( $configdata == FALSE )
	{
		echo '<p class="error">Erreur: impossible de lire le fichier de configuration</p>';
		return FALSE;
	}
	
	global $yourls_user_passwords;
	$current_password = $yourls_user_passwords[ YOURLS_USER ];
	$user = YOURLS_USER;
	
	$hash = yourls_phpass_hash( $new_password );
	// PHP would interpret $ as a variable, so replace it in storage.
	$hash = str_replace( '$', '!', $hash );
	
	$quotes = "'" . '"';
	$pattern = "/[$quotes]${user}[$quotes]\s*=>\s*[$quotes]" . preg_quote( $current_password, '/' ) . "[$quotes]/";
	$replace = "'$user' => 'phpass:$hash'";
	$count = 0;
	$configdata = preg_replace( $pattern, $replace, $configdata, -1, $count );
	
	// There should be exactly one replacement. Otherwise, fast fail.
	if ( $count != 1 )
	{
		echo '<p class="error">Erreur: mise à jour du mot de passe impossible</p>';
		return FALSE;
	}
	
	$success = file_put_contents( YOURLS_CONFIGFILE, $configdata );
	if ( $success === FALSE )
	{
		echo '<p class="error">Erreur: mise à jour de la configuration impossible</p>';
		return FALSE;
	}
	
	return TRUE;
}

/**
 * Verify YOURLS >= 1.7, passwords are hashed, and config file is writable
 * 
 * @return bool
 */
function vva_change_password_verify_capabilities()
{
	$error = FALSE;
	
	if ( version_compare( YOURLS_VERSION, '1.7', 'lt' ) )
	{
		$error .= 'Erreur : le greffon requiert YOURLS version 1.7 ou plus<br />';
	}
	
	if ( yourls_has_cleartext_passwords() )
	{
		$error .= 'Error: This plugin requires stored passwords to be hashed<br />';
	}
	
	if ( ! is_readable( YOURLS_CONFIGFILE ) )
		
	{
		$error .= 'Erreur: Ne peut lire la configuration<br />';
	}
		
	if ( ! is_writable( YOURLS_CONFIGFILE ) )
	{
		$error .= 'Erreur: Ne peut écrire la nouvelle configuration<br />';
	}
	
	if ( $error )
	{
		echo '<p class="error">' . $error . '</p>';
		
		return FALSE;
	}
	else
	{
		return TRUE;
	}
}

// EOF */
