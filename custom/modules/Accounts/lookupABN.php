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

require "custom/modules/Accounts/ABN_LookupService.php";
const ABN_STRING_LENGTH = 11;
const INVALID_SEARCH    = "No data found.  Note that an ABN is a unique 11 digit number.";
$abnJSON = "";
$preparedObject = new stdClass();

//validate the incoming POST: expect exactly 1 array element, named 'abnValue'. Else fail.
$isValidPOST = $_POST && array_key_exists('abnValue', $_POST) && count($_POST) == 1;

if ($isValidPOST) {
    $abn = preg_replace('/\s+/', '', $_POST['abnValue']);

    //validate the ABN obtained from $_POST: expect exactly 11 digits [ + spaces] only. Else fail.
    $isValidABN = ctype_digit($abn) && strlen($abn) == ABN_STRING_LENGTH;

    if ($isValidABN) {

        //get abn_record from ABR via SOAP call
        try {
            $bureaucrat = new ABN_Lookup();
            $x = $bureaucrat->searchByAbn($abn);
            $GLOBALS['log']->debug("\n\n======================================SOAP ABN SEARCH results ===================== \n\n".print_r ($x, true));
            $preparedObject = $bureaucrat->prepareToShow();
        } catch (Exception $e) {
            echo $e->getMessage();
        }
        $abnJSON = json_encode($preparedObject);

    } else {
         $preparedObject->Message = INVALID_SEARCH;
    }
}

$hash = crc32($abnJSON);

if ($hash && empty($preparedObject->Message)) {
    $preparedObject->hash = "".$hash;
}

echo json_encode($preparedObject);
