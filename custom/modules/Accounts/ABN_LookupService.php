<?php
// https://abr.business.gov.au/Documentation/DataDictionary == CRUCIAL
/**
 * According to https://www.australia.gov.au/information-and-services/money-and-tax/tax/abn-australian-business-number
 * "An Australian Business Number (ABN) is a unique 11 digit number that identifies your business
 *  to the government and community."  - [retrieved 20190807]
 *
 * This script uses the ABR-published API to retrieve the ABR's public information related to an organisation
 */
if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

/**
 * Class ABN_Lookup
 */
class ABN_Lookup extends SoapClient
{
    const WSDL = 'http://abr.business.gov.au/abrxmlsearch/ABRXMLSearch.asmx?WSDL';
    const GUID = 'eb7e518b-6a8a-4db5-a4bd-508a169ddc42';
    const INCLUDE_HISTORICAL_DETAILS = 'Y';
    private $abnSearchResult;

    /**
     * ABN_Lookup constructor.
     */
    public function __construct()
    {
        $this->abnSearchResult = new stdClass();
        $params = [
            'soap_version' => SOAP_1_1,
            'exceptions' => true,
            'trace' => 1,
            'cache_wsdl' => WSDL_CACHE_NONE,
        ];

        //the client may be sitting behind a proxy
        global $sugar_config;
        $proxy_url = isset($sugar_config['abn_lookup']['proxy_url']) ? $sugar_config['abn_lookup']['proxy_url'] : '';
        $proxy_port = isset($sugar_config['abn_lookup']['proxy_port']) ? $sugar_config['abn_lookup']['proxy_port'] : '';
        if (!empty($proxy_url) && !empty($proxy_port)) {
            $params['proxy_host'] = trim($proxy_url);
            $params['proxy_port'] = trim($proxy_port);
            if (isset($sugar_config['abn_lookup']['proxy_username'])
                && !empty($sugar_config['abn_lookup']['proxy_username'])
                && isset($sugar_config['abn_lookup']['proxy_password'])
                && !empty($sugar_config['abn_lookup']['proxy_password'])
            ) {
                $params['proxy_login'] = isset($sugar_config['abn_lookup']['proxy_username']) ? trim($sugar_config['abn_lookup']['proxy_username']) : '';
                $params['proxy_password'] = isset($sugar_config['abn_lookup']['proxy_password']) ? trim($sugar_config['abn_lookup']['proxy_password']) : '';
            }
        }
        //end of proxy accommodation

        try {
            parent::__construct(self::WSDL, $params);
        } catch (SoapFault $e) {
            $GLOBALS['log']->info(
                __FILE__. ", method " . __CLASS__. "::" . __METHOD__ . "() line " . __LINE__
                . ": SOAP Error " . print_r($e->getMessage(), true)
            );
        }

    }

    /**
     * @param        $abn
     * @param string $historical
     *
     * @return mixed
     */
    public function searchByABN($abn,  $historical = self::INCLUDE_HISTORICAL_DETAILS)
    {
        $params = new stdClass();
        $params->searchString = $abn;
        $params->includeHistoricalDetails = $historical;
        $params->authenticationGuid = self::GUID;
        $this->abnSearchResult = $this->ABRSearchByABN($params);
        return ($this->abnSearchResult);
    }

