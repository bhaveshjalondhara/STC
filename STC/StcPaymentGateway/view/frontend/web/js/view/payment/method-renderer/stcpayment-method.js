/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
/*browser:true*/
/*global define*/
define(
    [   'jquery',
        'Magento_Checkout/js/view/payment/default',
        'mage/url',
        'Magento_Customer/js/customer-data',
        'Magento_Checkout/js/model/error-processor',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Ui/js/modal/modal',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Checkout/js/action/redirect-on-success'
    ],
    function ($,Component,url,customerData, errorProcessor, fullScreenLoader, modal, additionalValidators, redirectOnSuccessAction) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'STC_StcPaymentGateway/payment/stcpayment'
            },

            /** Returns send check to info */
            getMailingAddress: function() {
                return window.checkoutConfig.payment.checkmo.mailingAddress;
            },



        /**
         * Place order.
         */
         placeOrder: function (data, event) {
                var self = this;

                if (event) {
                    event.preventDefault();
                }

                if (this.validate() &&
                    additionalValidators.validate() &&
                    this.isPlaceOrderActionAllowed() === true
                ) {
                    this.isPlaceOrderActionAllowed(false);

                    this.getPlaceOrderDeferredObject()
                        .done(
                            function () {
                                self.afterPlaceOrder();

                                if (self.redirectAfterPlaceOrder) {
                                    redirectOnSuccessAction.execute();
                                }
                            }
                        ).always(
                            function () {
                                self.isPlaceOrderActionAllowed(true);
                            }
                        );

                    return true;
                }

                return false;
            },

            verifyOTP: function () {
                var self = this;

                var payment_url = url.build('stc/index/payment'); 
                $(".error").html("").hide();
                $(".success").html("").hide();
                var otp = $("#mobile").val();
                var input = {
                    "otp" : otp,
                    "action" : "verify_otp"
                };


                if (otp.length != 2 && otp != null) {
                     fullScreenLoader.startLoader();
                    $.ajax({
                        url : payment_url,
                        type : 'POST',
                        dataType : "json",
                        data : input,
                        success : function(response) {
                             var jsonresponce = $.parseJSON(JSON.stringify(response));
                            fullScreenLoader.stopLoader();
                            if(jsonresponce.Code)
                            {
                                alert(jsonresponce.Text);
                                $('.modal-footer button').click();
                                return false;
                            }
                            if(jsonresponce.error)
                            {
                                alert(jsonresponce.error);
                                $('.modal-footer button').click();
                                return false;   
                            } 

                             //call Ajax for confirm Payment
                            
                        var tokenId = jsonresponce.DirectPaymentConfirmResponseMessage.TokenId;
                        if(tokenId && typeof tokenId !== 'undefined') {
                            var inputConfirm  = {
                                            "action" : "directPaymentConfirm",
                                             "paymentconfirm"   : jsonresponce
                                            };
                                $.ajax({
                                url : payment_url,
                                type : 'POST',
                                dataType : "json",
                                data : inputConfirm,
                                success : function(response) {
                                     var jsondata = $.parseJSON(JSON.stringify(response));
                                   
                                     if (jsondata.DirectPaymentConfirmResponseMessage.TokenId)
                                     {
                                        alert("Mobile OTP varification successfully done");
                                        $('.modal-footer button').click();
                                        self.placeOrder();
                                        fullScreenLoader.startLoader();
                                     } else {
                                        alert("Mobile OTP varification responce can not found");
                                         $('.modal-footer button').click();
                                         return false;
                                     }
                                     fullScreenLoader.stopLoader();
                                },
                                error : function() {
                                    alert("Mobile OTP varification responce can not found");
                                     $('.modal-footer button').click();
                                     return false;
                                }
                            });
                         }
                            //endcode
                            
                        },
                        error : function() {
                            alert("Mobile OTP varification responce can not found");
                            $('.modal-footer button').click();
                            return false;
                        }
                    });
                } else {
                     alert('You have entered wrong OTP.');
                     $('.modal-footer button').click();
                     return false;
                }
            },
            afterPlaceOrder: function () {
                var input = {
                    "action" : 'afterPlaceOrder'
                };
                var payment_url = url.build('stc/index/payment'); 

                   $.ajax({
                            url : payment_url,
                            type : 'POST',
                            dataType : "json",
                            data : input,
                            success : function(response) {
                                 var jsonDataResponse = $.parseJSON(JSON.stringify(response));
                                 if (jsonDataResponse) {
                                     alert("After Place Order response correct");
                                     return false;
                                 }
                                 fullScreenLoader.stopLoader();
                            },
                            error : function() {
                                 alert("After Place Order response Incorrect");
                                 return false;
                            }
                         });
            },

            resendOPT: function () {

                fullScreenLoader.startLoader();
                var custom_controller_url = url.build('stc/index/payment'); //your custom controller url
                 $.post(custom_controller_url, 'json')
                 .done(function (response) {
                    var json = $.parseJSON(JSON.stringify(response));
                    if (json.Code)
                    {
                        alert(json.Text);
                        fullScreenLoader.stopLoader();
                        return false;
                    }

                    if(json.error)
                    {
                        alert(json.error);
                        fullScreenLoader.stopLoader();
                        return false;   
                    }


                    if(json.DirectPaymentAuthorizeResponseMessage.OtpReference) {
                        var otpReference = json.DirectPaymentAuthorizeResponseMessage.OtpReference;
                        var stcPayPmtReference = json.DirectPaymentAuthorizeResponseMessage.STCPayPmtReference;
                        var expiryDuration = json.DirectPaymentAuthorizeResponseMessage.ExpiryDuration;
                    
                        if (otpReference && stcPayPmtReference && expiryDuration) {
                             alert('Mobile OTP Resent successfully');
                            fullScreenLoader.stopLoader();
                            return false;
                        } else {
                            alert('DirectPaymentAuthorization some parameters are missing');
                             fullScreenLoader.stopLoader();
                            return false;
                        }
                    } else {
                        alert('DirectPaymentAuthorization fail');
                         fullScreenLoader.stopLoader();
                        return false;
                    }
                    
                })
                .fail(function (response) {
                    return false;
                })
                .always(function () {
                    return false;
                });
            
            },

            stcPayment: function () {
              var options = {
                    type: 'popup',
                    responsive: true,
                    innerScroll: true,
                    title: '',
                    buttons: [{
                        text: $.mage.__('Close'),
                        class: '',
                        click: function () {
                            this.closeModal();
                        }
                    }]
                };
               
                fullScreenLoader.startLoader();
                var custom_controller_url = url.build('stc/index/payment'); //your custom controller url
                 $.post(custom_controller_url, 'json')
                 .done(function (response) {
                    var json = $.parseJSON(JSON.stringify(response));
                    if (json.Code)
                    {
                        alert(json.Text);
                        fullScreenLoader.stopLoader();
                        return false;
                    }

                    if(json.error)
                    {
                        alert(json.error);
                        fullScreenLoader.stopLoader();
                        return false;   
                    }


                    if(json.DirectPaymentAuthorizeResponseMessage.OtpReference) {
                        var otpReference = json.DirectPaymentAuthorizeResponseMessage.OtpReference;
                        var stcPayPmtReference = json.DirectPaymentAuthorizeResponseMessage.STCPayPmtReference;
                        var expiryDuration = json.DirectPaymentAuthorizeResponseMessage.ExpiryDuration;
                    
                        if (otpReference && stcPayPmtReference && expiryDuration) {
                            fullScreenLoader.stopLoader();
                             var popup = modal(options, $('#header-mpdal'));
                             $("#header-mpdal").modal("openModal");
                        } else {
                            alert('DirectPaymentAuthorization some parameters are missing');
                             fullScreenLoader.stopLoader();
                            return false;
                        }
                    } else {
                        alert('DirectPaymentAuthorization fail');
                         fullScreenLoader.stopLoader();
                        return false;
                    }
                    
                })
                .fail(function (response) {
                    errorProcessor.process(response, this.messageContainer);
                })
                .always(function () {
                    fullScreenLoader.stopLoader();
                });
            },

             directPayment: function () {
                var self = this;
                fullScreenLoader.startLoader();
                 var input = {
                    "action" : 'directpayment'
                };
                var payment_url = url.build('stc/index/payment'); 
                   $.ajax({
                            url : payment_url,
                            type : 'POST',
                            dataType : "json",
                            data : input,
                            success : function(response) {
                                 var jsonDataResponse = JSON.stringify(response);
                                 var tokenId = response.tokenId;
                                 var error = response.error;

                                 if (tokenId && tokenId != null) {
                                    if(confirm("Are you sure to make payment using STC direct payment?") == true){
                                          self.directPaymentapi();
                                        } else {
                                           return false;
                                        }
                                        fullScreenLoader.stopLoader();
                                 } else {
                                    self.stcPayment();
                                 }
                            },
                            error : function() {
                                 alert("After Place Order response Incorrect");
                                 return false;
                            }
                         });
             },

              directPaymentapi: function () {
                var self = this;
                fullScreenLoader.startLoader();
                 var input = {
                    "action" : 'directpaymentApi'
                };
                var payment_url = url.build('stc/index/payment'); 
                   $.ajax({
                            url : payment_url,
                            type : 'POST',
                            dataType : "json",
                            data : input,
                            success : function(response) {
                                 var jsondata = $.parseJSON(JSON.stringify(response));
                                 if (jsondata.DirectPaymentResponseMessage.PaymentStatusDesc == 'Paid')
                                     {
                                        alert('Your payment has been successful');
                                        self.placeOrder();
                                        fullScreenLoader.startLoader();
                                     } else
                                     {
                                        alert('Payment is not done successful');
                                     }
                                 fullScreenLoader.stopLoader();
                                
                            },
                            error : function() {
                                 alert("After Place Order response Incorrect");
                                 return false;
                            }
                         });
             }

        });
    }
);
