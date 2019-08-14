//custom/modules/Accounts/js/editView.js
var jQuery;
var $;
var $abnInput = $('#abn_c');
var $hashInput = $('#abn_details_hash_c');

(function($) {
    "use strict";
    function initialiseValidationButton() {
        var $button = $('<button type="button" disabled="disabled" class="button abn-validation-button" style="float:right; margin-right:10%;">Validation</button>');
        var $messageSpace = $('<div id="abn-validation-message" class = "abn-validation-message"></div>');

        $abnInput.width('35%');

        $abnInput.parent().find('.abn-validation-button').remove();
        $abnInput.parent().append($button);

        $abnInput.parent().find('.abn-validation-message').remove();
        $abnInput.parent().append($messageSpace);

        /* usual theme override does not seem to work */
        $hashInput.prop('readonly', true);
        $hashInput.addClass('readonly');
        $hashInput.css("background", "#cccccc");
        $hashInput.css("border", "1px solid #e2e7eb");
        $hashInput.css("color", "#4e4f51");

        $button.on('click', function () {
            abnInitialise($messageSpace);
            abnLookup();
        });

        $abnInput.on('keyup', function(){
            $button.prop('disabled', !$abnInput.val().length);
        }).trigger('keyup');
    }
    initialiseValidationButton();
})(jQuery);

/**
 * Return value of field
 */
function getFieldValue(fieldId)
{
    "use strict";
    var oField = document.getElementById(fieldId);

    if (oField) {
        return oField.value;
    }
    else {
        return '';
    }
}

/**
 * populate field with value
 */
function setFieldValue(fieldId, value)
{
    "use strict";
    var oField = document.getElementById(fieldId);

    if (oField) {
        oField.value = value;
    }
}

/**
 * Use web service to lookup details of the ABN
 */
function abnLookup()
{
    "use strict";
    var abn = getFieldValue('abn_c');

    clearValidationMessages();
    jQuery.ajax(
        {
            url: "index.php?entryPoint=abr",
            method: "POST",
            dataType: 'json',
            data: {"abnValue": abn},

            success: function (data) {
                abnCallback(data);
            }
        }
    );
}

/**
 * Clear the validation message before next event.
 */
function clearValidationMessages()
{
    "use strict";
    var validationMessages = document.querySelectorAll(".validation-message");

    for (var i = 0; i < validationMessages.length; i++) {
        validationMessages[i].innerHTML = "";
    }
}

/**
 * Call back function
 */
