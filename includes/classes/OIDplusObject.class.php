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

abstract class OIDplusObject extends OIDplusBaseClass {

	/**
	 *
	 */
	const UUID_NAMEBASED_NS_OidPlusMisc = 'ad1654e6-7e15-11e4-9ef6-78e3b5fc7f22';

	/**
	 * Please overwrite this function!
	 * @param string $node_id
	 * @return OIDplusObject|null
	 */
	public static function parse(string $node_id)/*: ?OIDplusObject*/ {
		foreach (OIDplus::getEnabledObjectTypes() as $ot) {
			try {
				$good = false;
				if (get_parent_class($ot) == OIDplusObject::class) {
					$reflector = new \ReflectionMethod($ot, 'parse');
					$isImplemented = ($reflector->getDeclaringClass()->getName() === $ot);
					if ($isImplemented) { // avoid endless loop if parse is not overriden
						$good = true;
					}
				}
				// We need to do the workaround with "$good", otherwise PHPstan shows
				// "Call to an undefined static method object::parse()"
				if ($good && $obj = $ot::parse($node_id)) return $obj;
			} catch (\Exception $e) {}
		}
		return null;
	}

	/**
	 * @return OIDplusAltId[]
	 * @throws OIDplusException
	 */
	public function getAltIds(): array {
		if ($this->isRoot()) return array();

		$ids = array();

		// Creates an OIDplus-Hash-OID
		if ($this->ns() != 'oid') {
			$sid = OIDplus::getSystemId(true);
			if (!empty($sid)) {
				$ns_oid = $this->getPlugin()->getManifest()->getOid();
				if (str_starts_with($ns_oid, '1.3.6.1.4.1.37476.2.5.2.')) {
					// Official ViaThinkSoft object type plugins
					// For backwards compatibility with existing IDs,
					// set the hash_payload as '<namespace>:<id>'
					$hash_payload = $this->nodeId(true);
				} else {
					// Third-party object type plugins
					// Set the hash_payload as '<plugin oid>:<id>'
					$hash_payload = $ns_oid.':'.$this->nodeId(false);
				}
				$oid = $sid . '.' . smallhash($hash_payload);
				$ids[] = new OIDplusAltId('oid', $oid, _L('OIDplus Information Object OID'));
			}
		}

		// Make a namebased UUID, but...
		// ... exclude GUID, because a GUID is already a GUID
		// ... exclude OID, because an OID already has a record UUID_NAMEBASED_NS_OID (defined by IETF) set by class OIDplusOid
		if (($this->ns() != 'guid') && ($this->ns() != 'oid')) {
			$ids[] = new OIDplusAltId('guid', gen_uuid_md5_namebased(self::UUID_NAMEBASED_NS_OidPlusMisc, $this->nodeId()), _L('Name based version 3 / MD5 UUID with namespace %1','UUID_NAMEBASED_NS_OidPlusMisc'));
			$ids[] = new OIDplusAltId('guid', gen_uuid_sha1_namebased(self::UUID_NAMEBASED_NS_OidPlusMisc, $this->nodeId()), _L('Name based version 5 / SHA1 UUID with namespace %1','UUID_NAMEBASED_NS_OidPlusMisc'));
		}

		// Make a AID based on ViaThinkSoft schema
		// ... but not for OIDs below oid:1.3.6.1.4.1.37476.30.9, because these are the definition of these Information Object AIDs (which will be decoded in the OID object type plugin)
		if (($this->ns() != 'aid') && !str_starts_with($this->nodeId(true), 'oid:1.3.6.1.4.1.37476.30.9.')) {
			$sid = OIDplus::getSystemId(false);
			if ($sid !== false) {
				$ns_oid = $this->getPlugin()->getManifest()->getOid();
				if (str_starts_with($ns_oid, '1.3.6.1.4.1.37476.2.5.2.')) {
					// Official ViaThinkSoft object type plugins
					// For backwards compatibility with existing IDs,
					// set the hash_payload as '<namespace>:<id>'
					$hash_payload = $this->nodeId(true);
				} else {
					// Third-party object type plugins
					// Set the hash_payload as '<plugin oid>:<id>'
					$hash_payload = $ns_oid.':'.$this->nodeId(false);
				}

				$sid_hex = strtoupper(str_pad(dechex((int)$sid),8,'0',STR_PAD_LEFT));
				$obj_hex = strtoupper(str_pad(dechex(smallhash($hash_payload)),8,'0',STR_PAD_LEFT));
				$aid = 'D276000186B20005'.$sid_hex.$obj_hex;
				$ids[] = new OIDplusAltId('aid', $aid, _L('OIDplus Information Object Application Identifier (ISO/IEC 7816)'), ' ('._L('No PIX allowed').')');
			}
		}

		return $ids;
	}