    /**
     * Prepare a JSON object containing the latest search data retrieved fom ABR.
     *
     * The output of this method is intended to provide convenience for using & displaying search results online.
     * For example property values from the returned object may be used to populate form fields or drive page logic.
     *
     * @return mixed
     */
    public function prepareToShow()
    {
        //Step through the data dictionary https://abr.business.gov.au/Documentation/DataDictionary & populate $result
        $result = new stdClass();

        if (
            property_exists($this->abnSearchResult,"ABRPayloadSearchResults") &&
            isset($this->abnSearchResult->ABRPayloadSearchResults) &&
            property_exists($this->abnSearchResult->ABRPayloadSearchResults, "response") &&
            isset($this->abnSearchResult->ABRPayloadSearchResults->response)
        ) {
            $result = $this->abnSearchResult->ABRPayloadSearchResults->response;
            //Note: expect that usageStatement          is always populated.
            //Note: expect that dateRegisterLastUpdated is always populated.
            //Note: expect that dateTimeRetrieved       is always populated.

            if (
                property_exists($this->abnSearchResult->ABRPayloadSearchResults->response,"businessEntity") &&
                isset($this->abnSearchResult->ABRPayloadSearchResults->response->businessEntity)
            ) {
                //flatten the structure, for convenience
                $entity  = $this->abnSearchResult->ABRPayloadSearchResults->response->businessEntity;

                //ABN may have historical dates
                if (property_exists($entity, "ABN") && isset($entity->ABN)) {
                    $this->prepareDataABN($entity);
                }

                //Entity name may have historical data
                if (property_exists($entity,"mainName") && isset($entity->mainName)) {
                    $this->prepareDataEntityName($entity);
                }

                //ABN Status may have historical data
                if (property_exists($entity,"entityStatus") && isset($entity->entityStatus)) {
                    $this->prepareDataEntityStatus($entity);
                }

                //Entity Type does not have historical data  :) There is only 1 entityType record.
                //Use it to set all the CRM fields for current Entity Type

                //Trading Name may have historical data
                if (property_exists($entity,"mainTradingName") && isset($entity->mainTradingName) ) {
                    $this->prepareDataTradingName($entity);
                }

                //Main Business Physical Address may have historical data
                if (property_exists($entity,"mainBusinessPhysicalAddress") && isset($entity->mainBusinessPhysicalAddress) ) {
                    $this->prepareDataMainBusinessPhysicalAddress($entity);
                }

                //ASICNumber does not have historical data
                // there is only 1 ASICNumber record. use it to set the CRM field for current ASIC Number

                //GST may have historical data
                if (property_exists($entity,"goodsAndServicesTax") && isset($entity->goodsAndServicesTax)) {
                    $this->prepareDataGST($entity);
                }

                unset($result->businessEntity);
                $result->businessEntity = $entity;
                $result->hash = $this->hash($entity);

            }/* else {
                // NO BUSINESS ENTITY FOUND
            }*/
        }/* else {
            //NO RESPONSE FOUND
        }*/

        return $result;
    }

    /**
     * @param $a
     * @param $b
     *
     * @return int|lt
     */
    private function compare_ABN_dates_ascending($a, $b)
    {
        return strcmp($a->replacedFrom, $b->replacedFrom);
    }

    /**
     * @param $b
     * @param $a
     *
     * @return int|lt
     */
    private function compare_effectiveFrom_dates_descending($b, $a)
    {
        return strcmp($a->effectiveFrom, $b->effectiveFrom);
    }

    /**
     * @param $result
     */
    private function prepareDataABN(&$entity)
    {
        if (is_object($entity->ABN)) {
            // there is only 1 ABN record. It will be used to set all the CRM fields for the current ABN
        } elseif (is_array($entity->ABN)) {

            //sort the array ASC by the replaced-from date (0001-01-01).
            usort($entity->ABN, array($this, "compare_ABN_dates_ascending"));

            //stringify and concatenate all the objects EXCEPT the first.
            //this string will become the "historical ABN data" field in the CRM
            $entity->historicalABN_data = "";

            for ($i = 1; $i < count($entity->ABN); $i++) {
                $entity->historicalABN_data .=
                    "(".
                    "record:$i,".
                    "ABN:"         .$entity->ABN[$i]->identifierValue.",".
                    "isCurrent:"   .$entity->ABN[$i]->isCurrentIndicator.",".
                    "replacedfrom:".$entity->ABN[$i]->replacedFrom.
                    ")";
            }

            //the most recent ABN data is to be made available for the current ABN fields
            $identifierValue   = $entity->ABN[0]->identifierValue;
            $isCurrentIndicator= $entity->ABN[0]->isCurrentIndicator;
            $replacedFrom      = $entity->ABN[0]->replacedFrom;

            //(re) build the output object for use by the web page
            unset($entity->ABN);
            $entity->ABN = new stdClass();
            $entity->ABN->identifierValue    = $identifierValue;
            $entity->ABN->isCurrentIndicator = $isCurrentIndicator;
            $entity->ABN->replacedFrom       = $replacedFrom;
        }

    }

