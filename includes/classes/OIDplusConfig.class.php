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

// OIDplusConfig contains settings that are stored in the database.
// Not to be confused with OIDplusBaseConfig which is the basic ("static")
// configuration stored in userdata/baseconfig/config.inc.php,
// e.g. database access credentials.
class OIDplusConfig extends OIDplusBaseClass implements OIDplusGetterSetterInterface {

	/**
	 *
	 */
	/*public*/ const PROTECTION_EDITABLE = 0;

	/**
	 *
	 */
	/*public*/ const PROTECTION_READONLY = 1;

	/**
	 *
	 */
	/*public*/ const PROTECTION_HIDDEN   = 2;

	/**
	 * @var bool
	 */
	protected $configTableReadOnce = false; // this ensures that all $values and $descriptions were read

	/**
	 * @var array
	 */
	protected $values = array();

	/**
	 * @var array
	 */
	protected $descriptions = array();

	/**
	 * @var array
	 */
	protected $protectSettings = array();

	/**
	 * @var array
	 */
	protected $visibleSettings = array();

	/**
	 * @var array
	 */
	protected $validateCallbacks = array();

	/**
	 * @param string $name
	 * @param string $description
	 * @param string $init_value
	 * @param int $protection
	 * @param callable|null $validateCallback
	 * @return void
	 * @throws OIDplusException
	 */
	public function prepareConfigKey(string $name, string $description, string $init_value, int $protection, callable $validateCallback=null) {
		// Check if the protection flag is valid
		switch ($protection) {
			case OIDplusConfig::PROTECTION_EDITABLE:
				$protected = false;
				$visible   = true;
				break;
			case OIDplusConfig::PROTECTION_READONLY:
				$protected = true;
				$visible   = true;
				break;
			case OIDplusConfig::PROTECTION_HIDDEN:
				$protected = true;
				$visible   = false;
				break;
			default:
				throw new OIDplusException(_L('Invalid protection flag, use OIDplusConfig::PROTECTION_* constants'));
		}

		// Check length limitations given by the database tables
		if (strlen($name) > 50) {
			throw new OIDplusException(_L('Config key name "%1" is too long (max %2).',$name,50));
		}
		if (strlen($description) > 255) {
			throw new OIDplusException(_L('Description for config key "%1" is too long (max %2).',$name,255));
		}

		// Read all values and descriptions from the database once.
		$this->buildConfigArray();

		// Figure out if we need to create/update something at database level
		if (!isset($this->values[$name])) {
			// Case A: The config setting does not exist in the database. So we create it now.
			try {
				OIDplus::db()->query("insert into ###config (name, description, value, protected, visible) values (?, ?, ?, ?, ?)", array($name, $description, $init_value, $protected, $visible));
			} catch (\Exception $e) {
				// After a software update that introduced a new config setting,
				// there will be a race-condition at this place, because
				// jsTree and content are loading simultaneously!
				// So we ignore the error here.
			}
			$this->values[$name] = $init_value;
			$this->descriptions[$name] = $description;
			$this->protectSettings[$name] = $protected;
			$this->visibleSettings[$name] = $visible;
		} else {
			// Case B: The config setting exists ...
			if ($this->descriptions[$name] != $description) {
				// ... but the human readable description is different.
				// We want to give the plugin authors the possibility to automatically update the config descriptions for their plugins
				// So we just edit the description
				OIDplus::db()->query("update ###config set description = ? where name = ?", array($description, $name));
				$this->descriptions[$name] = $description;
			}
			if ($this->protectSettings[$name] != $protected) {
				OIDplus::db()->query("update ###config set protected = ? where name = ?", array($protected, $name));
				$this->protectSettings[$name] = $protected;
			}
			if ($this->visibleSettings[$name] != $visible) {
				OIDplus::db()->query("update ###config set visible = ? where name = ?", array($visible, $name));
				$this->visibleSettings[$name] = $visible;
			}
		}

		// Register the validation callback
		if (!is_null($validateCallback)) {
			$this->validateCallbacks[$name] = $validateCallback;
		}
	}

