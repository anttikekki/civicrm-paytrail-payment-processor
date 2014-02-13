cj(function ($) {
  'use strict';
  
  CRM.paytrail.paymentButtonClicked = function(redirectURL) {
    var form = $('#Confirm');
    var action = form.attr('action');
    action = action + '&paytrailEmbeddedButtonsRedirectURL=' + encodeURIComponent(redirectURL);
    form.attr('action', action);
    
    $('#_qf_Confirm_next-top').click();
  };
  
  SV.widget.initWithToken('help', CRM.paytrail.token);
});