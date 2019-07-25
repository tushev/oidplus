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

class OIDplusPagePublicLogin extends OIDplusPagePlugin {
	public function type() {
		return 'public';
	}

	public function priority() {
		return 90;
	}

	public function action(&$handled) {
		// === RA LOGIN/LOGOUT ===

		if (isset($_POST["action"]) && ($_POST["action"] == "ra_login")) {
			$handled = true;

			$email = $_POST['email'];
			$ra = new OIDplusRA($email);

			if (RECAPTCHA_ENABLED) {
				$secret=RECAPTCHA_PRIVATE;
				$response=$_POST["captcha"];
				$verify=file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret={$secret}&response={$response}");
				$captcha_success=json_decode($verify);
				if ($captcha_success->success==false) {
					die(json_encode(array("error" => 'Captcha wrong')));
				}
			}

			if ($ra->checkPassword($_POST['password'])) {
				OIDplus::logger()->log("RA($email)!", "RA '$email' logged in");
				OIDplus::authUtils()::raLogin($email);

				if (!OIDplus::db()->query("UPDATE ".OIDPLUS_TABLENAME_PREFIX."ra set last_login = now() where email = '".OIDplus::db()->real_escape_string($email)."'")) {
					die(json_encode(array("error" => OIDplus::db()->error())));
				}

				echo json_encode(array("status" => 0));
			} else {
				die(json_encode(array("error" => "Wrong password")));
			}
		}
		if (isset($_POST["action"]) && ($_POST["action"] == "ra_logout")) {
			$handled = true;
			
			$email = $_POST['email'];

			OIDplus::logger()->log("RA($email)!", "RA '$email' logged out");
			OIDplus::authUtils()::raLogout($email);
			echo json_encode(array("status" => 0));
		}

		// === ADMIN LOGIN/LOGOUT ===

		if (isset($_POST["action"]) && ($_POST["action"] == "admin_login")) {
			$handled = true;

			if (RECAPTCHA_ENABLED) {
				$secret=RECAPTCHA_PRIVATE;
				$response=$_POST["captcha"];
				$verify=file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret={$secret}&response={$response}");
				$captcha_success=json_decode($verify);
				if ($captcha_success->success==false) {
					die(json_encode(array("error" => 'Captcha wrong')));
				}
			}

			if (OIDplus::authUtils()::adminCheckPassword($_POST['password'])) {
				OIDplus::logger()->log("A!", "Admin logged in");
				OIDplus::authUtils()::adminLogin();
				echo json_encode(array("status" => 0));
			} else {
				die(json_encode(array("error" => "Wrong password")));
			}
		}
		if (isset($_POST["action"]) && ($_POST["action"] == "admin_logout")) {
			$handled = true;
			OIDplus::logger()->log("A!", "Admin logged out");
			OIDplus::authUtils()::adminLogout();
			echo json_encode(array("status" => 0));
		}
	}

	public function init($html=true) {
	}

	public function cfgSetValue($name, $value) {
	}

	public function gui($id, &$out, &$handled) {
		if ($id === 'oidplus:login') {
			$handled = true;
			$out['title'] = 'Login';
			$out['icon']  = 'plugins/'.basename(dirname(__DIR__)).'/'.basename(__DIR__).'/login_big.png';

			$out['text'] .= '<noscript>';
			$out['text'] .= '<p>You need to enable JavaScript to use the login area.</p>';
			$out['text'] .= '</noscript>';

			$out['text'] .= (RECAPTCHA_ENABLED ? '<script> grecaptcha.render(document.getElementById("g-recaptcha"), { "sitekey" : "'.RECAPTCHA_PUBLIC.'" }); </script>'.
			                                     '<p>Before logging in, please solve the following CAPTCHA</p><div id="g-recaptcha" class="g-recaptcha" data-sitekey="'.RECAPTCHA_PUBLIC.'"></div>' : '');


			$out['text'] .= '<br>';

			$out['text'] .= '<div id="loginArea" style="visibility: hidden"><div id="loginTab" class="container" style="width:100%;">';
			$out['text'] .= '<ul  class="nav nav-pills">';
			$out['text'] .= '			<li class="active">';
			$out['text'] .= '			<a href="#1a" data-toggle="tab">Login as RA</a>';
			$out['text'] .= '			</li>';
			$out['text'] .= '			<li><a href="#2a" data-toggle="tab">Login as administrator</a>';
			$out['text'] .= '			</li>';
			$out['text'] .= '		</ul>';
			$out['text'] .= '			<div class="tab-content clearfix">';
			$out['text'] .= '			  <div class="tab-pane active" id="1a">';

			$out['text'] .= '<h2>Login as RA</h2>';

			$login_list = OIDplus::authUtils()->loggedInRaList();
			if (count($login_list) > 0) {
				foreach ($login_list as $x) {
					$out['text'] .= '<p>You are logged in as <b>'.$x->raEmail().'</b> (<a href="#" onclick="return raLogout('.js_escape($x->raEmail()).');">Logout</a>)</p>';
				}
				$out['text'] .= '<p>If you have more accounts, you can log in with a new account:</p>';
			} else {
				$out['text'] .= '<p>Enter your email address and your password to log in as Registration Authority.</p>';
			}
			$out['text'] .= '<form onsubmit="return raLoginOnSubmit(this);">';
			$out['text'] .= '<input type="hidden" name="action" value="ra_login">';
			$out['text'] .= '<label class="padding_label">E-Mail:</label><input type="text" name="email" value="" id="raLoginEMail"><br>';
			$out['text'] .= '<label class="padding_label">Password:</label><input type="password" name="password" value="" id="raLoginPassword"><br><br>';
			$out['text'] .= '<input type="submit" value="Login"><br><br>';
			$out['text'] .= '</form>';
			$out['text'] .= '<p><a '.oidplus_link('oidplus:forgot_password').'>Forgot password?</a><br>';
			$out['text'] .= '<abbr title="To receive login data, the superior RA needs to send you an invitation. After creating or updating your OID, the system will ask them if they want to send you an invitation. If they accept, you will receive an email with an activation link.">How to register?</abbr></p>';

			$out['text'] .= '				</div>';
			$out['text'] .= '				<div class="tab-pane" id="2a">';

			if (OIDplus::authUtils()::isAdminLoggedIn()) {
				$out['text'] .= '<h2>Admin login</h2>';
				$out['text'] .= '<p>You are logged in as administrator.</p>';
				$out['text'] .= '<a href="#" onclick="return adminLogout();">Logout</a>';
			} else {
				$out['text'] .= '<h2>Login as administrator</h2>';
				$out['text'] .= '<form onsubmit="return adminLoginOnSubmit(this);">';
				$out['text'] .= '<input type="hidden" name="action" value="admin_login">';
				$out['text'] .= '<label class="padding_label">Password:</label><input type="password" name="password" value="" id="adminLoginPassword"><br><br>';
				$out['text'] .= '<input type="submit" value="Login"><br><br>';
				$out['text'] .= '</form>';
				$out['text'] .= '<p><abbr title="Delete the file includes/config.inc.php and reload the page to start Setup again">Forgot password?</abbr></p>';
			}

			$out['text'] .= '				</div>';
			$out['text'] .= '			</div>';
			$out['text'] .= '  </div><br>';
			$out['text'] .= '<p><font size="-1"><i>Privacy information</i>: By using the login functionality, you are accepting that a "session cookie" is temporarily stored in your browser. '.
			                'The session cookie is a small text file that is sent to this website every time you visit it, to identify you as an already logged in user. '.
			                'It does not track any of your online activities outside OIDplus. The cookie will be destroyed when you log out or after an inactivity of '.ceil(SESSION_LIFETIME/60).' minutes.</font></p></div>';

			$out['text'] .= '<script>document.getElementById("loginArea").style.visibility = "visible";</script>';
		}
	}

