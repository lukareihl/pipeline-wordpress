<?php
/*
    This Original Work is copyright of 51 Degrees Mobile Experts Limited.
    Copyright 2019 51 Degrees Mobile Experts Limited, 5 Charlotte Close,
    Caversham, Reading, Berkshire, United Kingdom RG4 7BY.

    This Original Work is licensed under the European Union Public Licence (EUPL) 
    v.1.2 and is subject to its terms as set out below.

    If a copy of the EUPL was not distributed with this file, You can obtain
    one at https://opensource.org/licenses/EUPL-1.2.

    The 'Compatible Licences' set out in the Appendix to the EUPL (as may be
    amended by the European Commission) shall be deemed incompatible for
    the purposes of the Work and the provisions of the compatibility
    clause in Article 5 of the EUPL shall not apply.
*/

use Google\Service\Analytics\CustomDimension;

/**
 * Google Analytics Service class 
 *
 * @since       1.0.0
 * 
 * @package Fiftyonedegrees
 * @author  Fatima Tariq
 */
class Fiftyonedegrees_Google_Analytics {

    /**
     * Authenticate with Google Analytics.
	 * @param string $key_google_token Access Code
     * @return boolean true for successful authentication. 
     */	
    public function google_analytics_authenticate($key_google_token) {
        
        try {

            update_option(Constants::GA_AUTH_CODE, $key_google_token);

            $client = $this->authenticate();

            if ($client) {
              
                $service = $this->get_google_analytics_service( $client );
                $this->get_analytics_properties_list($service);
                return true; 
            }
            else {
                error_log("Could not authenticate with the user.");
            }
    
        }
        catch (Exception $e) {
    
            error_log($e->getMessage());
        }
        return false; 
    }

    /**
     * Authenticates with backend PHP server using Google Client.
     * @return boolean status flag
     */	
    public function authenticate() {

        $client = new Google_Client();
        $client->setApprovalPrompt(FIFTYONEDEGREES_PROMPT);
        $client->setAccessType(FIFTYONEDEGREES_ACCESS_TYPE);
        $client->setClientId(FIFTYONEDEGREES_CLIENT_ID);
        $client->setClientSecret(FIFTYONEDEGREES_CLIENT_SECRET);
        $client->setRedirectUri(FIFTYONEDEGREES_REDIRECT);
        $client->setScopes(Google_Service_Analytics::ANALYTICS_READONLY);
        
        $ga_google_authtoken = get_option(Constants::GA_TOKEN);
    
        if (!empty($ga_google_authtoken)) {
    
            $client->setAccessToken($ga_google_authtoken);
        }
        else {
    
            $auth_code = get_option(Constants::GA_AUTH_CODE);
    
            if (empty($auth_code)) {
                
                update_option(
                    Constants::GA_ERROR,
                    "Please enter Access Code to authenticate.");
                return false; 
            }
    
            try {   
                               
                $access_token = $client->authenticate($auth_code);

                if (isset($access_token["error_description"])) {
                    update_option(
                        Constants::GA_ERROR,
                        "<b>Authentication request has returned " .
                        $access_token["error_description"] . "</b>");  
                }
                else if (isset($access_token["scope"]) &&
                    strpos(
                        $access_token["scope"],
                        Google_Service_Analytics::ANALYTICS_READONLY) === false) {
                    update_option(
                        Constants::GA_ERROR,
                        'Please ensure you tick the <b>See and download your ' .
                        'Google Analytics data</b> box when logging into ' .
                        'Google Analytics.');
                    return false;
                }
                else if (isset($access_token["scope"]) &&
                    strpos(
                        $access_token["scope"],
                        Google_Service_Analytics::ANALYTICS_EDIT) === false) {
                    update_option(
                        Constants::GA_ERROR,
                        'Please ensure you tick the <b>Edit Google Analytics ' .
                        'management entities</b> box when logging into ' .
                        'Google Analytics.');
                    return false;
                }                

            }
            catch (Analytify_Google_Auth_Exception $e) {
                update_option(
                    Constants::GA_ERROR,
                    "Authentication request has returned an error. " .
                    "Please enter valid Access Code.");
                error_log($e->getMessage());
                return false;
            }
            catch (Exception $e) {
                update_option(
                    Constants::GA_ERROR,
                    "Authentication request has returned an error. " .
                    "Please enter valid Access Code.");
                error_log($e->getMessage());
                return false;
            }
    
            if ($access_token) {
    
                $client->setAccessToken($access_token);
    
                update_option(Constants::GA_TOKEN, $access_token);
                update_option(
                    'fiftyonedegrees_ga_auth_date',
                    date( 'l jS F Y h:i:s A' ) . date_default_timezone_get());
    
            }
            else {
                return false;
            }
        }

        return $client;
    }

    /**
     * Retrieves Google Analytics Object
	 * @param Google_Client $client
     * @return Google_Service_Analytics service service object
     */	
    public function get_google_analytics_service ($client) {
        try {
            
            // Create an authorized analytics service object.
            $service = new Google_Service_Analytics($client);
             
        }
        catch (Google_Service_Exception $e) {
            
            error_log($e->getMessage());
        }
        catch (Exception $e) {
            
            error_log($e->getMessage());
        }

        return $service;		
    }