function abnCallback (abnData)
{
    "use strict";
    if (typeof abnData.Message === 'undefined' || abnData.Message.length === 0) {
        setFieldValue('abn_details_hash_c', abnData.hash);

        /*
         * The ABR Data dictionary is  https://abr.business.gov.au/Documentation/DataDictionary == CRUCIAL
         * @TODO: verify with customer the following mappings from ABN data dictionary into the Accounts bean.
         *
         *  abnData.usageStatement
         *  abnData.dateRegisterLastUpdated
         *  abnData.dateTimeRetrieved
         *
         *   ----------------- C U R R E N T ---------------------------------------------------------------------------
         *  ABN---------~*~-------------abnData.businessEntity.ABN.identifierValue
         *                              abnData.businessEntity.ABN.isCurrentIndicator
         *                              abnData.businessEntity.ABN.replacedFrom
         * Entity name------~*~---------abnData.mainName.organisationName
         *                              abnData.mainName.effectiveFrom
         * ABN Status-------~*~---------abnData.businessEntity.entityStatus.entityStatusCode
         *                              abnData.businessEntity.entityStatus.effectiveFrom
         * Entity Type                  abnData.businessEntity.entityType.entityDescription
         *                              abnData.businessEntity.entityType.entityTypeCode
         * Trading name*----~*~---------abnData.businessEntity.mainTradingName.organisationName
         *                              abnData.businessEntity.mainTradingName.effectiveFrom
         * ASIC number                  abnData.businessEntity.ASICNumber
         *
         * *** NOTE *** ---~*---- == Items for which the logic of reading the search response data needs to be further
         *                           unpacked (server-side) in case there are multiple (historical) values.
         *
         *   ----------------- H I S T O R I C A L  also includes ------------------------------------------------------
         * Entity name and Trading name(s)
         * Goods & Services Tax (GST)
         *
         * @TODO: use Studio to add necessary fields (what is best practice for adding custom fields in SuiteCRM?).
         * @TODO: possibly use lots of calls to setFieldValue('abn_details_hash_c', abnData.<Attribute>);
         */

        /*temporary hack - simply dump the contents in human-readable format into the html. */
        var output;
        output  ="<strong>Validated</strong><br>";
        //output +="<u>Usage Statement</u> : "         + abnData.usageStatement          + "<br>";
        output +="<u>dateRegisterLastUpdated</u> : " + abnData.dateRegisterLastUpdated + "<br>";
        output +="<u>dateTimeRetrieved</u> : "       + abnData.dateTimeRetrieved;

        output +="<div style='border: solid thin green;'>";

        output +="<u>recordLastUpdatedDate :</u> ";
        output +=abnData.businessEntity.recordLastUpdatedDate;

        output +="<br>";
        output +="<u>ABN</u> : ";
        output +=abnData.businessEntity.ABN.identifierValue;
        output +=" ( isCurrent: " + abnData.businessEntity.ABN.isCurrentIndicator;
        if (abnData.businessEntity.ABN.replacedFrom === '0001-01-01') {
            output +="";
        } else {
            output +=" - replaced " + abnData.businessEntity.ABN.replacedFrom;
        }
        output += ")";

        output +="<br>";
        output +="<u>Entity name</u> : ";
        if (typeof abnData.businessEntity.mainName === 'undefined' ||
            typeof abnData.businessEntity.mainName.organisationName === 'undefined' ||
            abnData.businessEntity.mainName.organisationName.length === 0
        ) {
            output += "not stated";
        } else {
            output += abnData.businessEntity.mainName.organisationName +
                " ( effective from " + abnData.businessEntity.mainName.effectiveFrom + ")";
        }

        output +="<br>";
        output +="<u>​ABN Status</u> : ";
        if (typeof abnData.businessEntity.entityStatus === 'undefined' ||
            typeof abnData.businessEntity.entityStatus.entityStatusCode === 'undefined' ||
            abnData.businessEntity.entityStatus.entityStatusCode.length === 0
        ) {
            output += "not stated";
        } else {
            output += abnData.businessEntity.entityStatus.entityStatusCode +
                " ( effective from " + abnData.businessEntity.entityStatus.effectiveFrom + ")";
        }

        output +="<br>";
        output +="<u>Entity Type</u> : ";
        if (typeof abnData.businessEntity.entityType === 'undefined' ||
            typeof abnData.businessEntity.entityType.entityDescription === 'undefined' ||
            abnData.businessEntity.entityType.entityDescription.length === 0
        ) {
            output += "not stated";
        } else {
            output += abnData.businessEntity.entityType.entityDescription + " ( " + abnData.businessEntity.entityType.entityTypeCode + ")";
        }

        output +="<br>";
        output +="<u>Trading name</u> : ";
        if (typeof abnData.businessEntity.mainTradingName === 'undefined' ||
            typeof abnData.businessEntity.mainTradingName.organisationName === 'undefined' ||
            abnData.businessEntity.mainTradingName.organisationName.length === 0
        ) {
            output += "not stated";
        } else {
            output += abnData.businessEntity.mainTradingName.organisationName + " ( effective from " + abnData.businessEntity.mainTradingName.effectiveFrom + ")";
        }

        output +="<br>";
        output +="<u>ASIC number</u>: ";
        if (typeof abnData.businessEntity.ASICNumber === 'undefined' ||
            abnData.businessEntity.ASICNumber.length === 0
        ) {
            output += "not stated";
        } else {
            output += abnData.businessEntity.ASICNumber;
        }

        output +="<br>";
        output +="<u>​GST dates</u>: ";
        if (typeof abnData.businessEntity.goodsAndServicesTax === "undefined" ||
            typeof abnData.businessEntity.goodsAndServicesTax.effectiveFrom === "undefined" ||
            abnData.businessEntity.goodsAndServicesTax.effectiveFrom.length === 0
        ) {
            output += "not stated";
        } else {
            output += " from " + abnData.businessEntity.goodsAndServicesTax.effectiveFrom;
            if (typeof abnData.businessEntity.goodsAndServicesTax.effectiveTo === "undefined" ||
                abnData.businessEntity.goodsAndServicesTax.effectiveTo.length === 0 ||
                abnData.businessEntity.goodsAndServicesTax.effectiveTo === '0001-01-01'
            ) {
                output += "";
            } else {
                output += " to " + abnData.businessEntity.goodsAndServicesTax.effectiveTo;
            }
        }

        output +="<div style='border: solid thick pink;'>";
        output +="<u>Historical ABN data</u> : "           + (typeof abnData.businessEntity.historicalABN_data             === 'undefined' || abnData.businessEntity.historicalABN_data.length            === 0 ? " none, only current data exists. ": abnData.businessEntity.historicalABN_data            ) + "<br>";
        output +="<u>Historical ABN names data</u> : "     + (typeof abnData.businessEntity.historicalEntityNamesData      === 'undefined' || abnData.businessEntity.historicalEntityNamesData.length     === 0 ? " none, only current data exists. ": abnData.businessEntity.historicalEntityNamesData     ) + "<br>";
        output +="<u>Historical ​ABN Status data</u> : "    + (typeof abnData.businessEntity.historicalEntityStatusData     === 'undefined' || abnData.businessEntity.historicalEntityStatusData.length    === 0 ? " none, only current data exists. ": abnData.businessEntity.historicalEntityStatusData    ) + "<br>";
        output +="<u>Historical Trading name data</u> : "  + (typeof abnData.businessEntity.historicalMainTradingNameData  === 'undefined' || abnData.businessEntity.historicalMainTradingNameData.length === 0 ? " none, only current data exists. ": abnData.businessEntity.historicalMainTradingNameData ) + "<br>";
        output +="<u>Historical ​GST data</u> : "           + (typeof abnData.businessEntity.historicalGST_data             === 'undefined' || abnData.businessEntity.historicalGST_data.length            === 0 ? " none, only current data exists. ": abnData.businessEntity.historicalGST_data            ) + "<br>";
        output +="</div>";

        output +="</div>";
        document.getElementById('abn-validation-message').innerHTML = output;

    } else {
        setFieldValue('abn_details_hash_c', '');
        document.getElementById('abn-validation-message').innerHTML = "<strong>Not valid</strong><br>";

        if (typeof abnData.Message === 'undefined') {
            document.getElementById('abn-validation-message').innerHTML +=
                "<em>bad string?</em><br>" +
                "<em>changed at ABR since last validation?</em><br><br>" +
                "Please try again";
        } else {
            document.getElementById('abn-validation-message').innerHTML += abnData.Message;
        }
    }
}

/**
 * Initialise form fields
 */
function abnInitialise($messageSpace) {
    "use strict";
    $messageSpace.html('XXRequesting ABN Lookup data ... please wait');
}