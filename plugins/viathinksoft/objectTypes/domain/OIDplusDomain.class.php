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

class OIDplusDomain extends OIDplusObject {
	/**
	 * @var string
	 */
	private $domain;

	/**
	 * @param string $domain
	 */
	public function __construct(string $domain) {
		// TODO: syntax checks
		$this->domain = $domain;
	}

	/**
	 * @param string $node_id
	 * @return OIDplusDomain|null
	 */
	public static function parse(string $node_id)/*: ?OIDplusDomain*/ {
		@list($namespace, $domain) = explode(':', $node_id, 2);
		if ($namespace !== self::ns()) return null;
		return new self($domain);
	}

	/**
	 * @return string
	 */
	public static function objectTypeTitle(): string {
		return _L('Domain Names');
	}

	/**
	 * @return string
	 */
	public static function objectTypeTitleShort(): string {
		return _L('Domain');
	}

	/**
	 * @return string
	 */
	public static function ns(): string {
		return 'domain';
	}

	/**
	 * @return string
	 */
	public static function root(): string {
		return self::ns().':';
	}

	/**
	 * @return bool
	 */
	public function isRoot(): bool {
		return $this->domain == '';
	}

	/**
	 * @param bool $with_ns
	 * @return string
	 */
	public function nodeId(bool $with_ns=true): string {
		return $with_ns ? self::root().$this->domain : $this->domain;
	}

	/**
	 * @param string $str
	 * @return string
	 * @throws OIDplusException
	 */
	public function addString(string $str): string {
		if ($this->isRoot()) {
			return self::root().$str;
		} else {
			if (strpos($str,'.') !== false) throw new OIDplusException(_L('Please only submit one arc.'));
			return self::root().$str.'.'.$this->nodeId(false);
		}
	}

	/**
	 * @param OIDplusObject $parent
	 * @return string
	 */
	public function crudShowId(OIDplusObject $parent): string {
		return $this->domain;
	}

	/**
	 * @return string
	 * @throws OIDplusException
	 */
	public function crudInsertSuffix(): string {
		return $this->isRoot() ? '' : substr($this->addString(''), strlen(self::ns())+1);
	}

	/**
	 * @param OIDplusObject|null $parent
	 * @return string
	 */
	public function jsTreeNodeName(OIDplusObject $parent = null): string {
		if ($parent == null) return $this->objectTypeTitle();
		return $this->domain;
	}

	/**
	 * @return string
	 */
	public function defaultTitle(): string {
		return $this->domain;
	}

	/**
	 * @return bool
	 */
	public function isLeafNode(): bool {
		return false;
	}

	/**
	 * @param string $title
	 * @param string $content
	 * @param string $icon
	 * @return void
	 * @throws OIDplusException
	 */
	public function getContentPage(string &$title, string &$content, string &$icon) {
		$icon = file_exists(__DIR__.'/img/main_icon.png') ? OIDplus::webpath(__DIR__,OIDplus::PATH_RELATIVE).'img/main_icon.png' : '';

		if ($this->isRoot()) {
			$title = OIDplusDomain::objectTypeTitle();

			$res = OIDplus::db()->query("select * from ###objects where parent = ?", array(self::root()));
			if ($res->any()) {
				$content  = '<p>'._L('Please select a Domain Name in the tree view at the left to show its contents.').'</p>';
			} else {
				$content  = '<p>'._L('Currently, no Domain Name is registered in the system.').'</p>';
			}

			if (!$this->isLeafNode()) {
				if (OIDplus::authUtils()->isAdminLoggedIn()) {
					$content .= '<h2>'._L('Manage root objects').'</h2>';
				} else {
					$content .= '<h2>'._L('Available objects').'</h2>';
				}
				$content .= '%%CRUD%%';
			}
		} else {
			$title = $this->getTitle();

			$content = '<h3>'.explode(':',$this->nodeId())[1].'</h3>';

			$content .= '<h2>'._L('Description').'</h2>%%DESC%%'; // TODO: add more meta information about the object type

			if (!$this->isLeafNode()) {
				if ($this->userHasWriteRights()) {
					$content .= '<h2>'._L('Create or change subordinate objects').'</h2>';
				} else {
					$content .= '<h2>'._L('Subordinate objects').'</h2>';
				}
				$content .= '%%CRUD%%';
			}
		}
	}

	/**
	 * @return OIDplusDomain|null
	 */
	public function one_up()/*: ?OIDplusDomain*/ {
		$oid = $this->domain;

		$p = strpos($oid, '.');
		if ($p === false) return self::parse('');

		$oid_up = substr($oid, $p+1);

		return self::parse(self::ns().':'.$oid_up);
	}

	/**
	 * @param OIDplusObject|string $to
	 * @return int|null
	 */
	public function distance($to) {
		if (!is_object($to)) $to = OIDplusObject::parse($to);
		if (!$to) return null;
		if (!($to instanceof $this)) return null;

		$a = $to->domain;
		$b = $this->domain;

		if (substr($a,-1) == '.') $a = substr($a,0,strlen($a)-1);
		if (substr($b,-1) == '.') $b = substr($b,0,strlen($b)-1);

		$ary = explode('.', $a);
		$bry = explode('.', $b);

		$ary = array_reverse($ary);
		$bry = array_reverse($bry);

		$min_len = min(count($ary), count($bry));

		for ($i=0; $i<$min_len; $i++) {
			if ($ary[$i] != $bry[$i]) return null;
		}

		return count($ary) - count($bry);
	}

	/**
	 * @return string
	 */
	public function getDirectoryName(): string {
		if ($this->isRoot()) return $this->ns();
		return $this->ns().'_'.md5($this->nodeId(false));
	}

	/**
	 * @param string $mode
	 * @return string
	 */
	public static function treeIconFilename(string $mode): string {
		return 'img/'.$mode.'_icon16.png';
	}
}
