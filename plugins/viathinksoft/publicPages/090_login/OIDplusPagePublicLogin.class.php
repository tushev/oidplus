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

class OIDplusPagePublicLogin extends OIDplusPagePluginPublic {

	/**
	 * @param array $params
	 * @return array
	 * @throws OIDplusException
	 */
	private function action_RaLogin(array $params): array {
		OIDplus::getActiveCaptchaPlugin()->captchaVerify($params, 'captcha');

		_CheckParamExists($params, 'email');
		_CheckParamExists($params, 'password');

		$email = $params['email'];
		$ra = new OIDplusRA($email);

		if (empty($email)) {
			throw new OIDplusException(_L('Please enter a valid email address'));
		}

		if ($ra->checkPassword($params['password'])) {
			OIDplus::authUtils()->raLoginEx($email, 'Regular login');

			$authInfo = OIDplus::authUtils()->raGeneratePassword($params['password']);

			// Rehash, so that we always have the latest default auth plugin and params
			// Note that we do it every time (unlike PHPs recommended password_needs_rehash),
			// because we are not sure which auth plugin created the hash (there might be multiple
			// auth plugins that can verify this hash). So we just rehash on every login!
			$new_authkey = $authInfo->getAuthKey();

			OIDplus::db()->query("UPDATE ###ra set last_login = ".OIDplus::db()->sqlDate().", authkey = ? where email = ?", array($new_authkey, $email));

			return array("status" => 0);
		} else {
			if (OIDplus::config()->getValue('log_failed_ra_logins', false)) {
				if ($ra->existing()) {
					OIDplus::logger()->log("V2:[WARN]A", "Failed login to RA account '%1' (wrong password)", $email);
				} else {
					OIDplus::logger()->log("V2:[WARN]A", "Failed login to RA account '%1' (RA not existing)", $email);
				}
			}
			throw new OIDplusException(_L('Wrong password or user not registered'));
		}
	}

	/**
	 * @param array $params
	 * @return array
	 * @throws OIDplusException
	 */
	private function action_RaLogout(array $params): array {
		_CheckParamExists($params, 'email');

		$email = $params['email'];

		OIDplus::authUtils()->raLogoutEx($email);

		return array("status" => 0);
	}

	/**
	 * @param array $params
	 * @return array
	 * @throws OIDplusException
	 */
	private function action_AdminLogin(array $params): array {
		OIDplus::getActiveCaptchaPlugin()->captchaVerify($params, 'captcha');

		_CheckParamExists($params, 'password');
		if (OIDplus::authUtils()->adminCheckPassword($params['password'])) {
			OIDplus::authUtils()->adminLoginEx('Regular login');

			// TODO: Write a "last login" entry in config table?

			return array("status" => 0);
		} else {
			if (OIDplus::config()->getValue('log_failed_admin_logins', false)) {
				OIDplus::logger()->log("V2:[WARN]A", "Failed login to admin account");
			}
			throw new OIDplusException(_L('Wrong password'));
		}
	}

	/**
	 * @param array $params
	 * @return array
	 * @throws OIDplusException
	 */
	private function action_AdminLogout(array $params): array {
		OIDplus::authUtils()->adminLogoutEx();

		return array("status" => 0);
	}

	/**
	 * @param string $actionID
	 * @param array $params
	 * @return array
	 * @throws OIDplusException
	 */
	public function action(string $actionID, array $params): array {
		if ($actionID == 'ra_login') {
			return $this->action_RaLogin($params);
		} else if ($actionID == 'ra_logout') {
			return $this->action_RaLogout($params);
		} else if ($actionID == 'admin_login') {
			return $this->action_AdminLogin($params);
		}  else if ($actionID == 'admin_logout') {
			return $this->action_AdminLogout($params);
		} else {
			return parent::action($actionID, $params);
		}
	}