	/**
	 * @return string
	 */
	public abstract static function objectTypeTitle(): string;

	/**
	 * @return string
	 */
	public abstract static function objectTypeTitleShort(): string;

	/**
	 * @return OIDplusObjectTypePlugin|null
	 */
	public function getPlugin()/*: ?OIDplusObjectTypePlugin */ {
		$plugins = OIDplus::getObjectTypePlugins();
		foreach ($plugins as $plugin) {
			if (get_class($this) == $plugin::getObjectTypeClassName()) {
				return $plugin;
			}
		}
		return null;
	}

	/**
	 * @return string
	 */
	public abstract static function ns(): string;

	/**
	 * @return string
	 */
	public abstract static function root(): string;

	/**
	 * @return bool
	 */
	public abstract function isRoot(): bool;

	/**
	 * @param bool $with_ns
	 * @return string
	 */
	public abstract function nodeId(bool $with_ns=true): string;

	/**
	 * @param string $str
	 * @return string mixed
	 * @throws OIDplusException
	 */
	public abstract function addString(string $str): string;

	/**
	 * @param OIDplusObject $parent
	 * @return string
	 */
	public abstract function crudShowId(OIDplusObject $parent): string;

	/**
	 * @return string
	 */
	public function crudInsertPrefix(): string {
		return '';
	}

	/**
	 * @return string
	 */
	public function crudInsertSuffix(): string {
		return '';
	}

	/**
	 * @param OIDplusObject|null $parent
	 * @return string
	 */
	public abstract function jsTreeNodeName(OIDplusObject $parent = null): string;

	/**
	 * @return string
	 */
	public abstract function defaultTitle(): string;

	/**
	 * @return bool
	 */
	public abstract function isLeafNode(): bool;

	/**
	 * @param string $title
	 * @param string $content
	 * @param string $icon
	 * @return void
	 */
	public abstract function getContentPage(string &$title, string &$content, string &$icon);

