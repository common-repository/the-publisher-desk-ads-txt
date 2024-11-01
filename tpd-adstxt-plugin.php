<?php

/**
 * @package AdsTxt generator
 * Updated by Christopher Norvell 5/20/2019
 */

/*
    Plugin Name: The Publisher Desk ads.txt
    Plugin URI: https://www.publisherdesk.com
    Description: Ads.txt management tool for publishers in The Publisher Desk portfolio.
    Version: 1.5.0
    Author: The Publisher Desk
    Author URI: http://www.publisherdesk.com
    License: GPLv2 or later
 */


if (!defined('ABSPATH')) { 
    exit; // Exit if accessed directly.
}


/* globals */

global $ads_txt_errors;
$ads_txt_errors = [];

global $adsTxtServerUrl;
$adsTxtServerUrl = "https://www.publisherdesk.com/ads-txt/global.txt";

$GLOBALS["TPDAT_ADS_MARKER"] = "\n\n### END TPD ADS TXT ###\n\n";

if (!defined('TPDAT_PLUGIN_VERSION')) {
    define('TPDAT_PLUGIN_VERSION', '1.5');
}

/* activation / deactivation */

register_activation_hook(__FILE__, "tpdat_pluginActivation");
register_deactivation_hook(__FILE__, "tpdat_pluginDeactivation");


function tpdat_pluginActivation() {
    require_once __DIR__ . '/includes/class-activation.php';
    $act = new tpdatPluginActivation;
    $act->activate();
}


function tpdat_pluginDeactivation() {
    require_once __DIR__ . '/includes/class-deactivation.php';
    $deact = new tpdatPluginDeactivation;
    $deact->deactivate();
}


/* plugin updates */

add_action('plugins_loaded', 'tpdat_checkForPluginUpdate');
function tpdat_checkForPluginUpdate() {
    if (TPDAT_PLUGIN_VERSION !== get_option('tpdat_plugin_version')) {
        tpdat_pluginActivation();
    }
}


/* Add admin panel. */

add_action('admin_menu', 'tpdat_addAdminPanel');


/**
 * Add the ads.txt admin page to the WP dashboard menu
 */
function tpdat_addAdminPanel() {
    add_menu_page(
        "Publisher Desk ads.txt Wordpress Plugin", 
        "Ads.txt", 
        "manage_options", 
        "pd-ads-menu", 
        "tpdat_adminPanel"
    );
}

function tpdat_adminPanel() {
    require_once __DIR__ . '/includes/class-admin.php';
    $adminPanel = new tpdatPluginAdmin;
    $adminPanel->adminPanel();    
}


/* Cronjob: refresh TPD ads.txt contents daily. */
add_action('tpdat_install_latest_adstxt_from_server', 'tpdat_updateTpdContents');




/**
 * Add an error to global errors array
 * @param STRING $str
 */
function tpdat_addError($str) {
    global $ads_txt_errors;
    $ads_txt_errors[] = $str;
}


/**
 * Respond to a request for the site's ads.txt file.
 *     Because the file contents can be stored either
 *     as an option or explicitly through an actual file,
 *     a check must be done on which method is currently 
 *     employed.
 * @return (string) ads.txt file contents for request response
 */
function tpdat_respondToAdsTxtRequest() {
    if (esc_url_raw($_SERVER['REQUEST_URI']) === "/ads.txt") {
        if (tpdat_getMethodFromOptions() == "requestUri") {
            header("Content-Type: text/plain");
            echo esc_html(tpdat_getContentsFromOptions());
        }
        die();
    }  
}
add_action('init', 'tpdat_respondToAdsTxtRequest');


//  Add exposed endpoint for prompting an update so that changes in TPD's admin UI will be
//      instantly reflected.
//      Don't use full plugin url since we need as much consistency as possible.
//      Example: localhost:8888/wp-json/the-publisher-desk-ads-txt/update
add_action( 'rest_api_init', function () {
    register_rest_route( 'the-publisher-desk-ads-txt', '/update', array(
        'methods' => 'GET',
        'permission_callback' => function() {
            return true;
        },          
        'callback' => 'tpdat_updateTpdContents',
    ));
});



/**
 * Helpers: file management.
 */

function tpdat_adsTxtPath() {
    // don't use get_home_path in case of sub-folder wp installation
    return trailingslashit(ABSPATH) . 'ads.txt';
}

function tpdat_adsTxtFileExists() {
    $adsTxtPath = tpdat_adsTxtPath();
    return (!empty($adsTxtPath) && file_exists($adsTxtPath));
}

function tpdat_adsTxtFileIsWritable() {
    return is_writable(tpdat_adsTxtPath());
}


