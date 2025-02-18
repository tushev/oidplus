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

class OIDplusMenuUtils extends OIDplusBaseClass {

	/**
	 * @return string
	 * @throws OIDplusConfigInitializationException
	 * @throws OIDplusException
	 */
	public function nonjs_menu(): string {
		$json = array();

		$static_node_id = $_REQUEST['goto'] ?? 'oidplus:system';

		foreach (OIDplus::getPagePlugins() as $plugin) {
			// Note: The system (OIDplusMenuUtils) does only show the menu of
			//       publicPage plugins. Menu entries for RAs and Admins are
			//       handled by the tree() function of the plugin publicPages/090_login
			if (is_subclass_of($plugin, OIDplusPagePluginPublic::class)) {
				$plugin->tree($json, null, true, $static_node_id);
			}
		}

		$out = '';
		foreach ($json as $x) {
			if ($static_node_id == $x['id']) $out .= '<b>';
			if (isset($x['indent'])) $out .= str_repeat('&nbsp;', $x['indent']*5);
			$cur_lang = OIDplus::getCurrentLang();
			if ($cur_lang != OIDplus::getDefaultLang()) {
				$out .= '<a href="?lang='.$cur_lang.'&amp;goto='.urlencode($x['id']).'">';
			} else {
				$out .= '<a href="?goto='.urlencode($x['id']).'">';
			}
			if (!empty($x['icon'])) $out .= '<img src="'.$x['icon'].'" alt=""> ';
			$out .= htmlentities($x['id']).' | '.htmlentities($x['text']).'</a><br>';
			if ($static_node_id == $x['id']) $out .= '</b>';
		}
		return $out;
	}

	/**
	 * @param string $req_id comes from jsTree via AJAX
	 * @param string $req_goto comes from the user (GET argument)
	 * @return string[]
	 */
	public function json_tree(string $req_id, string $req_goto): array {
		$json = array();

		if ($req_id === '#') {
			foreach (OIDplus::getPagePlugins() as $plugin) {
				// Note: The system (OIDplusMenuUtils) does only show the menu of
				//       publicPage plugins. Menu entries for RAs and Admins are
				//       handled by the tree() function of the plugin publicPages/090_login
				if (is_subclass_of($plugin, OIDplusPagePluginPublic::class)) {
					$plugin->tree($json, null, false, $req_goto);
				}
			}
		} else {
			$json = $this->tree_populate($req_id);
		}

		if (is_array($json)) $this->addHrefIfRequired($json);

		return $json;
	}

	/**
	 * @param array $json
	 * @return void
	 */
	protected function addHrefIfRequired(array &$json) {
		foreach ($json as &$item) {
			if (isset($item['id'])) {
				if (!isset($item['conditionalselect']) || ($item['conditionalselect'] != 'false')) {
					if (!isset($item['a_attr'])) {
						$item['a_attr'] = array("href" => "?goto=".urlencode($item['id']));
					} else if (!isset($item['a_attr']['href'])) {
						$item['a_attr']['href'] = "?goto=".urlencode($item['id']);
					}
				}
			}

			if (isset($item['children'])) {
				if (is_array($item['children'])) $this->addHrefIfRequired($item['children']);
			}
		}
		unset($item);
	}

	/**
	 * @param string $parent
	 * @param array|true|null $goto_path
	 * @return array
	 * @throws OIDplusConfigInitializationException
	 * @throws OIDplusException
	 */
	public function tree_populate(string $parent, $goto_path=null): array {
		$children = array();

		$parentObj = OIDplusObject::parse($parent);

		if (is_array($goto_path)) array_shift($goto_path);

		$res = OIDplus::db()->query("select * from ###objects where parent = ?", array($parent));
		$res->naturalSortByField('id');
		while ($row = $res->fetch_array()) {
			$obj = OIDplusObject::parse($row['id']);
			if (!$obj) continue; // e.g. object-type plugin disabled

			if (!$obj->userHasReadRights()) continue;

			$child = array();
			$child['id'] = $row['id'];

			// Determine display name (relative OID)
			$child['text'] = $parentObj ? $obj->jsTreeNodeName($parentObj) : '';
			$child['text'] .= empty($row['title']) ? /*' -- <i>'.htmlentities('Title missing').'</i>'*/ '' : ' -- <b>' . htmlentities($row['title']) . '</b>';

			// Check if node is confidential, or if one of its parent was confidential
			$is_confidential = $obj->isConfidential();
			if ($is_confidential) {
				$child['text'] = '<font color="gray"><i>'.$child['text'].'</i></font>';
			}

			// Determine icon
			$child['icon'] = $obj->getIcon($row);

			// Check if there are more sub OIDs
			if ($goto_path === true) {
				$child['children'] = $this->tree_populate($row['id'], $goto_path);
				$child['state'] = array("opened" => true);
			} else if (!is_null($goto_path) && (count($goto_path) > 0) && ($goto_path[0] === $row['id'])) {
				$child['children'] = $this->tree_populate($row['id'], $goto_path);
				$child['state'] = array("opened" => true);
			} else {
				$obj_children = $obj->getChildren();

				// Variant 1: Fast, but does not check for hidden OIDs
				//$child_count = count($obj_children);

				// variant 2
				$child_count = 0;
				foreach ($obj_children as $obj_test) {
					if (!$obj_test->userHasReadRights()) continue;
					$child_count++;
				}

				$child['children'] = $child_count > 0;
			}

			$children[] = $child;
		}

		return $children;
	}
}