	/**
	 * @param OIDplusRA|string|null $ra
	 * @return array
	 * @throws OIDplusConfigInitializationException
	 * @throws OIDplusException
	 */
	public static function getRaRoots($ra=null) : array{
		if ($ra instanceof OIDplusRA) $ra = $ra->raEmail();

		$out = array();

		if (!OIDplus::baseConfig()->getValue('OBJECT_CACHING', true)) {
			if (!$ra) {
				$res = OIDplus::db()->query("select oChild.id as child_id, oChild.ra_email as child_mail, oParent.ra_email as parent_mail from ###objects as oChild ".
				                            "left join ###objects as oParent on oChild.parent = oParent.id");
				$res->naturalSortByField('oChild.id');
				while ($row = $res->fetch_array()) {
					if (!OIDplus::authUtils()->isRaLoggedIn($row['parent_mail']) && OIDplus::authUtils()->isRaLoggedIn($row['child_mail'])) {
						$x = self::parse($row['child_id']); // can be NULL if namespace was disabled
						if ($x) $out[] = $x;
					}
				}
			} else {
				$res = OIDplus::db()->query("select oChild.id as child_id from ###objects as oChild ".
				                            "left join ###objects as oParent on oChild.parent = oParent.id ".
				                            "where (".OIDplus::db()->getSlang()->isNullFunction('oParent.ra_email',"''")." <> ? and ".
				                            OIDplus::db()->getSlang()->isNullFunction('oChild.ra_email',"''")." = ?) or ".
				                            "      (oParent.ra_email is null and ".OIDplus::db()->getSlang()->isNullFunction('oChild.ra_email',"''")." = ?) ",
				                            array($ra, $ra, $ra));
				$res->naturalSortByField('oChild.id');
				while ($row = $res->fetch_array()) {
					$x = self::parse($row['child_id']); // can be NULL if namespace was disabled
					if ($x) $out[] = $x;
				}
			}
		} else {
			if (!$ra) {
				$ra_mails_to_check = OIDplus::authUtils()->loggedInRaList();
				if (count($ra_mails_to_check) == 0) return $out;
			} else {
				$ra_mails_to_check = array($ra);
			}

			self::buildObjectInformationCache();

			foreach ($ra_mails_to_check as $check_ra_mail) {
				$out_part = array();

				foreach (self::$object_info_cache as $id => $cacheitem) {
					if ($cacheitem[self::CACHE_RA_EMAIL] == $check_ra_mail) {
						$parent = $cacheitem[self::CACHE_PARENT];
						if (!isset(self::$object_info_cache[$parent]) || (self::$object_info_cache[$parent][self::CACHE_RA_EMAIL] != $check_ra_mail)) {
							$out_part[] = $id;
						}
					}
				}

				natsort($out_part);

				foreach ($out_part as $id) {
					$obj = self::parse($id);
					if ($obj) $out[] = $obj;
				}
			}
		}

		return $out;
	}

	/**
	 * @return array
	 * @throws OIDplusException
	 */
	public static function getAllNonConfidential(): array {
		$out = array();

		if (!OIDplus::baseConfig()->getValue('OBJECT_CACHING', true)) {
			$res = OIDplus::db()->query("select id from ###objects where confidential = ?", array(false));
			$res->naturalSortByField('id');
			while ($row = $res->fetch_array()) {
				$obj = self::parse($row['id']); // will be NULL if the object type is not registered
				if ($obj && (!$obj->isConfidential())) {
					$out[] = $row['id'];
				}
			}
		} else {
			self::buildObjectInformationCache();

			foreach (self::$object_info_cache as $id => $cacheitem) {
				$confidential = $cacheitem[self::CACHE_CONFIDENTIAL];
				if (!$confidential) {
					$obj = self::parse($id); // will be NULL if the object type is not registered
					if ($obj && (!$obj->isConfidential())) {
						$out[] = $id;
					}
				}
			}
		}

		return $out;
	}

	/**
	 * @return bool
	 * @throws OIDplusException
	 */
	public function isConfidential(): bool {
		if (!OIDplus::baseConfig()->getValue('OBJECT_CACHING', true)) {
			//static $confidential_cache = array();
			$curid = $this->nodeId();
			//$orig_curid = $curid;
			//if (isset($confidential_cache[$curid])) return $confidential_cache[$curid];
			// Recursively search for the confidential flag in the parents
			while (($res = OIDplus::db()->query("select parent, confidential from ###objects where id = ?", array($curid)))->any()) {
				$row = $res->fetch_array();
				if ($row['confidential']) {
					//$confidential_cache[$curid] = true;
					//$confidential_cache[$orig_curid] = true;
					return true;
				} else {
					//$confidential_cache[$curid] = false;
				}
				$curid = $row['parent'];
				//if (isset($confidential_cache[$curid])) {
					//$confidential_cache[$orig_curid] = $confidential_cache[$curid];
					//return $confidential_cache[$curid];
				//}
			}

			//$confidential_cache[$orig_curid] = false;
			return false;
		} else {
			self::buildObjectInformationCache();

			$curid = $this->nodeId();
			// Recursively search for the confidential flag in the parents
			while (isset(self::$object_info_cache[$curid])) {
				if (self::$object_info_cache[$curid][self::CACHE_CONFIDENTIAL]) return true;
				$curid = self::$object_info_cache[$curid][self::CACHE_PARENT];
			}
			return false;
		}
	}

