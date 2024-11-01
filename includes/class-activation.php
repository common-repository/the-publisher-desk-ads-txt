<?php

if (!defined('ABSPATH')) { 
    exit; // Exit if accessed directly.
}

// function tpdat_log($str) {
//     file_put_contents(__DIR__ . '/log.txt', "\n" . $str . "\n", FILE_APPEND);    
// }


class tpdatPluginActivation {

    public $existingAdsTxtOption;


    function decideOnMethod() {

        // check if wordpress is installed in sub-directory
        if (get_option('siteurl') !== get_option('home')) {
            return "requestUri";
        }

        $method = null;

        // check if we can manage the file directly, if not rely on basic server request response
        $testFile = trailingslashit(ABSPATH) . 'tpdat-ads-test.txt';
        if (fopen($testFile, "w")) {
            if (is_readable($testFile) && is_writeable($testFile)) {
                $method = "file";
                unlink(trailingslashit(ABSPATH) . 'tpdat-ads-test.txt');
            } else {
                unlink(trailingslashit(ABSPATH) . 'tpdat-ads-test.txt');
            }
        }

        if (!$method) {
            $method = "requestUri";
        }

        return $method;     
    }


    function activate() {
        $method = $this->decideOnMethod();
        $this->setUpFromMethod($method);
        $this->setUpCron();

        // register plugin version for use in update process
        update_option('tpdat_plugin_version', '1.5');
    }   


    function setUpCron() {
        $recurrence = "daily";
        $alreadyScheduledTime = wp_next_scheduled("tpdat_install_latest_adstxt_from_server");
        if ($alreadyScheduledTime) {
            wp_unschedule_event(
                $alreadyScheduledTime, 
                "tpdat_install_latest_adstxt_from_server"
            );
        }
        wp_schedule_event(time(), $recurrence, "tpdat_install_latest_adstxt_from_server");        
    }


    function setUpFromMethod($method) {
        global $TPDAT_ADS_MARKER;

        $this->existingAdsTxtOption = get_option('tpd_adstxt');

        // search for existing custom ads.txt lines so user doesn't lose them
        $existingCustomContent = null;
        if (
            $this->existingAdsTxtOption &&
            array_key_exists('method', $this->existingAdsTxtOption)
        ) {

            if ($this->existingAdsTxtOption['method'] == 'requestUri' && $this->existingAdsTxtOption['contents']) {

                $fullExistingContents = $this->existingAdsTxtOption['contents'];

                if (strpos($fullExistingContents, '### END TPD ADS TXT ###') !== false) {
                    $explFullContents = explode('### END TPD ADS TXT ###', $fullExistingContents);
                    if (count($explFullContents) == 2 && $explFullContents[1] && strlen($explFullContents[1]) > 0) {
                        $existingCustomContent = $explFullContents[1];

                    }    

                }

            } else if ($this->existingAdsTxtOption['method'] == 'file') {

                try {
                    $fullExistingContents = file_get_contents(tpdat_adsTxtPath());

                    if (strpos($fullExistingContents, '### END TPD ADS TXT ###') !== false) {

                        $explFullContents = explode('### END TPD ADS TXT ###', $fullExistingContents);
                        if (count($explFullContents) == 2 && $explFullContents[1] && strlen($explFullContents[1]) > 0) {
                            $existingCustomContent = $explFullContents[1];
                        }

                    }

                } catch (Exception $e) {}                

            }
        } else if (get_option("tpd_adstxt_contents")) {
            $fullExistingContents = get_option("tpd_adstxt_contents");
            if (strpos($fullExistingContents, '### END TPD ADS TXT ###') !== false) {
                $explFullContents = explode('### END TPD ADS TXT ###', $fullExistingContents);
                if (count($explFullContents) == 2 && $explFullContents[1] && strlen($explFullContents[1]) > 0) {
                    $existingCustomContent = $explFullContents[1];
                }                
            }
        }

        update_option("tpd_adstxt", [
            "method" => null,
            "contents" => null
        ]);

        if ($method == "requestUri") {

            update_option("tpd_adstxt", [
                "method" => "requestUri",
                "contents" => null
            ]);

            // rename file if we can
            if (tpdat_adsTxtFileExists()) {
                if (tpdat_adsTxtFileIsWritable() && $this->renameExistingFile()) {

                } else {    
                    tpdat_addError("Warning: current ads.txt file exists and could not be renamed. Please remove or rename this file or the plugin won't function.");
                }                 
            }


            $tpdAdsTxtContents = tpdat_getRemoteTpdAdsTxtContents();
            if (!$tpdAdsTxtContents) {
                tpdat_addError("Error: no remove ads.txt contents returned on installation fetch attempt.");
                return false;
            }

            $fullContents = $tpdAdsTxtContents . $TPDAT_ADS_MARKER;
            if (get_option("tpd_adstxt_contents")) {
                $fullContents .= "\n\n";
                $fullContents .= explode($TPDAT_ADS_MARKER, get_option("tpd_adstxt_contents"))[1];
            }

            if ($existingCustomContent) {
                $fullContents .= $existingCustomContent;
            }


            update_option("tpd_adstxt", [
                "method" => "requestUri",
                "contents" => $fullContents
            ]);
            
        } else if ($method == "file") {

            update_option("tpd_adstxt", [
                "method" => "file",
                "contents" => null
            ]);

            $fileSetup = $this->setUpFiles($existingCustomContent); // true/false

        } else { // if not identified

            tpdat_addError("Error: did not recognize server configuration, defaulting to simple redirect.");

            update_option("tpd_adstxt", [
                "method" => "requestUri",
                "contents" => null
            ]);

            if (tpdat_adsTxtFileExists()) {
                tpdat_addError("Warning: an ads.txt file already exists. Please remove or rename this file or the plugin won't function.");
            }

            $tpdAdsTxtContents = tpdat_getRemoteTpdAdsTxtContents();
            if (!$tpdAdsTxtContents) {
                tpdat_addError("Error: no remove ads.txt contents returned on installation fetch attempt.");
                return false;
            }

            if ($existingCustomContent) {
                update_option("tpd_adstxt", [
                    "method" =>"requestUri",
                    "contents" => $tpdAdsTxtContents . $TPDAT_ADS_MARKER . $existingCustomContent
                ]);
            } else {
                update_option("tpd_adstxt", [
                    "method" =>"requestUri",
                    "contents" => $tpdAdsTxtContents . $TPDAT_ADS_MARKER
                ]);                
            }



        }        
    }