function tpdat_originalAdsTxtPath() {
    return str_replace("/ads.txt", "/ads-txt-original.txt", tpdat_adsTxtPath());
}

function tpdat_originalFileExists() {
    $adsTxtPath = tpdat_originalAdsTxtPath();
    return (!empty($adsTxtPath) && file_exists($adsTxtPath));
}

function tpdat_originalFileIsWriteable() {
    return is_writable(tpdat_originalAdsTxtPath());
}





/**
 * Helpers: options management.
 */


/**
 * Get ads.txt content by section input. Use division marker TPDAT_ADS_MARKER 
 * to differentiate
 * @param  STRING $type custom/tpd
 * @return STRING content section based on input
 */
function tpdat_getContentPiece($type) {
    global $TPDAT_ADS_MARKER;

    $arrExploded = explode($TPDAT_ADS_MARKER, tpdat_getAdsTxtContents());

    if (count($arrExploded) !== 2) {
        return "";
    }

    if ($type === "tpd") {
        return $arrExploded[0];
    } else if ($type === "custom") {
        return $arrExploded[1];
    } else {
        tpdat_addError("Warning: Invalid argument provided when fetching custom or TPD ads.txt contents.");
    }
}

function tpd_getCustomContent() {
    return tpdat_getContentPiece("custom");
}

function tpd_getTpdContent() {
    return tpdat_getContentPiece("tpd");
}


// variable based on method
function tpdat_getAdsTxtContents() {
    $method = tpdat_getMethodFromOptions();

    if ($method == "file") { // nginx -> read file
        $raw = file_get_contents(tpdat_adsTxtPath());
        return esc_textarea($raw);
    } else if ($method == "requestUri") {
        return esc_textarea(tpdat_getContentsFromOptions());
    } else {
        tpdat_addError("Error: No server specification provided to ads.txt contents fetch function.");
    }
}


/**
 * Get contents from plugin's option. For non file-mode functionality.
 * @return (string) full ads.txt contents
 */
function tpdat_getContentsFromOptions() {
    $tpdatOptions = get_option("tpd_adstxt");
    return $tpdatOptions["contents"];
}


/**
 * @return (string) file or requestUri
 */
function tpdat_getMethodFromOptions() {
    $tpdatOptions = get_option("tpd_adstxt");
    return $tpdatOptions["method"];
}






/**
 * Helper to avoid sub-domain when fetching any pub-specific ads.txt lines
 *     from https://stackoverflow.com/questions/2679618/get-domain-name-not-subdomain-in-php
 * @param  (string) $host [description]
 */
function tpdat_getDomainOnly($host){
    $host = strtolower(trim($host));
    $host = ltrim(str_replace("http://","",str_replace("https://","",$host)),"www.");
    $count = substr_count($host, '.');
    if($count === 2){
        if(strlen(explode('.', $host)[1]) > 3) $host = explode('.', $host, 2)[1];
    } else if($count > 2){
        $host = tpdat_getDomainOnly(explode('.', $host, 2)[1]);
    }
    $host = explode('/',$host);
    return $host[0];
}



/**
 *  Fetch'Pub-Specific' lines from a url based on the site's domain (excluding sub-domain)
 *      of structure: https://publisherdesk.com/ads-txt/DOMAIN.txt
 *  If the publisher doesn't have any unique ads.txt lines, the url may 404
 * @return (string) pub-specific TPD ads.txt lines
 */
function tpdat_getRemotePubSpecificAdsTxtContents() {

    $site_base_domain = tpdat_getDomainOnly($_SERVER['HTTP_HOST']);

    $pub_desk_pub_specific_ads_txt = "";

    $pub_specific_server_url = 'https://www.publisherdesk.com/ads-txt/' . $site_base_domain . '.txt'  . '?' . rand(1000, 9999);

    $pub_desk_pub_specific_ads_txt_response = wp_remote_get($pub_specific_server_url);

    // verify content response type
    if (
        !$pub_desk_pub_specific_ads_txt_response ||
        !isset($pub_desk_pub_specific_ads_txt_response['headers']) || 
        $pub_desk_pub_specific_ads_txt_response['headers']['content-type'] !== 'text/plain'
    ) {
        return $pub_desk_pub_specific_ads_txt;
    }

    $pub_desk_pub_specific_ads_txt = wp_remote_retrieve_body($pub_desk_pub_specific_ads_txt_response);

    $pub_desk_pub_specific_ads_txt = sanitize_textarea_field($pub_desk_pub_specific_ads_txt);

    return $pub_desk_pub_specific_ads_txt;

}

/**
 * Fetch 'Global' lines shared among all TPD publishers are sourced from
 *     https://www.publisherdesk.com/ads-txt/global.txt
 * @return (string) global TPD ads.txt lines
 */