	/**
	 * @param OIDplusObject $obj
	 * @return bool
	 * @throws OIDplusException
	 */
	public function isChildOf(OIDplusObject $obj): bool {
		if (!OIDplus::baseConfig()->getValue('OBJECT_CACHING', true)) {
			$curid = $this->nodeId();
			while (($res = OIDplus::db()->query("select parent from ###objects where id = ?", array($curid)))->any()) {
				$row = $res->fetch_array();
				if ($curid == $obj->nodeId()) return true;
				$curid = $row['parent'];
			}
			return false;
		} else {
			self::buildObjectInformationCache();

			$curid = $this->nodeId();
			while (isset(self::$object_info_cache[$curid])) {
				if ($curid == $obj->nodeId()) return true;
				$curid = self::$object_info_cache[$curid][self::CACHE_PARENT];
			}
			return false;
		}
	}

	/**
	 * @return array
	 * @throws OIDplusException
	 */
	public function getChildren(): array {
		$out = array();
		if (!OIDplus::baseConfig()->getValue('OBJECT_CACHING', true)) {
			$res = OIDplus::db()->query("select id from ###objects where parent = ?", array($this->nodeId()));
			while ($row = $res->fetch_array()) {
				$obj = self::parse($row['id']);
				if (!$obj) continue;
				$out[] = $obj;
			}
		} else {
			self::buildObjectInformationCache();

			foreach (self::$object_info_cache as $id => $cacheitem) {
				$parent = $cacheitem[self::CACHE_PARENT];
				if ($parent == $this->nodeId()) {
					$obj = self::parse($id);
					if (!$obj) continue;
					$out[] = $obj;
				}
			}
		}
		return $out;
	}

	/**
	 * @return OIDplusRA|null
	 * @throws OIDplusException
	 */
	public function getRa()/*: ?OIDplusRA*/ {
		$ra = $this->getRaMail();
		return $ra ? new OIDplusRA($ra) : null;
	}

	/**
	 * @param OIDplusRA|string|null $ra
	 * @return bool
	 * @throws OIDplusConfigInitializationException
	 * @throws OIDplusException
	 */
	public function userHasReadRights($ra=null): bool {
		if ($ra instanceof OIDplusRA) $ra = $ra->raEmail();

		// If it is not confidential, everybody can read/see it.
		// Note: This also checks if superior OIDs are confidential.
		if (!$this->isConfidential()) return true;

		if (!$ra) {
			// Admin may do everything
			if (OIDplus::authUtils()->isAdminLoggedIn()) return true;

			// If the RA is logged in, then they can see the OID.
			$ownRa = $this->getRaMail();
			if ($ownRa && OIDplus::authUtils()->isRaLoggedIn($ownRa)) return true;
		} else {
			// If this OID belongs to the requested RA, then they may see it.
			if ($this->getRaMail() == $ra) return true;
		}

		// If someone has rights to an object below our confidential node,
		// we let him see the confidential node,
		// Otherwise he could not browse through to his own node.
		$roots = $this->getRaRoots($ra);
		foreach ($roots as $root) {
			if ($root->isChildOf($this)) return true;
		}

		return false;
	}

	/**
	 * @param array|null $row
	 * @return string|null
	 * @throws OIDplusException
	 */
	public function getIcon(array $row=null) {
		$namespace = $this->ns(); // must use $this, not self::, otherwise the virtual method will not be called

		if (is_null($row)) {
			$ra_email = $this->getRaMail();
		} else {
			$ra_email = $row['ra_email'];
		}

		// $dirs = glob(OIDplus::localpath().'plugins/'.'*'.'/objectTypes/'.$namespace.'/');
		// if (count($dirs) == 0) return null; // default icon (folder)
		// $dir = substr($dirs[0], strlen(OIDplus::localpath()));
		$reflection = new \ReflectionClass($this);
		$dir = dirname($reflection->getFilename());
		$dir = substr($dir, strlen(OIDplus::localpath()));
		$dir = str_replace('\\', '/', $dir);

		if ($this->isRoot()) {
			$icon = $dir . '/' . $this::treeIconFilename('root');
		} else {
			// We use $this:: instead of self:: , because we want to call the overridden methods
			if ($ra_email && OIDplus::authUtils()->isRaLoggedIn($ra_email)) {
				if ($this->isLeafNode()) {
					$icon = $dir . '/' . $this::treeIconFilename('own_leaf');
					if (!file_exists($icon)) $icon = $dir . '/' . $this::treeIconFilename('own');
				} else {
					$icon = $dir . '/' . $this::treeIconFilename('own');
				}
			} else {
				if ($this->isLeafNode()) {
					$icon = $dir . '/' . $this::treeIconFilename('general_leaf');
					if (!file_exists($icon)) $icon = $dir . '/' . $this::treeIconFilename('general');
				} else {
					$icon = $dir . '/' . $this::treeIconFilename('general');
				}
			}
		}

		if (!file_exists($icon)) return null; // default icon (folder)

		return $icon;
	}

