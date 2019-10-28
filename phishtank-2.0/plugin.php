<?php
/*
Plugin Name: Phishtank-2.0
Plugin URI: https://github.com/joshp23/YOURLS-Phishtank-2.0
Description: Prevent shortening malware URLs using phishtank API
Version: 2.1.2
Author: Josh Panter
Author URI: https://unfettered.net/
*/

// No direct call
if( !defined( 'YOURLS_ABSPATH' ) ) die();

// Add the admin page
yourls_add_action( 'plugins_loaded', 'phishtank_add_page' );

function phishtank_add_page() {
        yourls_register_plugin_page( 'phishtank', 'Phishtank', 'phishtank_do_page' );
}

// Display admin page
function phishtank_do_page() {

	// Check if a form was submitted
	if( isset( $_POST['phishtank_api_key'] ) ) {
		// Check nonce
		yourls_verify_nonce( 'phishtank' );
		
		// Process form - update option in database
		yourls_update_option( 'phishtank_api_key', $_POST['phishtank_api_key'] );
		if(isset($_POST['phishtank_recheck'])) yourls_update_option( 'phishtank_recheck', $_POST['phishtank_recheck'] );
		if(isset($_POST['phishtank_soft'])) yourls_update_option( 'phishtank_soft', $_POST['phishtank_soft'] );
		if(isset($_POST['phishtank_cust_toggle'])) yourls_update_option( 'phishtank_cust_toggle', $_POST['phishtank_cust_toggle'] );
	}

	// Get values from database
	$phishtank_api_key = yourls_get_option( 'phishtank_api_key' );
	$phishtank_recheck = yourls_get_option( 'phishtank_recheck' );
	$phishtank_soft = yourls_get_option( 'phishtank_soft' );
	$phishtank_cust_toggle = yourls_get_option( 'phishtank_cust_toggle' );
	$phishtank_intercept = yourls_get_option( 'phishtank_intercept' );

	// set defaults
	if ($phishtank_recheck !== "false") {
		$rck_chk = 'checked';
		$vis_rck = 'inline';
		} else {
		$rck_chk = null;
		$vis_rck = 'none';
		}
	if ($phishtank_soft !== "false") { 
		$pl_ck = 'checked';
		$vis_pl = 'inline';
		} else {
		$pl_ck = null;
		$vis_pl = 'none';
		}
	if ($phishtank_cust_toggle !== "true") { 
		$url_chk = null;
		$vis_url = 'none';
		} else {
		$url_chk = 'checked';
		$vis_url = 'inline';
		}

	// Create nonce
	$nonce = yourls_create_nonce( 'phishtank' );

	echo <<<HTML
		<div id="wrap">
		<h2>Phishtank API Key</h2>
		<p>Vous pouvez utiliser l'API de Phistank sans clé, mais vous obtiendrez une limite de taux supérieure si vous en utilisez une. <a href="https://www.phishtank.com/" target="_blank">Cliquez ici</a> pour en savoir plus ou pour enregistrer cette application et obtenir une clé.</p>
		<form method="post">
		<input type="hidden" name="nonce" value="$nonce" />
		<p><label for="phishtank_api_key">Votre clé  </label> <input type="text" size=60 id="phishtank_api_key" name="phishtank_api_key" value="$phishtank_api_key" /></p>

		<h2>Re-vérification des redirections : comportement avec les anciens liens</h2>
		<p>Les anciens liens peuvent être re-vérifiés à chaque fois qu'il sont utilisés. <b>Le comportement par défaut est de les vérifier</b>.</p>

		<div class="checkbox">
		  <label>
		    <input type="hidden" name="phishtank_recheck" value="false" />
		    <input name="phishtank_recheck" type="checkbox" value="true" $rck_chk > Re-vérifier les anciens liens?
		  </label>
		</div>

		<div style="display:$vis_rck;" >
			<p>Vous pouvez décider de conserver ou de supprimer les liens qui échouent à une nouvelle vérification. <b>Ils sont par défaut préservés</b>, car de nombreux liens ont tendance à ne pas rester indéfiniment sur la liste noire.</p>
		
			<div class="checkbox">
			  <label>
			    <input type="hidden" name="phishtank_soft" value="false" />
			    <input name="phishtank_soft" type="checkbox" value="true" $pl_ck > Préserver les liens et intercepter les échecs de re-vérification?
			  </label>
			  <p>Les liens qui ont échoué la re-vérification sont ajoutés au greffon <a href="https://github.com/joshp23/YOURLS-Compliance" target="_blank" >Compliance</a> s'il est installé.</p>
			</div>

			<div class="checkbox" style="display:$vis_pl;">
			  <label>
				<input name="phishtank_cust_toggle" type="hidden" value="false" /><br>
				<input name="phishtank_cust_toggle" type="checkbox" value="true" $url_chk >Utiliser une URL d'interception?
			  </label>
			</div>
			<div style="display:$vis_url;">
				<p>Laisser ce paramètre vide activera les réglages par défaut.</p>
				<p><label for="phishtank_intercept">Intercepter l'URL </label> <input type="text" size=40 id="phishtank_intercept" name="phishtank_intercept" value="$phishtank_intercept" /></p>
			</div>
		</div>
		<p><input type="submit" value="Envoyer" /></p>
		</form>
		</div>
HTML;
}