function tpdat_getRemoteGlobalAdsTxtContents() {
    global $adsTxtServerUrl;

    $pub_desk_global_ads_txt = "";

    $pub_desk_global_ads_txt_response = wp_remote_get($adsTxtServerUrl . '?' . rand(1000, 9999));
    if ($pub_desk_global_ads_txt_response) {
        $pub_desk_global_ads_txt = wp_remote_retrieve_body($pub_desk_global_ads_txt_response);
        $pub_desk_global_ads_txt = sanitize_textarea_field($pub_desk_global_ads_txt);        
    }

    return $pub_desk_global_ads_txt;
}


/**
 * Fetches remote TPD ads.txt lines, both global and pub-specific
 * @return (string) tpd's ads.txt contents
 */
function tpdat_getRemoteTpdAdsTxtContents() {
    $pub_desk_full_ads_txt = "";

    try {

        $remote_global_contents = tpdat_getRemoteGlobalAdsTxtContents();
        $remote_pub_specific_contents = tpdat_getRemotePubSpecificAdsTxtContents();

        // Move sellers.json group to the top if applicable
        if ( strpos( $remote_pub_specific_contents, '# sellers.json' ) !== false ) {
            $pub_specific_contents_line_break_arr = explode( "\n", $remote_pub_specific_contents );
            if ( is_array( $pub_specific_contents_line_break_arr ) && count( $pub_specific_contents_line_break_arr ) > 0 ) {
                $sellers_json_line_break_arr = array();
                foreach ( $pub_specific_contents_line_break_arr as $i => $line ) {
                    if (
                        $line === '# sellers.json' &&
                        count( $pub_specific_contents_line_break_arr ) >= $i &&
                        trim( $pub_specific_contents_line_break_arr[ $i + 1 ] ) !== ''
                    ) {
                        array_push( $sellers_json_line_break_arr, $line );
                        array_push( $sellers_json_line_break_arr, $pub_specific_contents_line_break_arr[ $i + 1 ] );
                        unset( $pub_specific_contents_line_break_arr[ $i ] );
                        unset( $pub_specific_contents_line_break_arr[ $i + 1 ] );
                        $pub_specific_contents_line_break_arr = array_values( $pub_specific_contents_line_break_arr );
                        break;
                    }
                }
                if ( count( $sellers_json_line_break_arr ) === 2 ) {
                    $pub_desk_full_ads_txt .= "### TPD " . tpdat_getDomainOnly($_SERVER['HTTP_HOST']) . " SELLERS.JSON ###\n\n";
                    $pub_desk_full_ads_txt .= implode( "\n", $sellers_json_line_break_arr ) . "\n\n";
                    $remote_pub_specific_contents = implode( "\n", $pub_specific_contents_line_break_arr );
                }
            }
        }

        $pub_desk_full_ads_txt .= "### TPD GLOBAL ###\n\n";
        $pub_desk_full_ads_txt .= $remote_global_contents;

        $pub_desk_full_ads_txt .= "\n\n### TPD " . tpdat_getDomainOnly($_SERVER['HTTP_HOST']) . " SPECIFIC ###\n\n";
        $pub_desk_full_ads_txt .= $remote_pub_specific_contents;

    } catch (Exception $e) {return "";}

    return $pub_desk_full_ads_txt;
}


/**
 * Update the top bit of the file, from the server, leaving the custom stuff as it is
 * @return STRING the latest PD text from the ads.txt file, or cached content on error
 */
function tpdat_updateTpdContents() {
    global $TPDAT_ADS_MARKER;

    $adsTxtContentFull = tpdat_getAdsTxtContents();

    // get custom content
    $arrExploded = explode($TPDAT_ADS_MARKER, $adsTxtContentFull);
    if (count($arrExploded) !== 2) {
        $arrExploded = ["", ""];
    }


    // get new version of tpd contents
    $pub_desk_ads_txt = tpdat_getRemoteTpdAdsTxtContents();
    if (is_null($pub_desk_ads_txt)) {
        tpdat_addError("Error: Failed to retrieve TPD's ads.txt contents.");
    }

    tpdatSaveAdsTxt($pub_desk_ads_txt, $arrExploded[1]);

    return $pub_desk_ads_txt;
}



/**
 * Write the two sections to the plugin"s ads.txt file
 * @param  STRING $pdText     the publisher desk"s ads.txt lines
 * @param  STRING $customText custom/user submitted ads.txt lines
 */
