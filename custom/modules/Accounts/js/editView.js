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

        $hashInput.prop('disabled', true);

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
    var guid = abnLookupGUID;
    var jasonScript;

    var request = 'https://www.abr.business.gov.au/json/AbnDetails.aspx?callback=abnCallback&abn=' + abn + '&guid=' + guid;
    //var request = 'https://abr.business.gov.au/abrxmlsearch/AbrXmlSearch.asmx/SearchByABNv201408?searchString=35450124968&includeHistoricalDetails=N&authenticationGuid=eb7e518b-6a8a-4db5-a4bd-508a169ddc42';
    try {
        abnInitialise($messageSpace);
        jasonScript = new jsonRequest(request);
        console.log(jasonScript);
        jasonScript.buildScriptTag();
        jasonScript.addScriptTag();
    }
    catch (exception) {
        alert("Shez ded Jim");
    }
}

String.prototype.hashCode = function() {
    var hash = 0, i, chr;
    if (this.length === 0) return hash;
    for (i = 0; i < this.length; i++) {
        chr   = this.charCodeAt(i);
        hash  = ((hash << 5) - hash) + chr;
        hash |= 0; // Convert to 32bit integer
    }
    return hash;
};


/*------------------------------------------------------------------
Call back function
------------------------------------------------------------------ */
function abnCallback (abnData) {
    console.log("Response from ABR: ");
    console.log(abnData);

    if (abnData.Message.length == 0) {
        var abnDetailsString = JSON.stringify(abnData);
        console.log("Concatenated: " + abnDetailsString);
        var hashStr = abnDetailsString.hashCode();
        setFieldValue('abn_details_hash_c', hashStr);
        console.log("hashed: " + hashStr);
        document.getElementById('abn-validation-message').innerHTML = "Validated (details in console!).<br>ABR data has been unchanged since " + abnData.AddressDate;
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