    // nginx setup function
    function setUpFiles($existingCustomContent) {
        global $TPDAT_ADS_MARKER;


        // todo: writable not best check for renaming?
        if (tpdat_adsTxtFileExists()) {
            if (!tpdat_adsTxtFileIsWritable()) {
                tpdat_addError("Warning: existing ads.txt file is not writeable.");
                return false;
            }
            $success = $this->renameExistingFile();
            if (!$success) {
                tpdat_addError("Error: existing ads.txt file could not be renamed.");
                return false;
            }        
        }

        $file = fopen(tpdat_adsTxtPath(), "w");
        if (!$file) {
            tpdat_addError("Warning: unable to create new ads.txt file.");
            return false;
        }

        if (tpdat_adsTxtFileIsWritable()) {
            $tpdAdsTxtContents = tpdat_getRemoteTpdAdsTxtContents();
            if (!$tpdAdsTxtContents) {
                tpdat_addError("Error: could not fetch remote ads.txt contents.");  
                return false;
            }


            $fullContents = $tpdAdsTxtContents . $TPDAT_ADS_MARKER;

            if ($existingCustomContent) {
                $fullContents .= "\n\n";
                $fullContents .= $existingCustomContent;
            }
  
            // already determined to be writeable at this point
            $filePutRes = file_put_contents(
                tpdat_adsTxtPath(), 
                $fullContents
            );

            // false or bytes on success
            if (!$filePutRes) {
                tpdat_addError("Error: existing ads.txt file was writeable but could not be written to...");
                return false;
            }

            return true;
        } else {
            tpdat_addError("Error: double-check on existing ads.txt file being writeable failed...");
            return false;        
        }        
    }


    function renameExistingFile() {
        $existingPath = tpdat_adsTxtPath();
        return rename($existingPath, str_replace("/ads.txt", "/ads-txt-original.txt", $existingPath));        
    }


}