function tpdatSaveAdsTxt($pdText, $customText) {
    global $TPDAT_ADS_MARKER;

    $method = tpdat_getMethodFromOptions();

    if ($method == "file") { // nginx -> read file

        if (tpdat_adsTxtFileExists() && tpdat_adsTxtFileIsWritable()) {

            if (file_put_contents(tpdat_adsTxtPath(), $pdText . "\n" . $TPDAT_ADS_MARKER . $customText)) {
                return True;
            } else {
                tpdat_addError("Error: could not save changes to ads.txt file.");
            }
        } else {
            tpdat_addError("Error: ads.txt file is no longer writeable by Wordpress.");
            return null;
        }

    } else if ($method == "requestUri") {
        update_option("tpd_adstxt", [
            "method" => "requestUri",
            "contents" => $pdText . "\n" . $TPDAT_ADS_MARKER . "\n" . $customText
        ]);
        return true;
    } else {
        tpdat_addError("Warning: invalid method argument provided during save function.");
        return null;
    }

}


/**
 * Update custom section with user"s submission, then sync The Publisher Desk"s section
 * @return array [custom, tpd]
 */
function tpdat_handleCustomContentSubmission() {
    global $TPDAT_ADS_MARKER;

    /* receive and validate */

    $submittedText = sanitize_textarea_field($_POST["client-ads-text"]);

    $validatedSubmissionLines = [];
    $lineExplSubmission = explode("\n", $submittedText);
    if (!is_array($lineExplSubmission) || count($lineExplSubmission) < 1) {
        $validatedSubmissionLines = [];
    } else {
        foreach ($lineExplSubmission as $submittedLine) {
            $lineValidation = tpdat_validateAdsTxtLine($submittedLine);
            if ($lineValidation["error"]) {
                tpdat_addError($lineValidation["error"]);
                return ["custom" => tpd_getCustomContent(), "tpd" => tpd_getTpdContent()];
            } else {
                $validatedSubmissionLines[] = $lineValidation["validated_line"];    
            }
            
        }        
    }

    $validatedSubmission = implode("\n", $validatedSubmissionLines);



    /* update */

    $ads_txt_content_full = tpdat_getAdsTxtContents();

    $arrExploded = explode($TPDAT_ADS_MARKER, $ads_txt_content_full);
    if (count($arrExploded) !== 2) {
        tpdat_addError("Warning: failed to split the input contents on the boundary string.");
        return ["tpd" => "", "custom" => ""];
    }

    tpdatSaveAdsTxt($arrExploded[0], $validatedSubmission);

    // now we"ve saved the user"s submitted text, refresh the default ads code from the server.
    return ["custom" => $validatedSubmission, "tpd" => tpdat_updateTpdContents()];
}



/**
 * Validate that a submitted line conforms with ads.txt standards
 * @param  STRING $line a raw user-submitted line
 * @return STRING empty if the line did not pass validation (an error is added), 
 * or the input if it passed
 */
function tpdat_validateAdsTxtLine($line) {
    // if the first character is #, recognize as comment
    
    $validation = ["error" => null, "validated_line" => ""];

    // let through empty lines
    if (strlen(trim($line)) == 0) {
        $validation["validated_line"] = $line;
        return $validation;
    }

    // let through comments
    if (substr($line, 0, 1) === "#") {
        $validation["validated_line"] = $line;
        return $validation;
    } elseif (strpos($line, ",") === false)  {
        $validation["error"] = "Error on custom submission: the line " . $line . " does not seem to have the required number of fields.";
        return $validation;
    } else { // split and remove spaces for easier treatment
        $explLine = explode(",", $line);

        $explLine = array_map(
            function($v) {return str_replace(" ", "", $v);},
            $explLine
        );
        if (count($explLine) !== 3 && count($explLine) !== 4) {
            $validation["error"] = "Error on custom submission: the line " . $line . " does not seem to have the required number of fields.";
            return $validation;
        } 
        // first entry must be a valid domain
        if (!preg_match(
            "/^(?!\-)(?:[a-zA-Z\d\-]{0,62}[a-zA-Z\d]\.){1,126}(?!\d+)[a-zA-Z\d]{1,63}$/", 
            $explLine[0]
        )) {
            $validation["error"] = "Error on custom submission: the first entry of the submitted line " . $line . " does not appear to be a valid domain.";
            return $validation;
        } 

        $directResellerCheck = trim(strtolower($explLine[2]));
        if (strpos($directResellerCheck, "#") !== false) {
            $directResellerCheck = explode("#", $directResellerCheck)[0];
        }

        // third entry must be one of direct or reseller, case-insensitive
        if (
            $directResellerCheck !== "direct" && $directResellerCheck !== "reseller"
        ) {
            $validation["error"] = "Error on custom submission: the third entry of the submitted line " . $line . " is not one of \"direct\" or \"reseller\".";
            return $validation;            
        }
        // validation passed, let the line through
        $validation["validated_line"] = $line;
        return $validation;

    }

}