    /**
     * Retrieves web properties list for the authorized user.
	 * @param Google_Service_Analytics $analytics_service
     * @return array properties list
     */	
    public function get_analytics_properties_list($analytics_service) {
  
        if (!get_option(Constants::GA_TOKEN)) {
            echo "You must authenticate to access your Analytics Account.";
            return;
        }
      
		try {
			// Get the list of accounts for the authorized user.
			$properties = $analytics_service->management_webproperties->listManagementWebproperties('~all');
			$propertiesList = array();
			if (count($properties->getItems()) > 0) {
				foreach ($properties->getItems() as $property) {
					$propertyId = $property->getId();
					$propertyName = $property->getName();
					$property = array();
					$property["id"] = $propertyId;
					$property["name"] = $propertyName . " (" . $propertyId . ") "; 
					array_push($propertiesList, $property);           
				}
			}
			else {
				echo 'No Properties found for this user.';
				return;
			}  
		}
		catch (Exception $e) {
			error_log($e->getMessage());
		}

        update_option(Constants::GA_PROPERTIES , $propertiesList);
    
        return $propertiesList;
    }

    /**
     * Retrieves account id for the web property being used.
	 * @param Google_Service_Analytics $analytics_service
     * @param string $trackingId
     * @return string accountId 
     */	
    public function get_account_id($analytics_service, $trackingId) {

        if (!empty($trackingId)) {

            try {
                // Get the list of accounts and web properties.
                $accounts = $analytics_service->management_accountSummaries->listManagementAccountSummaries();
                foreach ($accounts->getItems() as $account) {
                    $accountId = $account->getId();
                    foreach ($account->getWebProperties() as $property) {
                        if ($property->getId() === $trackingId) {
                            return $accountId;
                        }
                    }
                }
            }
            catch (apiServiceException $e) {
                error_log('There was an Analytics API service error ' .
                $e->getCode() . ':' . $e->getMessage());
                return "";
              
            }
            catch (apiException $e) {
                error_log('There was a general API error ' .
                $e->getCode() . ':' . $e->getMessage());

                return "";
            }
        }
        return ""; 
    }

    /**
     * Retrieves custom dimensions for the authorized user.
     * 
     * @return array array containing custom dimensions list
     * and max available custom dimension index 
     */	
    public function get_custom_dimensions() {

        $trackingId = get_option(Constants::GA_TRACKING_ID);
        $maxCustomDimIndex = get_option(Constants::GA_MAX_DIMENSIONS);
        $client = $this->authenticate();

        if ($client) {

            $service = $this->get_google_analytics_service($client);

            // Get accountId from tracking Id
            $accountId = $this->get_account_id($service, $trackingId);
            update_option(Constants::GA_ACCOUNT_ID, $accountId);

            // Get the list of custom dimensions for the web property.
            $customDimensions = $service->management_customDimensions->listManagementCustomDimensions($accountId, $trackingId);
            
            // Create a map with custom dimensions name and indices.
            $custom_dimensions_map = array();
            foreach ($customDimensions->getItems() as $customDimension) {
                $customDimensionName = $customDimension->getName();
                $customDimensionIndex = $customDimension->getIndex();
                $custom_dimensions_map[$customDimensionName] = $customDimensionIndex;
            }

            // Get Maximum Custom Dimension Index
            $maxCustomDimIndex = count($customDimensions->getItems());
            update_option(Constants::GA_MAX_DIMENSIONS, $maxCustomDimIndex);
    
        } 
        else {
            error_log("User is not authenticated.");
        }

        return array(
            "cust_dims_map" => $custom_dimensions_map,
            "max_cust_dim_index" => $maxCustomDimIndex );
    }

    /**
     * Inserts Custom Dimension into analytics account.
     * 
     * @return int number of new custom dimensions inserted.
     */	
    public function insert_custom_dimensions() {

        $calls = 0;        
        $accountId = get_option("fiftyonedegrees_ga_account_id");
        $trackingId = get_option(Constants::GA_TRACKING_ID);
        $cust_dim_map = get_option("fiftyonedegrees_ga_cust_dims_map");
        $client = $this->authenticate();

        if ($client) {

            $service = $this->get_google_analytics_service($client);

            foreach ($cust_dim_map as $dimension) {

                $custDimName = $dimension["custom_dimension_name"];
                $custDimGAIndex = $dimension["custom_dimension_ga_index"];
                $custDimIndex = $dimension["custom_dimension_index"];

                if ($custDimGAIndex === -1) {

                    $customDimension = new CustomDimension();
                    $customDimension->setName($custDimName);
                    $customDimension->setIndex($custDimIndex);
                    $customDimension->setScope(FIFTYONEDEGREES_CUSTOM_DIMENSION_SCOPE);
                    $customDimension->setActive(true);

                    try {

                        // Insert Custom Dimension in Google Analytics
                        $result = $service->management_customDimensions->insert($accountId, $trackingId, $customDimension);
                        $calls = $calls + 1;

                    }
                    catch (Exception $e) {

                        $calls = -1;
                        $jsonError = json_decode($e->getMessage(), $assoc = true);
                        update_option(
                            Constants::GA_ERROR,
                            "Could not insert Custom Dimensions in Google " .
                            "Analytics account because" .
                            $jsonError["error"]["message"]);
                        error_log($e->getMessage());
                    }
                }
            }    
        }
        else {
            error_log("User is not authenticated.");
        }  

        return $calls;
    }
}
    
