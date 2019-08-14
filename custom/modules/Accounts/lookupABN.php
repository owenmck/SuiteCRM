<?php //custom/modules/Accounts/lookupABN.php
/**
 * According to https://www.australia.gov.au/information-and-services/money-and-tax/tax/abn-australian-business-number
 * "An Australian Business Number (ABN) is a unique 11 digit number that identifies your business
 *  to the government and community."  - [retrieved 20190807]
 *
 * This script is intended for use as the target of a POST jQuery.ajax() call which expects a JSON response.
 * Its task is, 'given an ABN string in a $_POST array, echo a JSON-encoded string of the ABR record for that business'.
 */
if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

// SOAP request happens via an 'ABN_Lookup' object
require "custom/modules/Accounts/ABN_LookupService.php";

const ABN_STRING_LENGTH = 11;
const INVALID_SEARCH    = "No data found.  Note that an ABN is a unique 11 digit number.";

$abnJSON = "";
$preparedObject = new stdClass();

//validate the incoming POST: expect exactly 1 array element, named 'abnValue'. Else fail.
$isValidPOST = $_POST && array_key_exists('abnValue', $_POST) && count($_POST) == 1;

if ($isValidPOST) {
    $abn = preg_replace('/\s+/', '', $_POST['abnValue']);

    //further validate the ABN obtained from $_POST: expect exactly 11 digits [ + spaces] only. Else fail.
    $isValidABN = ctype_digit($abn) && strlen($abn) == ABN_STRING_LENGTH;

    if ($isValidABN) {

        //get abn_record from ABR
        try {
            $bureaucrat = new ABN_Lookup();
            $bureaucrat->searchByABN($abn);
            $preparedObject = $bureaucrat->prepareToShow();
        } catch (Exception $e) {
            $preparedObject->Message = $e->getMessage();
        }

    } else {
        $preparedObject->Message = INVALID_SEARCH;
    }
}

echo json_encode($preparedObject);

/**
Useful truthful ABN test cases
==============================
Google                              33 102 417 032
NEC                                 91 081 975 484

ABN test cases per https://abr.business.gov.au/Documentation/WebServiceResponse, retrieved 20190813
=======================================================================================================
Description                         ABN
--------------------------------------------------
Suppressed ABN                      34 241 177 887
Replaced ABN                        30 613 501 612
Re-issued ABN                       49 093 669 660
Multiple addresses                  33 531 321 789
Multiple GST status                 76 093 555 992
Multiple ABN status                 53 772 093 958
Many name types                     85 832 766 990
Main DGR status                     56 006 580 883
DGR funds with historical names     78 345 431 247
Tax concession information          48 212 321 102
Superannuation fund                 12 586 695 715
 */