	/**
	 * @param bool $html
	 * @return void
	 * @throws OIDplusException
	 */
	public function init(bool $html=true) {
		OIDplus::config()->prepareConfigKey('log_failed_ra_logins', 'Log failed RA logins', '0', OIDplusConfig::PROTECTION_EDITABLE, function($value) {
			if (!is_numeric($value) || (($value != 0) && (($value != 1)))) {
				throw new OIDplusException(_L('Valid values: 0 (off) or 1 (on).'));
			}
		});
		OIDplus::config()->prepareConfigKey('log_failed_admin_logins', 'Log failed Admin logins', '0', OIDplusConfig::PROTECTION_EDITABLE, function($value) {
			if (!is_numeric($value) || (($value != 0) && (($value != 1)))) {
				throw new OIDplusException(_L('Valid values: 0 (off) or 1 (on).'));
			}
		});
	}

	/**
	 * @param string $id
	 * @param array $out
	 * @param bool $handled
	 * @return void
	 * @throws OIDplusException
	 */
	public function gui(string $id, array &$out, bool &$handled) {
		$ary = explode('$', $id);
		$desired_ra = '';
		if (isset($ary[1])) {
			$id = $ary[0];
			$tab = $ary[1];
			if (isset($ary[2])) {
				$desired_ra = $ary[2];
			}
		} else {
			$tab = 'ra';
		}
		if ($id === 'oidplus:login') {
			$handled = true;
			$out['title'] = _L('Login');
			$out['icon']  = OIDplus::webpath(__DIR__,OIDplus::PATH_RELATIVE).'img/login_icon.png';

			$out['text']  = '<noscript>';
			$out['text'] .= '<p><font color="red">'._L('You need to enable JavaScript to use the login area.').'</font></p>';
			$out['text'] .= '</noscript>';

			$out['text'] .= '<div id="loginArea" style="visibility: hidden"><div id="loginTab" class="container" style="width:100%;">';

			$out['text'] .= OIDplus::getActiveCaptchaPlugin()->captchaGenerate(_L('Before logging in, please solve the following CAPTCHA'));
			$out['text'] .= '<br>';

			// ---------------- Tab control
			$out['text'] .= OIDplus::gui()->tabBarStart();
			$out['text'] .= OIDplus::gui()->tabBarElement('ra',    _L('Login as RA'),            $tab === 'ra');
			$out['text'] .= OIDplus::gui()->tabBarElement('admin', _L('Login as administrator'), $tab === 'admin');
			$out['text'] .= OIDplus::gui()->tabBarEnd();
			$out['text'] .= OIDplus::gui()->tabContentStart();
			// ---------------- "RA" tab
			$tabcont = '<h2>'._L('Login as RA').'</h2>';
			$login_list = OIDplus::authUtils()->loggedInRaList();
			if (count($login_list) > 0) {
				foreach ($login_list as $x) {
					$tabcont .= '<p>'._L('You are logged in as %1','<b>'.$x->raEmail().'</b>').' (<a href="#" onclick="return OIDplusPagePublicLogin.raLogout('.js_escape($x->raEmail()).');">'._L('Logout').'</a>)</p>';
				}
				$tabcont .= '<p>'._L('If you have more accounts, you can log in with another account here.').'</p>';
			} else {
				$tabcont .= '<p>'._L('Enter your email address and your password to log in as Registration Authority.').'</p>';
			}
			$tabcont .= '<form action="javascript:void(0);" onsubmit="return OIDplusPagePublicLogin.raLoginOnSubmit(this);">';
			$tabcont .= '<div><label class="padding_label">'._L('E-Mail').':</label><input type="text" name="email" value="'.htmlentities($desired_ra).'" id="raLoginEMail"></div>';
			$tabcont .= '<div><label class="padding_label">'._L('Password').':</label><input type="password" name="password" value="" id="raLoginPassword"></div>';
			$tabcont .= '<br><input type="submit" value="'._L('Login').'"><br><br>';
			$tabcont .= '</form>';
			$tabcont .= '<p><a '.OIDplus::gui()->link('oidplus:forgot_password').'>'._L('Forgot password?').'</a><br>';

			$invitePlugin = OIDplus::getPluginByOid('1.3.6.1.4.1.37476.2.5.2.4.2.92'); // OIDplusPageRaInvite
			if (!is_null($invitePlugin) && OIDplus::config()->getValue('ra_invitation_enabled')) {
				$tabcont .= '<p><b>'._L('How to register?').'</b> '._L('To receive login data, the superior RA needs to send you an invitation. After creating or updating your OID, the system will ask them if they want to send you an invitation. If they accept, you will receive an email with an activation link. Alternatively, the system admin can create your account manually in the administrator control panel.').'</p>';
			} else {
				$tabcont .= '<p><b>'._L('How to register?').'</b> '._L('Since invitations are disabled at this OIDplus system, the system administrator needs to create your account manually in the administrator control panel.').'</p>';
			}

			if ($tab === 'ra') {
				$alt_logins_html = array();
				foreach (OIDplus::getAllPlugins() as $plugin) {
					if ($plugin instanceof INTF_OID_1_3_6_1_4_1_37476_2_5_2_3_5) {
						$logins = $plugin->alternativeLoginMethods();
						foreach ($logins as $data) {
							if (isset($data[2]) && !empty($data[2])) {
								$img = '<img src="'.$data[2].'" alt="'.htmlentities($data[1]).'"> ';
							} else {
								$img = '';
							}
							$alt_logins_html[] = $img.'<a '.OIDplus::gui()->link($data[0]).'>'.htmlentities($data[1]).'</a>';
						}
					}
				}
				if (count($alt_logins_html) > 0) {
					$tabcont .= '<p>'._L('Alternative login methods').':<br>';
					foreach ($alt_logins_html as $alt_login) {
						$tabcont .= $alt_login.'<br>';
					}
					$tabcont .= '</p>';
				}
			}

			$out['text'] .= OIDplus::gui()->tabContentPage('ra', $tabcont, $tab === 'ra');
			// ---------------- "Administrator" tab
			$tabcont = '<h2>'._L('Login as administrator').'</h2>';
			if (OIDplus::authUtils()->isAdminLoggedIn()) {
				$tabcont .= '<p>'._L('You are logged in as administrator.').'</p>';
				$tabcont .= '<a href="#" onclick="return OIDplusPagePublicLogin.adminLogout();">'._L('Logout').'</a>';
			} else {
				$tabcont .= '<form action="javascript:void(0);" onsubmit="return OIDplusPagePublicLogin.adminLoginOnSubmit(this);">';
				$tabcont .= '<div><label class="padding_label">'._L('Password').':</label><input type="password" name="password" value="" id="adminLoginPassword"></div>';
				$tabcont .= '<br><input type="submit" value="'._L('Login').'"><br><br>';
				$tabcont .= '</form>';
				$tabcont .= '<p><a '.OIDplus::gui()->link('oidplus:forgot_password_admin').'>'._L('Forgot password?').'</a><br>';
			}
			$out['text'] .= OIDplus::gui()->tabContentPage('admin', $tabcont, $tab === 'admin');
			$out['text'] .= OIDplus::gui()->tabContentEnd();
			// ---------------- Tab control END

			$out['text'] .= '</div><br>';

			$out['text'] .= '<p><font size="-1">'._L('<i>Privacy information</i>: By using the login functionality, you are accepting that a "session cookie" is temporarily stored in your browser. The session cookie is a small text file that is sent to this website every time you visit it, to identify you as an already logged in user. It does not track any of your online activities outside OIDplus. The cookie will be destroyed when you log out.');
			$privacy_document_file = 'OIDplus/privacy_documentation.html';
			$resourcePlugin = OIDplus::getPluginByOid('1.3.6.1.4.1.37476.2.5.2.4.1.500'); // OIDplusPagePublicResources
			if (!is_null($resourcePlugin) && file_exists(OIDplus::localpath().'res/'.$privacy_document_file)) {
				$out['text'] .= ' <a '.OIDplus::gui()->link('oidplus:resources$'.$privacy_document_file.'#cookies').'>'._L('More information about the cookies used').'</a>';
			}
			$out['text'] .= '</font></p></div>';

			$out['text'] .= '<script>$("#loginArea")[0].style.visibility = "visible";</script>';
		}
	}

