<?php


if (!defined('ABSPATH')) { 
    exit; // Exit if accessed directly.
}

class tpdatPluginDeactivation {

	/**
	 * Main deactivation handler.
	 */
	function deactivate() {

		$this->removeCronJob();

		$method = tpdat_getMethodFromOptions();

		if ($method == "file") {
	        $this->resetFiles();
	    } elseif ($method == "requestUri") {
	    	$this->returnOriginalFile(); // has check on whether original file is there
	    }

	    if (get_option("tpd_adstxt")) {
	    	delete_option("tpd_adstxt");			
	    }
	}


	function removeCronJob() {
	    $timestamp = wp_next_scheduled("tpdat_install_latest_adstxt_from_server");
	    if ($timestamp) {
	    	wp_unschedule_event($timestamp, "tpdat_install_latest_adstxt_from_server");			
	    }
	}


	/**
	 * Meta reset file handler.
	 */
	function resetFiles() {
	    if (tpdat_adsTxtFileExists() && tpdat_adsTxtFileIsWritable()) {

	        $removalSuccess = $this->removePluginsFile();
	        if (!$removalSuccess) {
	            return false;
	        }

	        $renameSuccess = $this->returnOriginalFile();
	        if (!$renameSuccess) {
	            return false;            
	        }
	    }
	}

	/**
	 * Remove ads.txt file created on activation.
	 */
	function removePluginsFile() {
	    if (tpdat_adsTxtFileExists() && tpdat_adsTxtFileIsWritable()) {
	        $deletion = unlink(tpdat_adsTxtPath());
	        if (!$deletion) {
	            return false;
	        }
	        return true;    
	    }
	}


	/**
	 * Check for original file renamed to ads-txt-original.txt on installation
	 * and rename it back to ads.txt if found.
	 */
	function returnOriginalFile() {
	    // check for renamed file and put back if found
	    // if it doesn't exist it may never have, can ignore?
	    if (tpdat_originalFileExists() && tpdat_originalFileIsWriteable()) {
	        return rename(tpdat_originalAdsTxtPath(), tpdat_adsTxtPath());
	    }
	    return true;		
	}













}