	/**
	 * @param string $id
	 * @return bool
	 * @throws OIDplusException
	 */
	public static function exists(string $id): bool {
		if (!OIDplus::baseConfig()->getValue('OBJECT_CACHING', true)) {
			$res = OIDplus::db()->query("select id from ###objects where id = ?", array($id));
			return $res->any();
		} else {
			self::buildObjectInformationCache();
			return isset(self::$object_info_cache[$id]);
		}
	}

	/**
	 * Get parent gives the next possible parent which is EXISTING in OIDplus
	 * It does not give the immediate parent
	 * @return OIDplusObject|null
	 * @throws OIDplusException
	 */
	public function getParent()/*: ?OIDplusObject*/ {
		if (!OIDplus::baseConfig()->getValue('OBJECT_CACHING', true)) {
			$res = OIDplus::db()->query("select parent from ###objects where id = ?", array($this->nodeId()));
			if ($res->any()) {
				$row = $res->fetch_array();
				$parent = $row['parent'];
				$obj = OIDplusObject::parse($parent);
				if ($obj) return $obj;
			}
		} else {
			self::buildObjectInformationCache();
			if (isset(self::$object_info_cache[$this->nodeId()])) {
				$parent = self::$object_info_cache[$this->nodeId()][self::CACHE_PARENT];
				$obj = OIDplusObject::parse($parent);
				if ($obj) return $obj;
			}
		}

		// If this OID does not exist, the SQL query "select parent from ..." does not work. So we try to find the next possible parent using one_up()
		$cur = $this->one_up();
		if (!$cur) return null;
		do {
			// findFitting() checks if that OID exists
			if ($fitting = self::findFitting($cur->nodeId())) return $fitting;

			$prev = $cur;
			$cur = $cur->one_up();
			if (!$cur) return null;
		} while ($prev->nodeId() !== $cur->nodeId());

		return null;
	}

	/**
	 * @return string|null
	 * @throws OIDplusException
	 */
	public function getRaMail() {
		if (!OIDplus::baseConfig()->getValue('OBJECT_CACHING', true)) {
			$res = OIDplus::db()->query("select ra_email from ###objects where id = ?", array($this->nodeId()));
			if (!$res->any()) return null;
			$row = $res->fetch_array();
			return $row['ra_email'];
		} else {
			self::buildObjectInformationCache();
			if (isset(self::$object_info_cache[$this->nodeId()])) {
				return self::$object_info_cache[$this->nodeId()][self::CACHE_RA_EMAIL];
			}
			return null;
		}
	}

	/**
	 * @return string|null
	 * @throws OIDplusException
	 */
	public function getTitle() {
		if (!OIDplus::baseConfig()->getValue('OBJECT_CACHING', true)) {
			$res = OIDplus::db()->query("select title from ###objects where id = ?", array($this->nodeId()));
			if (!$res->any()) return null;
			$row = $res->fetch_array();
			return $row['title'];
		} else {
			self::buildObjectInformationCache();
			if (isset(self::$object_info_cache[$this->nodeId()])) {
				return self::$object_info_cache[$this->nodeId()][self::CACHE_TITLE];
			}
			return null;
		}
	}

