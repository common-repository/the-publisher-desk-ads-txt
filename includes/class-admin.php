<?php


if (!defined('ABSPATH')) { 
    exit; // Exit if accessed directly.
}

class tpdatPluginAdmin { 



	function tpdat_nextRefreshTime() {
	    return date("m-d-Y H:i:s", wp_next_scheduled("tpdat_install_latest_adstxt_from_server"));
	}


	/**
	 * Print collected errors to the admin panel
	 */
	function tpdat_printErrors() {
	    global $ads_txt_errors;

	    if (empty($ads_txt_errors)) {
	        return;
	    }

	    foreach ($ads_txt_errors  as $message) {
	        esc_html__(printf("<div class=\"notice notice-error\"><p>%s</p></div>", $message));
	    }
	}


	/**
	 * Admin panel writer functino
	 */
	public function adminPanel() {
	    global $ads_txt_errors;

	    // plugin update handler
	    if (!get_option("tpd_adstxt")) {
		    require_once plugin_dir_path(__FILE__) . '/class-activation.php';
		    $act = new tpdatPluginActivation;
		    $act->activate();	    	
	    }


	    if (array_key_exists("pd_refresh_submit", $_POST) && $_POST["pd_refresh_submit"]) {

	        if ( 
	            ! isset( $_POST["tpdat_refresh_nonce_field"] ) 
	            || ! wp_verify_nonce( $_POST["tpdat_refresh_nonce_field"], "tpdat_refresh_nonce_action" ) 
	        ) {
	            tpdat_addError("Error on ads.txt refresh: the hidden field check was not passed.");
	        } else {
		        tpdat_updateTpdContents();
		       	$pub_desk_ads_txt = tpd_getTpdContent();
		        $custom_content = tpd_getCustomContent();
	            
	        }

	    } elseif (array_key_exists("pd_form_submit", $_POST) && $_POST["pd_form_submit"]) {

	        if ( 
	            ! isset( $_POST["tpdat_custom_submission_nonce_field"] ) 
	            || ! wp_verify_nonce( $_POST["tpdat_custom_submission_nonce_field"], "tpdat_custom_submission_nonce_action" ) 
	        ) {            
	            tpdat_addError("Error on custom submission: the hidden field check was not passed.");
	        } else {

	        	$arr = tpdat_handleCustomContentSubmission();

	            $pub_desk_ads_txt = $arr["tpd"];
	            $custom_content = $arr["custom"];

	        }

	    } else {

	        $pub_desk_ads_txt = tpd_getTpdContent();
	        $custom_content = tpd_getCustomContent();

	    }
	    

	    ?>


	    <!-- START HTML -->

	    
	    <link rel="stylesheet" href="<?php echo plugin_dir_url( __DIR__ ) . 'assets/bootstrap-4.0.0.min.css' ?>">

	    <style>

	        .body {
	            position: relative;
	            text-align: center;
	            width: 80%;
	            margin: 0 auto;
	        }

	        .edit-text {
	            display: block;
	            font-size: 20px;
	            width: 100%;
	            height: 250px;
	        }

	        .edit-text:disabled {
	            background-color: LightGray;
	        }

	        #submit {
	            text-align: center;
	            position: absolute;
	            right:0;
	        }

	        .submit-text {
	            text-align: center;
	            padding-top: 55px;
	            padding-left: 50px;
	        }
	        
	        #refresh-link {
	            cursor: pointer;
	            text-decoration: underline;
	        }

	    </style>

	    <script>
	    	/*
	        (function(w) {
	            if (w.location && w.location.href && w.location.href.split("&").length) {
	                try {
	                    var split = w.location.href.split("&");
	                    if (split.length > 1) {
	                        if (split[1].indexOf("refresh") > -1) {
	                            w.location.href = split[0];
	                        }
	                    }
	                } catch (e) {
	                    console.warn(e);
	                }
	            }
	        })(window);
			*/
	    </script>

	    <div class="body">


	        <h1 style="padding-top: 30px">TPD Ads.txt Wordpress Plugin</h1>

	        <h6 id="next-refresh-info">
	        	Next Refresh Scheduled For <i><?php echo esc_attr_e($this->tpdat_nextRefreshTime());?> UTC</i>
	       	</h6>

	        <?php
	            $this->tpdat_printErrors();
	        ?>

	        <form method="POST">
	        	<input type="hidden" name="pd_refresh_submit" value="true" />
	        	<?php wp_nonce_field( "tpdat_refresh_nonce_action", "tpdat_refresh_nonce_field" ); ?>
	            <?php submit_button("Refresh cache", "primary", "refresh-submit-btn"); ?>
	        </form>
	        <form href="admin.php" method="POST"> 

	            <?php wp_nonce_field( "tpdat_custom_submission_nonce_action", "tpdat_custom_submission_nonce_field" ); ?>

	            <input type="hidden" name="pd_form_submit" value="true" />
	            <div style= "text-align: center;">

	                <p style="font-weight: bold; font-size: 18px;">Publisher Desk Ad Rules</p>
	                <textarea class="edit-text" disabled><?php echo sanitize_textarea_field($pub_desk_ads_txt); ?></textarea>
	                
	                <p style="font-weight: bold; font-size: 18px; padding-top: 20px">Custom Ad Rules</p>
	                <textarea name="client-ads-text" class="edit-text"><?php echo sanitize_textarea_field($custom_content); ?></textarea>
	            </div>

	            <?php submit_button(); ?>

	        </form>

	    </div>


	    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js" integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" crossorigin="anonymous"></script>

	    <!-- STOP -->

	    <?php

	}



}