	public function tree(&$json, $ra_email=null, $nonjs=false, $req_goto='') {
		$loginChildren = array();

		if (OIDplus::authUtils()::isAdminLoggedIn()) {
			$ra_roots = array();

			foreach (OIDplus::getPagePlugins('admin') as $plugin) {
				$plugin->tree($ra_roots);
			}

			$ra_roots[] = array(
				'id'       => 'oidplus:logout$admin',
				'icon'     => 'plugins/'.basename(dirname(__DIR__)).'/'.basename(__DIR__).'/treeicon_logout.png',
				'conditionalselect' => 'adminLogout()', // defined in oidplus.js
				'text'     => 'Log out'
			);
			$loginChildren[] = array(
				'id'       => 'oidplus:dummy$'.md5(rand()),
				'text'     => "Logged in as admin",
				'icon'     => 'plugins/'.basename(dirname(__DIR__)).'/'.basename(__DIR__).'/treeicon_admin.png',
				'conditionalselect' => 'false', // dummy node that can't be selected
				'state'    => array("opened" => true),
				'children' => $ra_roots
			);
		}

		foreach (OIDplus::authUtils()::loggedInRaList() as $ra) {
			$ra_email = $ra->raEmail();
			$ra_roots = array();

			foreach (OIDplus::getPagePlugins('ra') as $plugin) {
				$plugin->tree($ra_roots, $ra_email);
			}

			$ra_roots[] = array(
				'id'       => 'oidplus:logout$'.$ra_email,
				'conditionalselect' => 'raLogout('.js_escape($ra_email).')', // defined in oidplus.js
				'icon'     => 'plugins/'.basename(dirname(__DIR__)).'/'.basename(__DIR__).'/treeicon_logout.png',
				'text'     => 'Log out'
			);
			foreach (OIDplusObject::getRaRoots($ra_email) as $loc_root) {
				$ico = $loc_root->getIcon();
				$ra_roots[] = array(
					'id' => 'oidplus:raroot$'.$loc_root->nodeId(),
					'text' => 'Jump to RA root '.$loc_root->objectTypeTitleShort().' '.$loc_root->crudShowId(OIDplusObject::parse($loc_root::root())),
					'conditionalselect' => 'openOidInPanel('.js_escape($loc_root->nodeId()).', true);',
					'icon' => !is_null($ico) ? $ico : 'plugins/'.basename(dirname(__DIR__)).'/'.basename(__DIR__).'/treeicon_link.png'
				);
			}
			$ra_email_or_name = (new OIDplusRA($ra_email))->raName();
			if ($ra_email_or_name == '') $ra_email_or_name = $ra_email;
			$loginChildren[] = array(
				'id'       => 'oidplus:dummy$'.md5(rand()),
				'text'     => "Logged in as ".htmlentities($ra_email_or_name),
				'icon'     => 'plugins/'.basename(dirname(__DIR__)).'/'.basename(__DIR__).'/treeicon_ra.png',
				'conditionalselect' => 'false', // dummy node that can't be selected
				'state'    => array("opened" => true),
				'children' => $ra_roots
			);
		}

		$json[] = array(
			'id'       => 'oidplus:login',
			'icon'     => 'plugins/'.basename(dirname(__DIR__)).'/'.basename(__DIR__).'/treeicon_login.png',
			'text'     => 'Login',
			'state'    => array("opened" => count($loginChildren)>0),
			'children' => $loginChildren
		);

		return true;
	}

	public function tree_search($request) {
		return false;
	}
}

OIDplus::registerPagePlugin(new OIDplusPagePublicLogin());
