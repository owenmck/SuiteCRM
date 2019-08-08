<?php
// https://abr.business.gov.au/Documentation/DataDictionary == CRUCIAL
/**
 * According to https://www.australia.gov.au/information-and-services/money-and-tax/tax/abn-australian-business-number
 * "An Australian Business Number (ABN) is a unique 11 digit number that identifies your business
 *  to the government and community."  - [retrieved 20190807]
 *
 * This script uses the ABR-published APi to .....
 * Its task is, .....
 */
if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

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
            $GLOBALS['log']->debug('SOAPP Error ' . print_r($e->getMessage(), true));
        }
    }

    public function searchByAbn($abn,  $historical = self::INCLUDE_HISTORICAL_DETAILS)
    {
        $params = new stdClass();

        $params->searchString = $abn;
        $params->includeHistoricalDetails = $historical;
        $params->authenticationGuid = self::GUID;
        return ($this->abnSearchResult = $this->ABRSearchByABN($params));
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
            $result->response = $this->abnSearchResult->ABRPayloadSearchResults->response;
            //Note: expect that usageStatement          is always populated.
            //Note: expect that dateRegisterLastUpdated is always populated.
            //Note: expect that dateTimeRetrieved       is always populated.

            if (
                property_exists($result->response,"businessEntity") &&
                isset($result->response->businessEntity)
            ) {
                //flatten the structure
                $result->businessEntity = $result->response->businessEntity;

                //ABN may have historical dates
                if (
                    property_exists($result->businessEntity,"ABN") &&
                    isset($result->businessEntity->ABN)
                ) {
                    if (is_object($result->businessEntity->ABN)) {
                        // there is only 1 ABN record. It will be used to set all the CRM fields for the current ABN

                    } elseif (is_array($result->businessEntity->ABN)) {
                        //sort the array ASC by the replaced-from date (0001-01-01).
                        usort($result->businessEntity->ABN, array($this, "compare_ABN_dates_ascending"));

                        //stringify and concatenate all the objects EXCEPT the first, then set this string to the -------- "historical ABN data" field in the CRM-------------
                        $result->businessEntity->historicalABN_data = "";

                        for ($i = 1; $i < count($result->businessEntity->ABN); $i++) {
                            $result->businessEntity->historicalABN_data .=
                                "(".
                                "record:$i,".
                                "ABN:"         .$result->businessEntity->ABN[$i]->identifierValue.",".
                                "isCurrent:"   .$result->businessEntity->ABN[$i]->isCurrentIndicator.",".
                                "replacedfrom:".$result->businessEntity->ABN[$i]->replacedFrom.
                                ")";
                        }

                        //set the content from the most recent one to the current ABN fields ... (tricky) Actually do this last!!!
                        $result->businessEntity->ABN->identifierValue    = $result->businessEntity->ABN[0]->identifierValue;
                        $result->businessEntity->ABN->isCurrentIndicator = $result->businessEntity->ABN[0]->isCurrentIndicator;
                        $result->businessEntity->ABN->replacedFrom       = $result->businessEntity->ABN[0]->replacedFrom;
                    }
                }

                //Entity name may have historical data
                if (
                    property_exists($result->businessEntity,"mainName") &&
                    isset($result->businessEntity->mainName)
                ) {
                    if (is_object($result->businessEntity->mainName)) {
                        // there is only 1 mainName record. It will be used to set all the CRM fields for the current Entity Name

                    } elseif (is_array($result->businessEntity->mainName)) {
                        //sort the array DESC by the effective-from date.
                        usort($result->businessEntity->mainName, array($this, "compare_effectiveFrom_dates_descending"));

                        //stringify and concatenate all the objects EXCEPT the first, then set this string to the -------- "historical Entity names data" field in the CRM-------------
                        $result->businessEntity->historicalEntityNamesData = "";

                        for ($i = 1; $i < count($result->businessEntity->mainName); $i++) {
                            $result->businessEntity->historicalEntityNamesData.=
                                "(".
                                "record:$i,".
                                "organisationName:".$result->businessEntity->mainName[$i]->organisationName .",".
                                "effectiveFrom:"   .$result->businessEntity->mainName[$i]->effectiveFrom .
                                ")";
                        }

                        //set the content from the most recent one to the CRM's current Entity Name fields
                        $result->businessEntity->mainName->organisationName = $result->businessEntity->mainName[0]->organisationName;
                        $result->businessEntity->mainName->effectiveFrom    = $result->businessEntity->mainName[0]->effectiveFrom;
                    }
                }

                //ABN Status may have historical data
                if (
                    property_exists($result->businessEntity,"entityStatus") &&
                    isset($result->businessEntity->entityStatus)
                ) {
                    if (is_object($result->businessEntity->entityStatus)) {
                        // there is only 1 entityStatus record. use it to set all the CRM fields for current ABN Status

                    } elseif (is_array($result->businessEntity->entityStatus)) {
                        //sort the array DESC by the effective-from date.
                        usort($result->businessEntity->entityStatus, array($this, "compare_effectiveFrom_dates_descending"));

                        //stringify and concatenate all the objects EXCEPT the first, then set this string to the -------- "historical ABN Status data" field in the CRM-------------
                        $result->businessEntity->historicalEntityStatusData = "";

                        for ($i = 1; $i < count($result->businessEntity->entityStatus); $i++) {
                            $result->businessEntity->historicalEntityStatusData .=
                                "(".
                                "record:$i,".
                                "entityStatusCode:".$result->businessEntity->entityStatus[$i]->entityStatusCode.",".
                                "effectiveFrom:"   .$result->businessEntity->entityStatus[$i]->effectiveFrom.",".
                                "effectiveTo:"     .$result->businessEntity->entityStatus[$i]->effectiveTo.
                                ")";
                        }

                        //set the content from the most recent one to the current ABN Status fields of the CRM record
                        $result->businessEntity->entityStatus->entityStatusCode = $result->businessEntity->entityStatus[0]->entityStatusCode;
                        $result->businessEntity->entityStatus->effectiveFrom    = $result->businessEntity->entityStatus[0]->effectiveFrom;
                        $result->businessEntity->entityStatus->effectiveTo      = $result->businessEntity->entityStatus[0]->effectiveTo;
                    }
                }

                //Entity Type does not have historical data  :)
                // there is only 1 entityType record. use it to set all the CRM fields for current Entity Type

                //Trading Name may have historical data
                if (
                    property_exists($result->businessEntity,"mainTradingName") &&
                    isset($result->businessEntity->mainTradingName)
                ) {
                    if (is_object($result->businessEntity->mainTradingName)) {
                        // there is only 1 mainTradingName record. use it to set all the CRM fields for current Trading name

                    } elseif (is_array($result->businessEntity->mainTradingName)) {
                        //sort the array DESC by the effective-from date.
                        usort($result->businessEntity->mainTradingName, array($this, "compare_effectiveFrom_dates_descending"));

                        //stringify and concatenate all the objects EXCEPT the first, then set this string to the -------- "historical Trading Name data" field in the CRM-------------
                        $result->businessEntity->historicalMainTradingNameData = "";

                        for ($i = 1; $i < count($result->businessEntity->mainTradingName); $i++) {
                            $result->businessEntity->historicalMainTradingNameData .=
                                "(".
                                "record:$i,".
                                "organisationName:".$result->businessEntity->mainTradingName[$i]->organisationName.",".
                                "effectiveFrom:"   .$result->businessEntity->mainTradingName[$i]->effectiveFrom.",".
                                "effectiveTo:"     .$result->businessEntity->mainTradingName[$i]->effectiveTo.
                                ")";
                        }

                        //set the content from the most recent one to the current Trading Name fields in the CRM record
                        $result->businessEntity->mainTradingName->organisationName = $result->businessEntity->mainTradingName[0]->organisationName;
                        $result->businessEntity->mainTradingName->effectiveFrom    = $result->businessEntity->mainTradingName[0]->effectiveFrom;
                        $result->businessEntity->mainTradingName->effectiveTo      = $result->businessEntity->mainTradingName[0]->effectiveTo;
                    }
                }

                //ASICNumber does not have historical data ? ?????????????????????????????????????????????????????????
                // there is only 1 ASICNumber record. use it to set the CRM field for current ASIC Number

                //GST may have historical data
                if (
                    property_exists($result->businessEntity,"goodsAndServicesTax") &&
                    isset($result->businessEntity->goodsAndServicesTax)
                ) {
                    if (is_object($result->businessEntity->goodsAndServicesTax)) {
                        // there is only 1 goodsAndServicesTax record. use it to set this record's CRM fields for current GST

                    } elseif (is_array($result->businessEntity->goodsAndServicesTax)) {
                        //sort the array DESC by the effective-from date.
                        usort($result->businessEntity->goodsAndServicesTax, array($this, "compare_effectiveFrom_dates_descending"));

                        //stringify and concatenate all the objects EXCEPT the first, then set this string to the -------- "historical goodsAndServicesTax data" field in the CRM-------------
                        $result->businessEntity->historicalGST_data = "";

                        for ($i = 1; $i < count($result->businessEntity->goodsAndServicesTax); $i++) {
                            $result->businessEntity->historicalGST_data .=
                                "(".
                                "record:$i,".
                                "effectiveFrom:"   .$result->businessEntity->goodsAndServicesTax[$i]->effectiveFrom.",".
                                "effectiveTo:"     .$result->businessEntity->goodsAndServicesTax[$i]->effectiveTo.
                                ")";
                        }

                        //set the content from the most recent one to the current GST fields in the CRM record
                        $result->businessEntity->goodsAndServicesTax->effectiveFrom = $result->businessEntity->goodsAndServicesTax[0]->effectiveFrom;
                        $result->businessEntity->goodsAndServicesTax->effectiveTo   = $result->businessEntity->goodsAndServicesTax[0]->effectiveTo;
                    }
                }

            } else {
                // NO BUSINESS ENTITY FOUND
            }

        } else {
            //NO RESPONSE FOUND
        }

        //Sort fields so they are always returned in lex. This matters for generating the hash.
        //ksort($object_of_returned_stuff);

        /*
         * The ABR Data dictionary is  https://abr.business.gov.au/Documentation/DataDictionary == CRUCIAL
         * @TODO: verify with customer the following mappings from ABN data dictionary into the Accounts bean.
         *
         *   ----------------- C U R R E N T ---------------------------------------------------------------------------         *
         *  ABN                         businessEntity.ABN.identifierValue
         *                              businessEntity.ABN.isCurrentIndicator
         *                              businessEntity.ABN.replacedFrom
         * Entity name------~*~---------businessEntity.mainName.organisationName
         *                              businessEntity.mainName.effectiveFrom
         * ABN Status-------~*~---------businessEntity.entityStatus.entityStatusCode
         *                              businessEntity.entityStatus.effectiveFrom
         * Entity Type                  businessEntity.entityType.entityDescription
         *                              businessEntity.entityType.entityTypeCode
         * Trading name*----~*~---------businessEntity.mainTradingName.organisationName
         *                              businessEntity.mainTradingName.effectiveFrom
         * ASIC number                  businessEntity.ASICNumber
         *
         * *** NOTE *** ---~*---- == Items for which the logic of reading the search response data needs to be further unpacked (server-side) in case there are multiple (historical) values
         *
         *   ----------------- H I S T O R I C A L  --------------------------------------------------------------------
         * Entity name and Trading name(s)
         * Goods & Services Tax (GST)
         *
         */
        return $result->response;
    }

    private function compare_ABN_dates_ascending($a, $b)
    {
        return strcmp($a->replacedFrom, $b->replacedFrom);
    }

    private function compare_effectiveFrom_dates_descending($b, $a)
    {
        return strcmp($a->effectiveFrom, $b->effectiveFrom);
    }


}