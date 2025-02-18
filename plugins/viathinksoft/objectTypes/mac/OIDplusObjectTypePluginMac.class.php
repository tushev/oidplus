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

class OIDplusObjectTypePluginMac extends OIDplusObjectTypePlugin
	implements INTF_OID_1_3_6_1_4_1_37476_2_5_2_3_6 /* gridGeneratorLinks */
{

	/**
	 * @return string
	 */
	public static function getObjectTypeClassName(): string {
		return OIDplusMac::class;
	}

	/**
	 * @param array $params
	 * @return array
	 * @throws OIDplusException
	 */
	private function action_GenerateAAI(array $params): array {
		_CheckParamExists($params, 'aai_bits');
		_CheckParamExists($params, 'aai_multicast');

		if (($params['aai_bits'] != '48') && ($params['aai_bits'] != '64')) {
			throw new OIDplusException(_L("Invalid bit amount"));
		}

		$aai = '';
		for ($i=0; $i<$params['aai_bits']/4; $i++) {
			try {
				$aai .= dechex(random_int(0, 15));
			} catch (\Exception $e) {
				$aai .= dechex(mt_rand(0, 15));
			}
		}

		if (oidplus_is_true($params['aai_multicast'] ?? false)) {
			$aai[1] = '3';
		} else {
			$aai[1] = '2';
		}

		$aai = strtoupper($aai);
		$aai = rtrim(chunk_split($aai, 2, '-'), '-');

		return array("status" => 0, "aai" => $aai);
	}

	/**
	 * @param string $actionID
	 * @param array $params
	 * @return array
	 * @throws OIDplusException
	 */
	public function action(string $actionID, array $params): array {
		if ($actionID == 'generate_aai') {
			return $this->action_GenerateAAI($params);
		} else {
			return parent::action($actionID, $params);
		}
	}

	/**
	 * Implements interface INTF_OID_1_3_6_1_4_1_37476_2_5_2_3_6
	 * @param OIDplusObject $objParent
	 * @return string
	 */
	public function gridGeneratorLinks(OIDplusObject $objParent): string {
		if (!$objParent->isRoot()) return '';
		return
			'<br>'._L('Generate a random <abbr title="Administratively Assigned Identifier (not world-wide unique!)">AAI</abbr>').':'.
			'<br>- <abbr title="'._L('Random hexadecimal string, but second nibble must be %1','2').'">Unicast</abbr> <a href="javascript:OIDplusObjectTypePluginMac.generateRandomAAI(48, false)">(AAI-48)</a> | <a href="javascript:OIDplusObjectTypePluginMac.generateRandomAAI(64, false)">(AAI-64)</a>'.
			'<br>- <abbr title="'._L('Random hexadecimal string, but second nibble must be %1','3').'">Multicast</abbr> <a href="javascript:OIDplusObjectTypePluginMac.generateRandomAAI(48, true)">(AAI-48)</a> | <a href="javascript:OIDplusObjectTypePluginMac.generateRandomAAI(64, true)">(AAI-64)</a>'.
			'<br><a href="https://standards.ieee.org/products-programs/regauth/" target="_blank">('._L('Buy an OUI or CID from IEEE').')</a>';
	}

	/**
	 * @param string $static_node_id
	 * @param bool $throw_exception
	 * @return string
	 */
	public static function prefilterQuery(string $static_node_id, bool $throw_exception): string {
		$static_node_id = trim($static_node_id);

		$static_node_id = preg_replace('@^eui:@', 'mac:', $static_node_id);
		$static_node_id = preg_replace('@^eli:@', 'mac:', $static_node_id);

		// Special treatment for MACs: if someone enters a valid MAC address in the goto box, prepend "mac:"
		if (((strpos($static_node_id, ':') !== false) ||
		     (strpos($static_node_id, '-') !== false) ||
		     (strpos($static_node_id, ' ') !== false)) && mac_valid($static_node_id)) {
			$static_node_id = 'mac:'.$static_node_id;
		}

		if (str_starts_with($static_node_id,'mac:')) {
			$static_node_id = 'mac:'.str_replace(array('-',':',' '), '', substr($static_node_id,4));
			$static_node_id = 'mac:'.strtoupper(substr($static_node_id,4));
		}

		return $static_node_id;
	}

}