	/**
	 * @return string|null
	 * @throws OIDplusException
	 */
	public function getDescription() {
		if (!OIDplus::baseConfig()->getValue('OBJECT_CACHING', true)) {
			$res = OIDplus::db()->query("select description from ###objects where id = ?", array($this->nodeId()));
			if (!$res->any()) return null;
			$row = $res->fetch_array();
			return $row['description'];
		} else {
			self::buildObjectInformationCache();
			if (isset(self::$object_info_cache[$this->nodeId()])) {
				return self::$object_info_cache[$this->nodeId()][self::CACHE_DESCRIPTION];
			}
			return null;
		}
	}

	/**
	 * @return string|null
	 * @throws OIDplusException
	 */
	public function getComment() {
		if (!OIDplus::baseConfig()->getValue('OBJECT_CACHING', true)) {
			$res = OIDplus::db()->query("select comment from ###objects where id = ?", array($this->nodeId()));
			if (!$res->any()) return null;
			$row = $res->fetch_array();
			return $row['comment'];
		} else {
			self::buildObjectInformationCache();
			if (isset(self::$object_info_cache[$this->nodeId()])) {
				return self::$object_info_cache[$this->nodeId()][self::CACHE_COMMENT];
			}
			return null;
		}
	}

	/**
	 * @return string|null
	 * @throws OIDplusException
	 */
	public function getCreatedTime() {
		if (!OIDplus::baseConfig()->getValue('OBJECT_CACHING', true)) {
			$res = OIDplus::db()->query("select created from ###objects where id = ?", array($this->nodeId()));
			if (!$res->any()) return null;
			$row = $res->fetch_array();
			return $row['created'];
		} else {
			self::buildObjectInformationCache();
			if (isset(self::$object_info_cache[$this->nodeId()])) {
				return self::$object_info_cache[$this->nodeId()][self::CACHE_CREATED];
			}
			return null;
		}
	}

	/**
	 * @return string|null
	 * @throws OIDplusException
	 */
	public function getUpdatedTime() {
		if (!OIDplus::baseConfig()->getValue('OBJECT_CACHING', true)) {
			$res = OIDplus::db()->query("select updated from ###objects where id = ?", array($this->nodeId()));
			if (!$res->any()) return null;
			$row = $res->fetch_array();
			return $row['updated'];
		} else {
			self::buildObjectInformationCache();
			if (isset(self::$object_info_cache[$this->nodeId()])) {
				return self::$object_info_cache[$this->nodeId()][self::CACHE_UPDATED];
			}
			return null;
		}
	}

	/**
	 * @param OIDplusRA|string|null $ra
	 * @return bool
	 * @throws OIDplusException
	 */
	public function userHasParentalWriteRights($ra=null): bool {
		if ($ra instanceof OIDplusRA) $ra = $ra->raEmail();

		if (!$ra) {
			if (OIDplus::authUtils()->isAdminLoggedIn()) return true;
		}

		$objParent = $this->getParent();
		if (!$objParent) return false;
		return $objParent->userHasWriteRights($ra);
	}

	/**
	 * @param OIDplusRA|string|null $ra
	 * @return bool
	 * @throws OIDplusException
	 */
	public function userHasWriteRights($ra=null): bool {
		if ($ra instanceof OIDplusRA) $ra = $ra->raEmail();

		if (!$ra) {
			if (OIDplus::authUtils()->isAdminLoggedIn()) return true;
			// TODO: should we allow that the parent RA also may update title/description about this OID (since they delegated it?)
			$ownRa = $this->getRaMail();
			return $ownRa && OIDplus::authUtils()->isRaLoggedIn($ownRa);
		} else {
			return $this->getRaMail() == $ra;
		}
	}

	/**
	 * @param string|OIDplusObject $to
	 * @return int|null
	 */
	public function distance($to)/*: ?int*/ {
		return null; // not implemented
	}

