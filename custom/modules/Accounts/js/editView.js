//custom/modules/Accounts/js/editView.js
var $abnInput = $('#abn_c');
var $hashInput = $('#abn_details_hash_c');
(function($) {
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

/*------------------------------------------------------------------
   Class for accessing services using JSON
  ------------------------------------------------------------------*/
function JSON_Request(url) {
    //REST request path
    this.url = url;

    //Ask IE not to cache requests
    this.noCacheIE = '&noCacheIE=' + (new Date()).getTime();

    //Locate (in the DOM) where to put the new script tag
    this.headLoc = document.getElementsByTagName("head").item(0);

    //Generate a unique script tag id
    this.scriptId = 'sflScriptId' + JSON_Request.Counter ++;
}

//Static script ID counter
JSON_Request.Counter = 1;

JSON_Request.prototype.buildScriptTag = function () {
    this.scriptObject = document.createElement("script");
    this.scriptObject.setAttribute("type", "text/javascript");
    this.scriptObject.setAttribute("src", this.url + this.noCacheIE);
    this.scriptObject.setAttribute("id", this.scriptId);
};

// remove script tag
JSON_Request.prototype.removeScriptTag = function () {
    this.headLoc.removeChild(this.scriptObject);
};

// add script tag
JSON_Request.prototype.addScriptTag = function () {
    this.headLoc.appendChild(this.scriptObject);
};
/*------------------------------------------------------------------
    return value of field
  ------------------------------------------------------------------*/
function getFieldValue(fieldId) {
    var oField = document.getElementById(fieldId);
    if (oField) {
        return oField.value;
    }
    else {
        return '';
    }
}
/*------------------------------------------------------------------
    populate field with value
  ------------------------------------------------------------------*/
function setFieldValue(fieldId, value) {
    var oField = document.getElementById(fieldId);
    if (oField) {
        oField.value = value;
    }
}

/*------------------------------------------------------------------
    Use web service to lookup details of the ABN
  ------------------------------------------------------------------*/

function abnLookup() {
    var abn = getFieldValue('abn_c');

    clearValidationMessages();
    jQuery.ajax(
        {
            url: "index.php?entryPoint=abr",
            method: "POST",
            dataType: 'json',
            data: {"abnValue": abn},
            error: function (XHR, AjaxStatus, theMessage) {
                console.log("ajax error: " + theMessage);
            },
            statusCode: {
                403: function (XHR, AjaxStatus, theMessage) {
                    console.log("403 (verboten): " + theMessage);
                },
                404: function (XHR, AjaxStatus, theMessage) {
                    console.log("404 (lost!): " + theMessage);
                }
            },
            success: function (data) {
                abnCallback(data);
            }
        }
    );
}

function clearValidationMessages()
{
    var validationMessages = document.querySelectorAll(".validation-message");

    for (var i = 0; i < validationMessages.length; i++) {
        validationMessages[i].innerHTML = "";
    }
}

/*------------------------------------------------------------------
Call back function
------------------------------------------------------------------ */
function abnCallback (abnData)
{
    if (typeof abnData.Message === 'undefined' || abnData.Message.length == 0) {
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
        document.getElementById('abn-validation-message').innerHTML =
            "<strong>Validated</strong><br>" +
            "<u>Usage Statement</u> : "         + abnData.usageStatement          + "<br>" +
            "<u>dateRegisterLastUpdated</u> : " + abnData.dateRegisterLastUpdated + "<br>" +
            "<u>dateTimeRetrieved</u> : "       + abnData.dateTimeRetrieved       +

            "<div style='border: solid thin green;'>" +
            "<u>recordLastUpdatedDate :</u> "   + abnData.businessEntity.recordLastUpdatedDate   + "<br>" +
            "<u>ABN</u> : "            + abnData.businessEntity.ABN.identifierValue           + " ( isCurrent: "     + abnData.businessEntity.ABN.isCurrentIndicator     + (abnData.businessEntity.ABN.replacedFrom == '0001-01-01' ? "" : " - replaced " + abnData.businessEntity.ABN.replacedFrom         ) + ")" + "<br>" +
            "<u>Entity name</u> : "    + abnData.businessEntity.mainName.organisationName     + " ( effective from " + abnData.businessEntity.mainName.effectiveFrom + ")"      + "<br>" +
            "<u>​ABN Status</u> : "     + abnData.businessEntity.entityStatus.entityStatusCode + " ( effective from " + abnData.businessEntity.entityStatus.effectiveFrom  + ")" + "<br>" +
            "<u>Entity Type</u> : "    + abnData.businessEntity.entityType.entityDescription  + " ( " + abnData.businessEntity.entityType.entityTypeCode + ")"                  + "<br>" +
            "<u>Trading name</u> : "   + abnData.businessEntity.mainTradingName.organisationName + " ( effective from " + abnData.businessEntity.mainTradingName.effectiveFrom + ")"      + "<br>" +
            "<u>ASIC number</u>: "     + abnData.businessEntity.ASICNumber   + "<br>" +
            "<u>​GST dates</u>: "       + " from " + abnData.businessEntity.goodsAndServicesTax.effectiveFrom + ( abnData.businessEntity.goodsAndServicesTax.effectiveTo == '0001-01-01'  ? "" : " to " + abnData.businessEntity.goodsAndServicesTax.effectiveTo) + "<br>" +

                "<div style='border: solid thick pink;'>" +
                "<u>Historical ABN data</u> : "           + (typeof abnData.businessEntity.historicalABN_data             === 'undefined' || abnData.businessEntity.historicalABN_data.length            == 0 ? " none, only current data exists. ": abnData.businessEntity.historicalABN_data            ) + "<br>" +
                "<u>Historical ABN names data</u> : "     + (typeof abnData.businessEntity.historicalEntityNamesData      === 'undefined' || abnData.businessEntity.historicalEntityNamesData.length     == 0 ? " none, only current data exists. ": abnData.businessEntity.historicalEntityNamesData     ) + "<br>" +
                "<u>Historical ​ABN Status data</u> : "    + (typeof abnData.businessEntity.historicalEntityStatusData     === 'undefined' || abnData.businessEntity.historicalEntityStatusData.length    == 0 ? " none, only current data exists. ": abnData.businessEntity.historicalEntityStatusData    ) + "<br>" +
                "<u>Historical Trading name data</u> : "  + (typeof abnData.businessEntity.historicalMainTradingNameData  === 'undefined' || abnData.businessEntity.historicalMainTradingNameData.length == 0 ? " none, only current data exists. ": abnData.businessEntity.historicalMainTradingNameData ) + "<br>" +
                "<u>Historical ​GST data</u> : "           + (typeof abnData.businessEntity.historicalGST_data             === 'undefined' || abnData.businessEntity.historicalGST_data.length            == 0 ? " none, only current data exists. ": abnData.businessEntity.historicalGST_data            ) + "<br>" +
                "</div>" +

            "</div>";

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

/*------------------------------------------------------------------
Initialise form fields
------------------------------------------------------------------ */
function abnInitialise($messageSpace) {
    "use strict";
    $messageSpace.html('Requesting ABN Lookup data ... please wait');
}