    /**
     * @param $result
     */
    private function prepareDataEntityName(&$entity)
    {
        if (is_object($entity->mainName)) {
            // there is only 1 mainName record. It will be used to set all the CRM fields for the current Entity Name
        } elseif (is_array($entity->mainName)) {

            //sort the array DESC by the effective-from date.
            usort($entity->mainName, array($this, "compare_effectiveFrom_dates_descending"));

            //stringify and concatenate all the objects EXCEPT the first.
            //this string will become the "historical Entity names data" field in the CRM
            $entity->historicalEntityNamesData = "";

            for ($i = 1; $i < count($entity->mainName); $i++) {
                $entity->historicalEntityNamesData.=
                    "(".
                    "record:$i,".
                    "organisationName:".$entity->mainName[$i]->organisationName .",".
                    "effectiveFrom:"   .$entity->mainName[$i]->effectiveFrom .
                    ")";
            }

            //the most recent Entity Name data is to be made available for the current Entity Name fields of the CRM record
            $organisationName = $entity->mainName[0]->organisationName;
            $effectiveFrom    = $entity->mainName[0]->effectiveFrom;

            //(re) build the output object for use by the web page
            unset($entity->mainName);
            $entity->mainName = new stdClass();
            $entity->mainName->organisationName = $organisationName;
            $entity->mainName->effectiveFrom    = $effectiveFrom;
        }

    }

    /**
     * @param $result
     */
    private function prepareDataEntityStatus(&$entity)
    {
        if (is_object($entity->entityStatus)) {
            // there is only 1 entityStatus record. use it to set all the CRM fields for current ABN Status
        } elseif (is_array($entity->entityStatus)) {

            //sort the array DESC by the effective-from date.
            usort($entity->entityStatus, array($this, "compare_effectiveFrom_dates_descending"));

            //stringify and concatenate all the objects EXCEPT the first.
            //this string will become the "historical ABN Status data" field in the CRM
            $entity->historicalEntityStatusData = "";

            for ($i = 1; $i < count($entity->entityStatus); $i++) {
                $entity->historicalEntityStatusData .=
                    "(".
                    "record:$i,".
                    "entityStatusCode:".$entity->entityStatus[$i]->entityStatusCode.",".
                    "effectiveFrom:"   .$entity->entityStatus[$i]->effectiveFrom.",".
                    "effectiveTo:"     .$entity->entityStatus[$i]->effectiveTo.
                    ")";
            }

            //the most recent ABN status data is to be made available for the current ABN Status fields of the CRM record
            $entityStatusCode = $entity->entityStatus[0]->entityStatusCode;
            $effectiveFrom    = $entity->entityStatus[0]->effectiveFrom;
            $effectiveTo      = $entity->entityStatus[0]->effectiveTo;

            //(re)build the output object for use by the web page
            unset($entity->entityStatus);
            $entity->entityStatus = new stdClass();
            $entity->entityStatus->entityStatusCode = $entityStatusCode ;
            $entity->entityStatus->effectiveFrom    = $effectiveFrom;
            $entity->entityStatus->effectiveTo      = $effectiveTo;
        }
    }


    /**
     * @param $result
     */
    private function prepareDataTradingName(&$entity)
    {
        if (is_object($entity->mainTradingName)) {
            // there is only 1 mainTradingName record. use it to set all the CRM fields for current Trading name
        } elseif (is_array($entity->mainTradingName)) {

            //sort the array DESC by the effective-from date.
            usort($entity->mainTradingName, array($this, "compare_effectiveFrom_dates_descending"));

            //stringify and concatenate all the objects EXCEPT the first.
            //this string will become the "historical Trading Name data" field in the CRM
            $entity->historicalMainTradingNameData = "";

            for ($i = 1; $i < count($entity->mainTradingName); $i++) {
                $entity->historicalMainTradingNameData .=
                    "(".
                    "record:$i,".
                    "organisationName:".$entity->mainTradingName[$i]->organisationName.",".
                    "effectiveFrom:"   .$entity->mainTradingName[$i]->effectiveFrom.",".
                    "effectiveTo:"     .$entity->mainTradingName[$i]->effectiveTo.
                    ")";
            }

            //the most recent Trading Name data is to be made available for the current Trading Name fields of the CRM record
            $organisationName = $entity->mainTradingName[0]->organisationName;
            $effectiveFrom    = $entity->mainTradingName[0]->effectiveFrom;
            $effectiveTo      = $entity->mainTradingName[0]->effectiveTo;

            //(re)build the output object for use by the web page
            unset($entity->mainTradingName);
            $entity->mainTradingName = new stdClass();
            $entity->mainTradingName->organisationName = $organisationName ;
            $entity->mainTradingName->effectiveFrom    = $effectiveFrom;
            $entity->mainTradingName->effectiveTo      = $effectiveTo;
        }
    }

