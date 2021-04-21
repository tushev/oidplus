/*
 * OIDplus 2.0
 * Copyright 2019 - 2021 Daniel Marschall, ViaThinkSoft
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

var OIDplusPagePublicForgotPassword = {

	oid: "1.3.6.1.4.1.37476.2.5.2.4.1.91",

	forgotPasswordFormOnSubmit: function() {
		$.ajax({
			url: "ajax.php",
			type: "POST",
			beforeSend: function(jqXHR, settings) {
				$.xhrPool.abortAll();
				$.xhrPool.add(jqXHR);
			},
			complete: function(jqXHR, text) {
				$.xhrPool.remove(jqXHR);
			},
			data: {
				csrf_token:csrf_token,
				plugin:OIDplusPagePublicForgotPassword.oid,
				action: "forgot_password",
				email: $("#email").val(),
				captcha: document.getElementsByClassName('g-recaptcha').length > 0 ? grecaptcha.getResponse() : null
			},
			error:function(jqXHR, textStatus, errorThrown) {
				if (errorThrown == "abort") return;
				alert(_L("Error: %1",errorThrown));
				if (document.getElementsByClassName('g-recaptcha').length > 0) grecaptcha.reset();
			},
			success: function(data) {
				if ("error" in data) {
					alert(_L("Error: %1",data.error));
					if (document.getElementsByClassName('g-recaptcha').length > 0) grecaptcha.reset();
				} else if (data.status >= 0) {
					alert(_L("E-Mail sent."));
					window.location.href = '?goto=oidplus:login';
					//reloadContent();
				} else {
					alert(_L("Error: %1",data));
					if (document.getElementsByClassName('g-recaptcha').length > 0) grecaptcha.reset();
				}
			}
		});
		return false;
	},

	resetPasswordFormOnSubmit: function() {
		$.ajax({
			url: "ajax.php",
			type: "POST",
			beforeSend: function(jqXHR, settings) {
				$.xhrPool.abortAll();
				$.xhrPool.add(jqXHR);
			},
			complete: function(jqXHR, text) {
				$.xhrPool.remove(jqXHR);
			},
			data: {
				csrf_token:csrf_token,
				plugin:OIDplusPagePublicForgotPassword.oid,
				action: "reset_password",
				email: $("#email").val(),
				auth: $("#auth").val(),
				password1: $("#password1").val(),
				password2: $("#password2").val(),
				timestamp: $("#timestamp").val()
			},
			error:function(jqXHR, textStatus, errorThrown) {
				if (errorThrown == "abort") return;
				alert(_L("Error: %1",errorThrown));
			},
			success: function(data) {
				if ("error" in data) {
					alert(_L("Error: %1",data.error));
				} else if (data.status >= 0) {
					alert(_L("Password successfully changed. You can now log in."));
					window.location.href = '?goto=oidplus:login';
					//reloadContent();
				} else {
					alert(_L("Error: %1",data));
				}
			}
		});
		return false;
	}

};