	/**
	 * @param array $out
	 * @return void
	 */
	public function publicSitemap(array &$out) {
		$out[] = 'oidplus:login';
	}

	/**
	 * @param array $json
	 * @param string|null $ra_email
	 * @param bool $nonjs
	 * @param string $req_goto
	 * @return bool
	 * @throws OIDplusConfigInitializationException
	 * @throws OIDplusException
	 */
	public function tree(array &$json, string $ra_email=null, bool $nonjs=false, string $req_goto=''): bool {
		$loginChildren = array();

		if (OIDplus::authUtils()->isAdminLoggedIn()) {
			$ra_roots = array();

			foreach (OIDplus::getPagePlugins() as $plugin) {
				if (is_subclass_of($plugin, OIDplusPagePluginAdmin::class)) {
					$plugin->tree($ra_roots);
				}
			}

			$ra_roots[] = array(
				'id'       => 'oidplus:logout$admin',
				'icon'     => OIDplus::webpath(__DIR__,OIDplus::PATH_RELATIVE).'img/logout_icon16.png',
				'conditionalselect' => 'OIDplusPagePublicLogin.adminLogout(); false;',
				'text'     => _L('Log out')
			);
			$loginChildren[] = array(
				'id'       => 'oidplus:dummy$'.md5((string)rand()),
				'text'     => _L("Logged in as <b>admin</b>"),
				'icon'     => OIDplus::webpath(__DIR__,OIDplus::PATH_RELATIVE).'img/admin_icon16.png',
				'conditionalselect' => 'false', // dummy node that can't be selected
				'state'    => array("opened" => true),
				'children' => $ra_roots
			);
		}

		foreach (OIDplus::authUtils()->loggedInRaList() as $ra) {
			$ra_email = $ra->raEmail();
			$ra_roots = array();

			foreach (OIDplus::getPagePlugins() as $plugin) {
				if (is_subclass_of($plugin, OIDplusPagePluginRa::class)) {
					$plugin->tree($ra_roots, $ra_email);
				}
			}

			$ra_roots[] = array(
				'id'       => 'oidplus:logout$'.$ra_email,
				'conditionalselect' => 'OIDplusPagePublicLogin.raLogout('.js_escape($ra_email).'); false;',
				'icon'     => OIDplus::webpath(__DIR__,OIDplus::PATH_RELATIVE).'img/logout_icon16.png',
				'text'     => _L('Log out')
			);
			foreach (OIDplusObject::getRaRoots($ra_email) as $loc_root) {
				$ico = $loc_root->getIcon();
				$ra_roots[] = array(
					'id' => 'oidplus:raroot$'.$loc_root->nodeId(),
					'text' => _L('Jump to RA root %1',$loc_root->objectTypeTitleShort().' '.$loc_root->crudShowId(OIDplusObject::parse($loc_root::root()))),
					'conditionalselect' => 'openOidInPanel('.js_escape($loc_root->nodeId()).', true); false;',
					'icon' => !is_null($ico) ? $ico : OIDplus::webpath(__DIR__,OIDplus::PATH_RELATIVE).'img/link_icon16.png'
				);
			}
			$ra_email_or_name = (new OIDplusRA($ra_email))->raName();
			if ($ra_email_or_name == '') {
				$ra_email_html = htmlentities($ra_email);
				$ra_email_or_name = '<b>'.$ra_email_html.'</b>';
			} else {
				$ra_email_html = htmlentities($ra_email);
				$ra_email_or_name_html = htmlentities($ra_email_or_name);
				$ra_email_or_name = "<b>$ra_email_or_name_html</b> ($ra_email_html)";
			}
			$loginChildren[] = array(
				'id'       => 'oidplus:dummy$'.md5((string)rand()),
				'text'     => _L('Logged in as %1',$ra_email_or_name),
				'icon'     => OIDplus::webpath(__DIR__,OIDplus::PATH_RELATIVE).'img/ra_icon16.png',
				'conditionalselect' => 'false', // dummy node that can't be selected
				'state'    => array("opened" => true),
				'children' => $ra_roots
			);
		}

		$json[] = array(
			'id'       => 'oidplus:login',
			'icon'     => OIDplus::webpath(__DIR__,OIDplus::PATH_RELATIVE).'img/login_icon16.png',
			'text'     => _L('Login'),
			'state'    => array("opened" => count($loginChildren)>0),
			'children' => $loginChildren
		);

		return true;
	}

	/**
	 * @param string $request
	 * @return array|false
	 */
	public function tree_search(string $request) {
		return false;
	}
}
