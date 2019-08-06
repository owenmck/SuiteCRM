//custom/modules/Accounts/js/editView.js
var abnLookupGUID = 'eb7e518b-6a8a-4db5-a4bd-508a169ddc42';
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
            abnLookup($messageSpace);
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
function jsonRequest(url) {
    //REST request path
    this.url = url;

    //Ask IE not to cache requests
    this.noCacheIE = '&noCacheIE=' + (new Date()).getTime();

    //Locate (in the DOM) where to put the new script tag
    this.headLoc = document.getElementsByTagName("head").item(0);

    //Generate a unique script tag id
    this.scriptId = 'sflScriptId' + jsonRequest.Counter ++;
}

//Static script ID counter
jsonRequest.Counter = 1;

jsonRequest.prototype.buildScriptTag = function () {
    this.scriptObject = document.createElement("script");
    this.scriptObject.setAttribute("type", "text/javascript");
    this.scriptObject.setAttribute("src", this.url + this.noCacheIE);
    this.scriptObject.setAttribute("id", this.scriptId);
};

// remove script tag
jsonRequest.prototype.removeScriptTag = function () {
    this.headLoc.removeChild(this.scriptObject);
};

// add script tag
jsonRequest.prototype.addScriptTag = function () {
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

function abnLookup($messageSpace) {
    var abn = getFieldValue('abn_c');
    // var guid = abnLookupGUID;
    // var jasonScript;
    abnInitialise($messageSpace);
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
                console.log(data);
                abnCallback(data);
            }
        }
    );
}

/*------------------------------------------------------------------
Call back function
------------------------------------------------------------------ */
function abnCallback (abnData)
{
    if (typeof abnData.Message === 'undefined' || abnData.Message.length == 0) {
        setFieldValue('abn_details_hash_c', abnData.hash);
        document.getElementById('abn-validation-message').innerHTML =
            "Validated (details in console!).<br>" +
            "ABR data has been unchanged since " + abnData.AddressDate + "<br>" +
            "​AbnStatus: "       + abnData.AbnStatus + "<br>" +
            "AddressDate: "     + abnData.AddressDate + "<br>" +
            "AddressPostcode: " + abnData.AddressPostcode;
    } else {
        document.getElementById('abn-validation-message').innerHTML = "Not valid, or changed at ABR since last validation. Please try again.";
        setFieldValue('abn_details_hash_c', '');
    }
}

/*------------------------------------------------------------------
Initialise form fields
------------------------------------------------------------------ */
function abnInitialise($messageSpace) {
    "use strict";
    $messageSpace.html('Requesting ABN Lookup data ... please wait');
}