	/**
	 * @param OIDplusObject|string $obj
	 * @return bool
	 */
	public function equals($obj): bool {
		if (!$obj) return false;
		if (!is_object($obj)) $obj = OIDplusObject::parse($obj);
		if (!$obj) return false;
		if (!($obj instanceof $this)) return false;

		$distance = $this->distance($obj);
		if (is_numeric($distance)) return $distance === 0; // if the distance function is implemented, use it

		return $this->nodeId() == $obj->nodeId(); // otherwise compare the node id case-sensitive
	}

	/**
	 * @param string $id
	 * @return OIDplusObject|false
	 * @throws OIDplusException
	 */
	public static function findFitting(string $id) {
		$obj = OIDplusObject::parse($id);
		if (!$obj) return false; // e.g. if ObjectType plugin is disabled

		if (!OIDplus::baseConfig()->getValue('OBJECT_CACHING', true)) {
			$res = OIDplus::db()->query("select id from ###objects where id like ?", array($obj->ns().':%'));
			while ($row = $res->fetch_object()) {
				$test = OIDplusObject::parse($row->id);
				if ($obj->equals($test)) return $test;
			}
			return false;
		} else {
			self::buildObjectInformationCache();
			foreach (self::$object_info_cache as $id => $cacheitem) {
				if (strpos($id, $obj->ns().':') === 0) {
					$test = OIDplusObject::parse($id);
					if ($obj->equals($test)) return $test;
				}
			}
			return false;
		}
	}

	/**
	 * @return OIDplusObject|null
	 */
	public function one_up()/*: ?OIDplusObject*/ {
		return null; // not implemented
	}

	// Caching stuff

	protected static $object_info_cache = null;

	/**
	 * @return void
	 */
	public static function resetObjectInformationCache() {
		self::$object_info_cache = null;
	}

	const CACHE_ID = 'id';
	const CACHE_PARENT = 'parent';
	const CACHE_TITLE = 'title';
	const CACHE_DESCRIPTION = 'description';
	const CACHE_RA_EMAIL = 'ra_email';
	const CACHE_CONFIDENTIAL = 'confidential';
	const CACHE_CREATED = 'created';
	const CACHE_UPDATED = 'updated';
	const CACHE_COMMENT = 'comment';

	/**
	 * @return void
	 * @throws OIDplusException
	 */
	private static function buildObjectInformationCache() {
		if (is_null(self::$object_info_cache)) {
			self::$object_info_cache = array();
			$res = OIDplus::db()->query("select * from ###objects");
			while ($row = $res->fetch_array()) {
				self::$object_info_cache[$row['id']] = $row;
			}
		}
	}

	/**
	 * override this function if you want your object type to save
	 * attachments in directories with easy names.
	 * Take care that your custom directory name will not allow jailbreaks (../) !
	 * @return string
	 * @throws OIDplusException
	 */
	public function getDirectoryName(): string {
		if ($this->isRoot()) return $this->ns();
		return $this->getLegacyDirectoryName();
	}

	/**
	 * @return string
	 * @throws OIDplusException
	 */
	public final function getLegacyDirectoryName(): string {
		if ($this::ns() == 'oid') {
			$oid = $this->nodeId(false);
		} else {
			$oid = null;
			$alt_ids = $this->getAltIds();
			foreach ($alt_ids as $alt_id) {
				if ($alt_id->getNamespace() == 'oid') {
					$oid = $alt_id->getId();
					break; // we prefer the first OID (for GUIDs, the first OID is the OIDplus-OID, and the second OID is the UUID OID)
				}
			}
		}

		if (!is_null($oid) && ($oid != '')) {
			// For OIDs, it is the OID, for other identifiers
			// it it the OID alt ID (generated using the SystemID)
			return str_replace('.', '_', $oid);
		} else {
			// Can happen if you don't have a system ID (due to missing OpenSSL plugin)
			return md5($this->nodeId(true)); // we don't use $id, because $this->nodeId(true) is possibly more canonical than $id
		}
	}

	/**
	 * @param string $mode
	 * @return string
	 */
	public static function treeIconFilename(string $mode): string {
		// for backwards-compatibility with older plugins
		return 'img/treeicon_'.$mode.'.png';
	}

}
