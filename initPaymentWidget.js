/**
* Event or Contribution confirmation page payment button embedding.
*/
cj(function ($) {
  'use strict';
  
  /**
  * This method is called from payment-widget-v1.0-custom.js when embedded payment 
  * button is clicked. Stores redirect URL to form action URL parameter so that it is available to 
  * PHP.
  */
  CRM.paytrail.paymentButtonClicked = function(redirectURL) {
    var form = $('#Confirm');
    var action = form.attr('action');
    var noURLParameters = action.indexOf('?') === -1;
    action = action + (noURLParameters ? '?' : '&') + 'paytrailEmbeddedButtonsRedirectURL=' + encodeURIComponent(redirectURL);
    form.attr('action', action);
    
    //Send form bu cliking submit
    $('#_qf_Confirm_next-top').click();
  };
  
  //Add container for payment buttons
  $('#Confirm').before('<div id="paytrailEmbeddedButtonsContainer"></div>');
  
  //Add payment buttons. Requires token from Paytrail REST API.
  SV.widget.initWithToken('paytrailEmbeddedButtonsContainer', CRM.paytrail.token);
});