// Check phishtank when a new link is added
yourls_add_filter( 'shunt_add_new_link', 'phishtank_check_add' );
function phishtank_check_add( $false, $url ) {
    // Sanitize URL and make sure there's a protocol
    $url = yourls_sanitize_url( $url );

    // only check for 'http(s)'
    if( !in_array( yourls_get_protocol( $url ), array( 'http://', 'https://' ) ) )
        return $false;
    
    // is the url malformed?
    if ( phishtank_is_blacklisted( $url ) === yourls_apply_filter( 'phishtank_malformed', 'malformed' ) ) {
		return array(
			'status' => 'fail',
			'code'   => 'error:nourl',
			'message' => yourls__( 'Missing or malformed URL' ),
			'errorCode' => '400',
		);
    }
	
    // is the url blacklisted?
    if ( phishtank_is_blacklisted( $url ) != false ) {
		return array(
			'status' => 'fail',
			'code'   => 'error:spam',
			'message' => 'Ce domaine est sur liste noire',
			'errorCode' => '403',
		);
    }
	
	// All clear, not interrupting the normal flow of events
	return $false;
}


// Re-Check phishtank on redirection
yourls_add_action( 'redirect_shorturl', 'phishtank_check_redirect' );
function phishtank_check_redirect( $url, $keyword = false ) {
	// Are we performing rechecks?
	$phishtank_recheck = yourls_get_option( 'phishtank_recheck' );
	if ($phishtank_recheck !== "false" ) {
		if( is_array( $url ) && $keyword == false ) {
			$keyword = $url[1];
			$url = $url[0];
		}
		// Check when the link was added
		// If shorturl is fresh (ie probably clicked more often?) check once every 10 times, otherwise check every time
		// Define fresh = 3 days = 259200 secondes
		$now  = date( 'U' );
		$then = date( 'U', strtotime( yourls_get_keyword_timestamp( $keyword ) ) );
		$chances = ( ( $now - $then ) > 259200 ? 10 : 1 );
		if( $chances == mt_rand( 1, $chances ) ) {
			if( phishtank_is_blacklisted( $url ) == true ) {
				// We got a hit, do we delete or intercept?
				$phishtank_soft = yourls_get_option( 'phishtank_soft' );
				// Intercept by default
				if( $phishtank_soft !== "false" ) {
					// Compliance integration
					if((yourls_is_active_plugin('compliance/plugin.php')) !== false) {
						global $ydb;
						$table = 'flagged';
						if (version_compare(YOURLS_VERSION, '1.7.3') >= 0) {
							$binds = array('keyword' => $keyword);
							$sql = "REPLACE INTO  `$table`  (keyword, reason) VALUES (:keyword, 'Phishtank Auto-Flag'";
							$insert = $ydb->fetchObject($sql, $binds);
						} else {
							$insert = $ydb->query("REPLACE INTO `flagged` (keyword, reason) VALUES ('$keyword', 'Phishtank Auto-Flag')");
						}
					}
					// use default intercept page?
					$phishtank_cust_toggle = yourls_get_option( 'phishtank_cust_toggle' );
					$phishtank_intercept = yourls_get_option( 'phishtank_intercept' );
					if (($phishtank_cust_toggle == "true") && ($phishtank_intercept !== '')) {
						// How to pass keyword and url to redirect?
						yourls_redirect( $phishtank_intercept, 302 );
						die ();
					}
					// Or go to default flag intercept 
					display_phlagpage( $keyword );
				} else {
				// Otherwise delete & die
				yourls_delete_link_by_keyword( $keyword );
				yourls_die( 'La page que vous tentez de visiter est sur la liste noire. Le lien vers cette page a été supprimé. / The page is blacklisted. The link has been deleted', 'Domain blacklisted', '403' );
				} 
			}
		}
		// Nothing found, move along
	}
	// Re-check disabled, move along
}
// Soft on Spam ~ intercept warning
function display_phlagpage($keyword) {

        $title = yourls_get_keyword_title( $keyword );
        $url   = yourls_get_keyword_longurl( $keyword );
        $base  = YOURLS_SITE;
	$img   = yourls_plugin_url( dirname( __FILE__ ).'/assets/caution.png' );
	$css   = yourls_plugin_url( dirname( __FILE__ ).'/assets/bootstrap.min.css');

	$vars = array();
		$vars['keyword'] = $keyword;
		$vars['title'] = $title;
		$vars['url'] = $url;
		$vars['base'] = $base;
		$vars['img'] = $img;
		$vars['css'] = $css;

	$intercept = file_get_contents( dirname( __FILE__ ) . '/assets/intercept.php' );
	// Replace all %stuff% in the intercept with variable $stuff
	$intercept = preg_replace_callback( '/%([^%]+)?%/', function( $match ) use( $vars ) { return $vars[ $match[1] ]; }, $intercept );

	echo $intercept;

	die();
}

// Is the link spam? true / false 
function phishtank_is_blacklisted( $url ) {
	$parsed = parse_url( $url );
	
	if( !isset( $parsed['host'] ) )
		return yourls_apply_filter( 'phishtank_malformed', 'malformed' );
	
	// Remove www. from domain (but not from www.com)
	$parsed['host'] = preg_replace( '/^www\.(.+\.)/i', '$1', $parsed['host'] );
	
	// Phishtank API
	$phishtank_api_key = yourls_get_option( 'phishtank_api_key' );

        $API="http://checkurl.phishtank.com/checkurl/";
        $url_64=base64_encode($url);

        $ch = curl_init();
        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt ($ch, CURLOPT_POST, TRUE);
        curl_setopt ($ch, CURLOPT_USERAGENT, "YOURLS");
        curl_setopt ($ch, CURLOPT_POSTFIELDS, "format=xml&app_key=$phishtank_api_key&url=$url_64");
        curl_setopt ($ch, CURLOPT_URL, "$API");
        $result = curl_exec($ch);
        curl_close($ch);

        if (preg_match("/phish_detail_page/",$result)) {
			return yourls_apply_filter( 'phishtank_blacklisted', true );
	}
	
	// All clear, probably not spam
	return yourls_apply_filter( 'phishtank_clean', false );
}
