<?php

/*
 * OIDplus 2.0
 * Copyright 2019 - 2023 Daniel Marschall, ViaThinkSoft
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

namespace ViaThinkSoft\OIDplus;

// phpcs:disable PSR1.Files.SideEffects
\defined('INSIDE_OIDPLUS') or die;
// phpcs:enable PSR1.Files.SideEffects

class OIDplusRAAuthInfo extends OIDplusBaseClass {

	private $authKey;

	public function setAuthKey($authKey) {
		// 250 is the length of the database field
		if (strlen($authKey) > 250) throw new OIDplusException(_L('Field %1 is too long. Max allowed %2','Auth key',250));
		if (is_null($authKey) || ($authKey === false)) throw new OIDplusException(_L('Field %1 is invalid','Auth key'));
		$this->authKey = $authKey;
	}

	public function getAuthKey() {
		return $this->authKey;
	}

	public function __construct($authKey) {
		$this->setAuthKey($authKey);
	}

	public function isPasswordLess() {
		return empty($this->authKey);
	}

}