    /**
     * @param $result
     */
    private function prepareDataGST(&$entity)
    {
        if (is_object($entity->goodsAndServicesTax)) {
            // there is only 1 goodsAndServicesTax record. use it to set this record's CRM fields for current GST
        } elseif (is_array($entity->goodsAndServicesTax)) {

            //sort the array DESC by the effective-from date.
            usort($entity->goodsAndServicesTax, array($this, "compare_effectiveFrom_dates_descending"));

            //stringify and concatenate all the objects EXCEPT the first.
            //this string will become the "historical goodsAndServicesTax data" field in the CRM
            $entity->historicalGST_data = "";

            for ($i = 1; $i < count($entity->goodsAndServicesTax); $i++) {
                $entity->historicalGST_data .=
                    "(".
                    "record:$i,".
                    "effectiveFrom:"   .$entity->goodsAndServicesTax[$i]->effectiveFrom.",".
                    "effectiveTo:"     .$entity->goodsAndServicesTax[$i]->effectiveTo.
                    ")";
            }

            //the most recent GST data is to be made available for the current GST fields of the CRM record
            $effectiveFrom    = $entity->goodsAndServicesTax[0]->effectiveFrom;
            $effectiveTo      = $entity->goodsAndServicesTax[0]->effectiveTo;

            //(re)build the output object for use by the web page
            unset($entity->goodsAndServicesTax);
            $entity->goodsAndServicesTax = new stdClass();
            $entity->goodsAndServicesTax->effectiveFrom    = $effectiveFrom;
            $entity->goodsAndServicesTax->effectiveTo      = $effectiveTo;
        }
    }

    private function prepareDataMainBusinessPhysicalAddress(&$entity)
    {
        if (is_object($entity->mainBusinessPhysicalAddress)) {
            // there is only 1 mainBusinessPhysicalAddress record. use it to set this record's CRM fields
        } elseif (is_array($entity->mainBusinessPhysicalAddress)) {

            //sort the array DESC by the effective-from date.
            usort($entity->mainBusinessPhysicalAddress, array($this, "compare_effectiveFrom_dates_descending"));

            //stringify and concatenate all the objects EXCEPT the first.
            //this string will become the "historical mainBusinessPhysicalAddress data" field in the CRM
            $entity->historicalMainBusinessPhysicalAddressData = "";

            for ($i = 1; $i < count($entity->mainBusinessPhysicalAddress); $i++) {
                $entity->historicalMainBusinessPhysicalAddressData .=
                    "(".
                    "record:$i,".
                    "stateCode:"     .$entity->mainBusinessPhysicalAddress[$i]->stateCode.",".
                    "postcode:"      .$entity->mainBusinessPhysicalAddress[$i]->postcode.",".
                    "effectiveFrom:" .$entity->mainBusinessPhysicalAddress[$i]->effectiveFrom.",".
                    "effectiveTo:"   .$entity->mainBusinessPhysicalAddress[$i]->effectiveTo.
                    ")";
            }

            //the most recent mainBusinessPhysicalAddress data is to be made available for the current fields of the record
            $stateCode     = $entity->mainBusinessPhysicalAddress[0]->stateCode;
            $postcode      = $entity->mainBusinessPhysicalAddress[0]->postcode;
            $effectiveFrom = $entity->mainBusinessPhysicalAddress[0]->effectiveFrom;
            $effectiveTo   = $entity->mainBusinessPhysicalAddress[0]->effectiveTo;

            //(re)build the output object for use by the web page
            unset($entity->mainBusinessPhysicalAddress);
            $entity->mainBusinessPhysicalAddress = new stdClass();
            $entity->mainBusinessPhysicalAddress->statusCode    = $stateCode;
            $entity->mainBusinessPhysicalAddress->postcode      = $postcode;
            $entity->mainBusinessPhysicalAddress->effectiveFrom = $effectiveFrom;
            $entity->mainBusinessPhysicalAddress->effectiveTo   = $effectiveTo;
        }
    }

    /**
     * @param $entityObject
     *
     * @return int
     */
    private function hash(&$entityObject)
    {
        if (is_object($entityObject) && isset($entityObject)) {
            $businessEntityJSON = json_encode($entityObject);
            return crc32($businessEntityJSON);
        }
    }
}