	/**
	 * @return void
	 * @throws OIDplusException
	 */
	public function clearCache() {
		$this->configTableReadOnce = false;
		$this->buildConfigArray();
	}

	/**
	 * @return void
	 * @throws OIDplusException
	 */
	protected function buildConfigArray() {
		if ($this->configTableReadOnce) return;

		$this->values = array();
		$this->descriptions = array();
		$this->protectSettings = array();
		$this->visibleSettings = array();
		$res = OIDplus::db()->query("select name, description, protected, visible, value from ###config");
		while ($row = $res->fetch_object()) {
			$this->values[$row->name] = $row->value;
			$this->descriptions[$row->name] = $row->description;
			$this->protectSettings[$row->name] = $row->protected;
			$this->visibleSettings[$row->name] = $row->visible;
		}

		$this->configTableReadOnce = true;
	}

	/**
	 * @return string[]
	 * @throws OIDplusException
	 */
	public function getAllKeys(): array {
		// TODO: put this method into the interface OIDplusGetterSetterInterface

		// Read all config settings once and write them in array $this->values
		$this->buildConfigArray();

		// Now we can see if our desired attribute is available
		return array_keys($this->values);
	}

	/**
	 * @param string $name
	 * @param mixed|null $default
	 * @return mixed|null
	 * @throws OIDplusException
	 */
	public function getValue(string $name, $default=null) {
		// Read all config settings once and write them in array $this->values
		$this->buildConfigArray();

		// Now we can see if our desired attribute is available
		return $this->values[$name] ?? $default;
	}

	/**
	 * @param string $name
	 * @return bool
	 * @throws OIDplusException
	 */
	public function exists(string $name): bool {
		return !is_null($this->getValue($name, null));
	}

	/**
	 * @param string $name
	 * @param mixed $value
	 * @return void
	 * @throws OIDplusException
	 */
	public function setValue(string $name, $value) {
		// Read all config settings once and write them in array $this->values
		$this->buildConfigArray();

		if (isset($this->values[$name])) {
			// Avoid unnecessary database writes
			if ($this->values[$name] == $value) return;
		} else {
			throw new OIDplusException(_L('Config value "%1" cannot be written because it was not prepared!', $name));
		}

		// Give plugins the possibility to stop the process by throwing an Exception (e.g. if the value is invalid)
		// Required is that the plugin previously prepared the config setting using prepareConfigKey()
		if (isset($this->validateCallbacks[$name])) {
			$this->validateCallbacks[$name]($value);
		}

		// Now change the value in the database
		OIDplus::db()->query("update ###config set value = ? where name = ?", array("$value", "$name"));
		$this->values[$name] = $value;
	}

	/**
	 * @param string $name
	 * @param string $value
	 * @return void
	 * @throws OIDplusException
	 */
	public function setValueNoCallback(string $name, string $value) {
		// Read all config settings once and write them in array $this->values
		$this->buildConfigArray();

		if (isset($this->values[$name])) {
			// Avoid unnecessary database writes
			if ($this->values[$name] == $value) return;
		} else {
			throw new OIDplusException(_L('Config value "%1" cannot be written because it was not prepared!', $name));
		}

		// Now change the value in the database
		OIDplus::db()->query("update ###config set value = ? where name = ?", array($value, $name));
		$this->values[$name] = $value;
	}

	/**
	 * @param string $name
	 * @return void
	 * @throws OIDplusException
	 */
	public function delete(string $name) {
		if ($this->configTableReadOnce) {
			if (isset($this->values[$name])) {
				OIDplus::db()->query("delete from ###config where name = ?", array($name));
			}
		} else {
			// We do not know if the value exists.
			// buildConfigArray() would do many reads which are unnecessary.
			// So we just do a MySQL command to delete the stuff:
			OIDplus::db()->query("delete from ###config where name = ?", array($name));
		}

		unset($this->values[$name]);
		unset($this->descriptions[$name]);
		unset($this->validateCallbacks[$name]);
		unset($this->protectSettings[$name]);
		unset($this->visibleSettings[$name]);
	}

}
