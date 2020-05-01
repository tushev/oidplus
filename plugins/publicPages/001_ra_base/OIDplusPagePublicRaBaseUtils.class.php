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

// TODO: should this be a different plugin type? A page without gui is weird!
class OIDplusPagePublicRaBaseUtils extends OIDplusPagePluginPublic {

	public function action(&$handled) {

		// Action:     delete_ra
		// Method:     POST
		// Parameters: email
		// Outputs:    Text
		if (isset($_POST["action"]) && ($_POST["action"] == "delete_ra")) {
			$handled = true;

			$email = $_POST['email'];

			$ra_logged_in = OIDplus::authUtils()->isRaLoggedIn($email);

			if (!OIDplus::authUtils()->isAdminLoggedIn() && !$ra_logged_in) {
				throw new OIDplusException('Authentification error. Please log in.');
			}

			if ($ra_logged_in) OIDplus::authUtils()->raLogout($email);

			$ra = new OIDplusRA($email);
			if (!$ra->existing()) {
				throw new OIDplusException("RA '$email' does not exist.");
			}
			$ra->delete();
			$ra = null;

			OIDplus::logger()->log("[?WARN/!OK]RA($email)!/[?INFO/!OK]A?", "RA '$email' deleted");

			echo json_encode(array("status" => 0));
		}

	}

	public function init($html=true) {
		// Will be used by: plugins admin-130, public-091, public-200, ra-092, ra-101
		OIDplus::config()->prepareConfigKey('ra_min_password_length', 'Minimum length for RA passwords', '6', OIDplusConfig::PROTECTION_EDITABLE, function($value) {
			if (!is_numeric($value) || ($value < 1)) {
				throw new OIDplusException("Please enter a valid password length.");
			}
		});
	}

	public function gui($id, &$out, &$handled) {
	}

	public function publicSitemap(&$out) {
	}

	public function tree(&$json, $ra_email=null, $nonjs=false, $req_goto='') {
	}

	public function tree_search($request) {
		return false;
	}
}
