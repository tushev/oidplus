<?php

/*
 * OIDplus 2.0
 * Copyright 2019 Daniel Marschall, ViaThinkSoft
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

if (!defined('IN_OIDPLUS')) die();

class OIDplusPageRaChangeEMail extends OIDplusPagePlugin {
	public function type() {
		return 'ra';
	}

	public function priority() {
		return 102;
	}

	public function action(&$handled) {
		if (isset($_POST["action"]) && ($_POST["action"] == "change_ra_email")) {
			$handled = true;

			if (!OIDplus::config()->getValue('allow_ra_email_change')) {
				die(json_encode(array("error" => 'This functionality has been disabled by the administrator.')));
			}

			$old_email = $_POST['old_email'];
			$new_email = $_POST['new_email'];

			if (!OIDplus::authUtils()::isRaLoggedIn($old_email) && !OIDplus::authUtils()::isAdminLoggedIn()) {
				die(json_encode(array("error" => 'Authentification error. Please log in as the RA to update its email address.')));
			}

			if (!oidplus_valid_email($new_email)) {
				die(json_encode(array("error" => 'eMail address is invalid.')));
			}

			$res = OIDplus::db()->query("select * from ".OIDPLUS_TABLENAME_PREFIX."ra where email = '".OIDplus::db()->real_escape_string($old_email)."'");
			if (OIDplus::db()->num_rows($res) == 0) {
				die(json_encode(array("error" => 'eMail address does not exist anymore. It was probably already changed.')));
			}

			$res = OIDplus::db()->query("select * from ".OIDPLUS_TABLENAME_PREFIX."ra where email = '".OIDplus::db()->real_escape_string($new_email)."'");
			if (OIDplus::db()->num_rows($res) > 0) {
				die(json_encode(array("error" => 'eMail address is already used by another RA. To merge accounts, please contact the superior RA of your objects and request an owner change of your objects.')));
			}

			OIDplus::logger()->log("RA($old_email)!+RA($new_email)!", "Requested email change from '$old_email' to '$new_email'");

			$timestamp = time();
			$activate_url = OIDplus::system_url() . '?goto='.urlencode('oidplus:activate_new_ra_email$'.$old_email.'$'.$new_email.'$'.$timestamp.'$'.OIDplus::authUtils()::makeAuthKey('activate_new_ra_email;'.$old_email.';'.$new_email.';'.$timestamp));

			$message = file_get_contents(__DIR__ . '/change_request_email.tpl');
			$message = str_replace('{{SYSTEM_URL}}', OIDplus::system_url(), $message);
			$message = str_replace('{{SYSTEM_TITLE}}', OIDplus::config()->systemTitle(), $message);
			$message = str_replace('{{ADMIN_EMAIL}}', OIDplus::config()->getValue('admin_email'), $message);
			$message = str_replace('{{OLD_EMAIL}}', $old_email, $message);
			$message = str_replace('{{NEW_EMAIL}}', $new_email, $message);
			$message = str_replace('{{ACTIVATE_URL}}', $activate_url, $message);
			my_mail($new_email, OIDplus::config()->systemTitle().' - Change email request', $message);

			echo json_encode(array("status" => 0));
		}

		if (isset($_POST["action"]) && ($_POST["action"] == "activate_new_ra_email")) {
			$handled = true;

			if (!OIDplus::config()->getValue('allow_ra_email_change')) {
				die(json_encode(array("error" => 'This functionality has been disabled by the administrator.')));
			}

			$old_email = $_POST['old_email'];
			$new_email = $_POST['new_email'];
			$password = $_POST['password'];

			$auth = $_POST['auth'];
			$timestamp = $_POST['timestamp'];

			if (!OIDplus::authUtils()::validateAuthKey('activate_new_ra_email;'.$old_email.';'.$new_email.';'.$timestamp, $auth)) {
				die(json_encode(array("error" => 'Invalid auth key')));
			}

			if ((OIDplus::config()->getValue('max_ra_email_change_time') > 0) && (time()-$timestamp > OIDplus::config()->maxEmailChangeTime())) {
				die(json_encode(array("error" => 'Activation link expired!')));
			}

			$res = OIDplus::db()->query("select * from ".OIDPLUS_TABLENAME_PREFIX."ra where email = '".OIDplus::db()->real_escape_string($old_email)."'");
			if (OIDplus::db()->num_rows($res) == 0) {
				die(json_encode(array("error" => 'eMail address does not exist anymore. It was probably already changed.')));
			}

			$res = OIDplus::db()->query("select * from ".OIDPLUS_TABLENAME_PREFIX."ra where email = '".OIDplus::db()->real_escape_string($new_email)."'");
			if (OIDplus::db()->num_rows($res) > 0) {
				die(json_encode(array("error" => 'eMail address is already used by another RA. To merge accounts, please contact the superior RA of your objects and request an owner change of your objects.')));
			}

			$ra = new OIDplusRA($old_email);
			if (!$ra->checkPassword($password)) {
				die(json_encode(array("error" => 'Wrong password')));
			}

			$ra->change_email($new_email);

			if (!OIDplus::db()->query("update ".OIDPLUS_TABLENAME_PREFIX."objects set ra_email = '".OIDplus::db()->real_escape_string($new_email)."' where ra_email = '".OIDplus::db()->real_escape_string($old_email)."'")) {
				throw new Exception(OIDplus::db()->error());
			}

			OIDplus::authUtils()->raLogout($old_email);
			OIDplus::authUtils()->raLogin($new_email);

			OIDplus::logger()->log("RA($old_email)!", "Changed email address from '$old_email' to '$new_email'");
			OIDplus::logger()->log("RA($new_email)!", "RA '$old_email' has changed its email address to '$new_email'");

			$message = file_get_contents(__DIR__ . '/email_change_confirmation.tpl');
			$message = str_replace('{{SYSTEM_URL}}', OIDplus::system_url(), $message);
			$message = str_replace('{{SYSTEM_TITLE}}', OIDplus::config()->systemTitle(), $message);
			$message = str_replace('{{ADMIN_EMAIL}}', OIDplus::config()->getValue('admin_email'), $message);
			$message = str_replace('{{OLD_EMAIL}}', $old_email, $message);
			$message = str_replace('{{NEW_EMAIL}}', $new_email, $message);
			my_mail($old_email, OIDplus::config()->systemTitle().' - eMail address changed', $message);

			echo json_encode(array("status" => 0));
		}
	}

	public function init($html=true) {
		OIDplus::config()->prepareConfigKey('max_ra_email_change_time', 'Max RA email change time in seconds (0 = infinite)', '0', 0, 1);
		OIDplus::config()->prepareConfigKey('allow_ra_email_change', 'Allow that RAs change their email address (0/1)', '1', 0, 1);
	}

	public function cfgSetValue($name, $value) {
		if ($name == 'allow_ra_email_change') {
		        if (($value != '0') && ($value != '1')) {
		                throw new Exception("Please enter either 0 or 1.");
		        }
		}
		if (($name == 'max_ra_invite_time') || ($name == 'max_ra_pwd_reset_time') || ($name == 'max_ra_email_change_time')) {
			if (!is_numeric($value) || ($value < 0)) {
				throw new Exception("Please enter a valid value.");
			}
		}
	}

	public function gui($id, &$out, &$handled) {
		if (explode('$',$id)[0] == 'oidplus:change_ra_email') {
			$handled = true;
			$out['title'] = 'Change RA email';
			$out['icon'] = file_exists(__DIR__.'/icon_big.png') ? 'plugins/'.basename(dirname(__DIR__)).'/'.basename(__DIR__).'/icon_big.png' : '';

			$ra_email = explode('$',$id)[1];

			$res = OIDplus::db()->query("select * from ".OIDPLUS_TABLENAME_PREFIX."ra where email = '".OIDplus::db()->real_escape_string($ra_email)."'");
			if (OIDplus::db()->num_rows($res) == 0) {
				$out['icon'] = 'img/error_big.png';
				$out['text'] = 'RA <b>'.htmlentities($ra_email).'</b> does not exist';
			} else if (!OIDplus::config()->getValue('allow_ra_email_change')) {
				$out['icon'] = 'img/error_big.png';
				$out['text'] .= '<p>This functionality has been disabled by the administrator.</p>';
			} else if (!OIDplus::authUtils()::isRaLoggedIn($ra_email) && !OIDplus::authUtils()::isAdminLoggedIn()) {
				$out['icon'] = 'img/error_big.png';
				$out['text'] .= '<p>You need to <a '.oidplus_link('oidplus:login').'>log in</a> as the requested RA <b>'.htmlentities($ra_email).'</b>.</p>';
			} else {
				$out['text'] .= '<form id="changeRaEmailForm" onsubmit="return changeRaEmailFormOnSubmit();">';
				$out['text'] .= '<input type="hidden" id="old_email" value="'.htmlentities($ra_email).'"/><br>';
				$out['text'] .= '<label class="padding_label">Old address:</label><b>'.htmlentities($ra_email).'</b><br><br>';
				$out['text'] .= '<label class="padding_label">New address:</label><input type="text" id="new_email" value=""/><br><br>';
				$out['text'] .= '<input type="submit" value="Send new activation email"></form>';
			}
		} else if (explode('$',$id)[0] == 'oidplus:activate_new_ra_email') {
			$handled = true;

			$old_email = explode('$',$id)[1];
			$new_email = explode('$',$id)[2];
			$timestamp = explode('$',$id)[3];
			$auth = explode('$',$id)[4];

			if (!OIDplus::config()->getValue('allow_ra_email_change')) {
				$out['icon'] = 'img/error_big.png';
				$out['text'] .= '<p>This functionality has been disabled by the administrator.</p>';
			} else {
				$out['title'] = 'Perform email address change';
				$out['icon'] = file_exists(__DIR__.'/icon_big.png') ? 'plugins/'.basename(dirname(__DIR__)).'/'.basename(__DIR__).'/icon_big.png' : '';

				$res = OIDplus::db()->query("select * from ".OIDPLUS_TABLENAME_PREFIX."ra where email = '".OIDplus::db()->real_escape_string($old_email)."'");
				if (OIDplus::db()->num_rows($res) == 0) {
					$out['icon'] = 'img/error_big.png';
					$out['text'] = 'eMail address does not exist anymore. It was probably already changed.';
				} else {
					$res = OIDplus::db()->query("select * from ".OIDPLUS_TABLENAME_PREFIX."ra where email = '".OIDplus::db()->real_escape_string($new_email)."'");
					if (OIDplus::db()->num_rows($res) > 0) {
						$out['icon'] = 'img/error_big.png';
						$out['text'] = 'eMail address is already used by another RA. To merge accounts, please contact the superior RA of your objects and request an owner change of your objects.';
					} else {
						if (!OIDplus::authUtils()::validateAuthKey('activate_new_ra_email;'.$old_email.';'.$new_email.';'.$timestamp, $auth)) {
							$out['icon'] = 'img/error_big.png';
							$out['text'] = 'Invalid authorization. Is the URL OK?';
						} else {
							$out['text'] = '<p>Old eMail-Address: <b>'.$old_email.'</b></p>
					                <p>New eMail-Address: <b>'.$new_email.'</b></p>

						         <form id="activateNewRaEmailForm" onsubmit="return activateNewRaEmailFormOnSubmit();">
						    <input type="hidden" id="old_email" value="'.htmlentities($old_email).'"/>
						    <input type="hidden" id="new_email" value="'.htmlentities($new_email).'"/>
						    <input type="hidden" id="timestamp" value="'.htmlentities($timestamp).'"/>
						    <input type="hidden" id="auth" value="'.htmlentities($auth).'"/>

						    <label class="padding_label">Please verify your password:</label><input type="password" id="password" value=""/><br><br>
						    <input type="submit" value="Change email address">
						  </form>';
						}
					}
				}
			}
		}
	}

	public function tree(&$json, $ra_email=null, $nonjs=false, $req_goto='') {
		if (file_exists(__DIR__.'/treeicon.png')) {
			$tree_icon = 'plugins/'.basename(dirname(__DIR__)).'/'.basename(__DIR__).'/treeicon.png';
		} else {
			$tree_icon = null; // default icon (folder)
		}

		$json[] = array(
			'id' => 'oidplus:change_ra_email$'.$ra_email,
			'icon' => $tree_icon,
			'text' => 'Change email address'
		);

		return true;
	}

	public function tree_search($request) {
		return false;
	}
}

OIDplus::registerPagePlugin(new OIDplusPageRaChangeEMail());
