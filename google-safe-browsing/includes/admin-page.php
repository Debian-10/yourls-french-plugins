<?php
/**
 * Google Safe Browsing Lookup admin page
 *
 */

// Display admin page
function ozh_yourls_gsb_display_page() {

	// Check if a form was submitted
	if( isset( $_POST['ozh_yourls_gsb'] ) ) {
		// Check nonce
		yourls_verify_nonce( 'gsb_page' );
		
		// Process form
		ozh_yourls_gsb_update_option();
	}

	// Get value from database
	$ozh_yourls_gsb = yourls_get_option( 'ozh_yourls_gsb' );
	
	// Create nonce
	$nonce = yourls_create_nonce( 'gsb_page' );

	echo <<<HTML
		<h2>Clé API Google Safe Browsing</h2>

		<p>Ce greffon nécessit un <strong>compte Google</strong> et une <strong>clé API</strong> Safe Browsing
        pour utiliser le <a href="https://developers.google.com/safe-browsing/lookup_guide">service de recherche Safe Browsing</a>.</p>
        <p>Obtenez votre clé ici: <a href="https://developers.google.com/safe-browsing/key_signup">https://developers.google.com/safe-browsing/key_signup</a></p>

        <h3>Avertissement de Google</h3>
        <p> Google s'efforce de fournir les informations de phishing et de programmes malveillants les plus précises et les plus récentes. Cependant, il ne peut pas
        garantir que ses informations sont complètes et sans erreur: certains sites à risque risquent de ne pas être identifiés,
        les sites peuvent être identifiés par erreur. </ p>

        <h3>Configurer le greffon</h3>
		<form method="post">
		<input type="hidden" name="nonce" value="$nonce" />
		<p><label for="ozh_yourls_gsb">clé API</label> <input type="text" id="ozh_yourls_gsb" name="ozh_yourls_gsb" value="$ozh_yourls_gsb" size="70" /></p>
		<p><input type="submit" value="Envoyer" /></p>
		</form>
HTML;
}

// Update option in database
function ozh_yourls_gsb_update_option() {
	$in = $_POST['ozh_yourls_gsb'];
	
	if( $in ) {
		// Validate ozh_yourls_gsb: alpha & digits
		$in = preg_replace( '/[^a-zA-Z0-9-_]/', '', $in );
		
		// Update value in database
		yourls_update_option( 'ozh_yourls_gsb', $in );
        
        yourls_redirect( yourls_admin_url( 'plugins.php?page=ozh_yourls_gsb' ) );
	}
}

