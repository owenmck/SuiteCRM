<?php //custom/modules/Accounts/lookupABN.php
/**
 * According to https://www.australia.gov.au/information-and-services/money-and-tax/tax/abn-australian-business-number
 * "An Australian Business Number (ABN) is a unique 11 digit number that identifies your business
 *  to the government and community."  - [retrieved 20190807]
 *
 * This script is intended for use as a target for a POST jQuery.ajax() call which expects a JSON response.
 * Its task is, 'given an ABN string in a $_POST array, echo a JSON-encoded string of the ABR record for that business'.
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

const ABN_STRING_LENGTH = 11;
$abn_record = "";
$array_of_returned_stuff=[];

//validate the incoming POSTED parameters: expect only 1 param and it only contains 11 digits [+ spaces].  Else fail.
if ($_POST && array_key_exists('abnValue', $_POST) && count($_POST) == 1) {
    $abn_sans_space = preg_replace('/\s+/', '', $_POST['abnValue']);

    if ( ctype_digit($abn_sans_space) && strlen($abn_sans_space) == ABN_STRING_LENGTH) {
        //get abn_record from ABR via SOAP call

        //dummy content (Google's ABR record)
        $array_of_returned_stuff=[
            "Abn" => "33 102 417 032",
            "AbnStatus" => "Active",
            "AddressDate" => "2018-11-22",
            "AddressPostcode" => "2009",
            "AddressState" => "NSW"
        ];
        //end dummy content

        //Sort fields so they are always returned in lex. This matters for generating the hash.
        ksort($array_of_returned_stuff);
        $abn_record = json_encode($array_of_returned_stuff );
    }
}

$hash = crc32($abn_record);

if ($hash && empty($array_of_returned_stuff['Message'])) {
    $array_of_returned_stuff['hash'] = "".$hash;
}

echo json_encode($array_of_returned_stuff);

