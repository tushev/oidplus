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

class OIDplusJava extends OIDplusObject {
	/**
	 * @var string
	 */
	private $java;

	/**
	 * @param string $java
	 */
	public function __construct(string $java) {
		// TODO: syntax checks
		$this->java = $java;
	}

	/**
	 * @param string $node_id
	 * @return OIDplusJava|null
	 */
	public static function parse(string $node_id)/*: ?OIDplusJava*/ {
		@list($namespace, $java) = explode(':', $node_id, 2);
		if ($namespace !== self::ns()) return null;
		return new self($java);
	}

	/**
	 * @return string
	 */
	public static function objectTypeTitle(): string {
		return _L('Java Package Names');
	}

	/**
	 * @return string
	 */
	public static function objectTypeTitleShort(): string {
		return _L('Package');
	}

	/**
	 * @return string
	 */
	public static function ns(): string {
		return 'java';
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
		return $this->java == '';
	}

	/**
	 * @param bool $with_ns
	 * @return string
	 */
	public function nodeId(bool $with_ns=true): string {
		return $with_ns ? self::root().$this->java : $this->java;
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
			return $this->nodeId() . '.' . $str;
		}
	}

	/**
	 * @param OIDplusObject $parent
	 * @return string
	 */
	public function crudShowId(OIDplusObject $parent): string {
		return $this->java;
	}

	/**
	 * @return string
	 * @throws OIDplusException
	 */
	public function crudInsertPrefix(): string {
		return $this->isRoot() ? '' : substr($this->addString(''), strlen(self::ns())+1);
	}

	/**
	 * @param OIDplusObject|null $parent
	 * @return string
	 */
	public function jsTreeNodeName(OIDplusObject $parent = null): string {
		if ($parent == null) return $this->objectTypeTitle();
		if ($parent->isRoot()) {
			return substr($this->nodeId(), strlen($parent->nodeId()));
		} else {
			return substr($this->nodeId(), strlen($parent->nodeId())+1);
		}
	}

	/**
	 * @return string
	 */
	public function defaultTitle(): string {
		return $this->java;
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
			$title = OIDplusJava::objectTypeTitle();

			$res = OIDplus::db()->query("select * from ###objects where parent = ?", array(self::root()));
			if ($res->any()) {
				$content  = '<p>'._L('Please select a Java Package Name in the tree view at the left to show its contents.').'</p>';
			} else {
				$content  = '<p>'._L('Currently, no Java Package Name is registered in the system.').'</p>';
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
	 * @return OIDplusJava|null
	 */
	public function one_up()/*: ?OIDplusJava*/ {
		$oid = $this->java;

		$p = strrpos($oid, '.');
		if ($p === false) return self::parse($oid);
		if ($p == 0) return self::parse('.');

		$oid_up = substr($oid, 0, $p);

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

		$a = $to->java;
		$b = $this->java;

		if (substr($a,0,1) == '.') $a = substr($a,1);
		if (substr($b,0,1) == '.') $b = substr($b,1);

		$ary = explode('.', $a);
		$bry = explode('.', $b);

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
