<?php

/*
 * PHP Utilities - Misc functions
 * Copyright 2019 - 2023 Daniel Marschall, ViaThinkSoft
 * Revision: 2023-02-27
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

// array(89,51,10,10) => 'Y3\012\012'
function c_literal($byte_array) {
	$out = "'";
	foreach ($byte_array as $c) {
		if (is_string($c)) $c = ord($c);
		if ((($c >= 0x00) && ($c <= 0x1F)) || ($c >= 0x7F)) {
			// For non-printable characters use octal notation:
			// \000 ... \377
			$out .= "\\".str_pad(base_convert(''.$c,10,8), 3, '0', STR_PAD_LEFT);
		} else {
			if (chr($c) == "'") $out .= '\\';
			$out .= chr($c);
		}
	}
	$out .= "'";
	return $out;
}

function c_literal_hexstr($hexstr) {
	$odd_char = (strlen($hexstr)%2 != 0) ? '0x'.substr($hexstr,-1).'<<4' : '';
	$hexstr = substr($hexstr,0,2*(int)floor(strlen($hexstr)/2));
	if ($hexstr != '') {
		$ary = str_split(hex2bin($hexstr));
		foreach ($ary as &$a) $a = ord($a);
		return rtrim(c_literal($ary).' '.$odd_char);
	} else {
		return $odd_char;
	}
}

function generateRandomString($length) {
	// Note: This function can be used in temporary file names, so you
	// may not generate illegal file name characters.
	$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
	$charactersLength = strlen($characters);
	$randomString = '';
	for ($i = 0; $i < $length; $i++) {
		$randomString .= $characters[rand(0, $charactersLength - 1)];
	}
	return $randomString;
}

function trim_br($html) {
	$count = 0;
	do { $html = preg_replace('@^\s*<\s*br\s*/{0,1}\s*>@isU', '', $html, -1, $count); } while ($count > 0); // left trim
	do { $html = preg_replace('@<\s*br\s*/{0,1}\s*>\s*$@isU', '', $html, -1, $count); } while ($count > 0); // right trim
	return $html;
}

function insertWhitespace($str, $index) {
	return substr($str, 0, $index) . ' ' . substr($str, $index);
}

function js_escape($data) {
	// TODO.... json_encode??
	$data = str_replace('\\', '\\\\', $data);
	$data = str_replace('\'', '\\\'', $data);
	return "'" . $data . "'";
}

function get_calling_function() {
	$ex = new Exception();
	$trace = $ex->getTrace();
	if (!isset($trace[2])) return '(main)';
	$final_call = $trace[2];
	return $final_call['file'].':'.$final_call['line'].'/'.$final_call['function'].'()';
}

function convert_to_utf8_no_bom($cont) {
	$cont = vts_utf8_encode($cont);

	// Remove BOM
	$bom = pack('H*','EFBBBF');
	$cont = preg_replace("/^$bom/", '', $cont);
	return $cont;
}

function vts_utf8_encode($text) {
	$enc = mb_detect_encoding($text, null, true);
	if ($enc === false) $enc = mb_detect_encoding($text, ['ASCII', 'UTF-8', 'Windows-1252', 'ISO-8859-1'], true);
	if ($enc === false) $enc = null;

	if ($enc === 'UTF-8') return $text;

	$res = mb_convert_encoding($text, 'UTF-8', $enc);
	if ($res === false) $res = iconv('UTF-8', 'UTF-8//IGNORE', $text);

	return $res;
}

function vts_utf8_decode($text) {
	$enc = mb_detect_encoding($text, null, true);
	if ($enc === false) $enc = mb_detect_encoding($text, ['ASCII', 'UTF-8', 'Windows-1252', 'ISO-8859-1'], true);
	if ($enc === false) $enc = null;

	if ($enc !== 'UTF-8') return $text;

	$res = mb_convert_encoding($text, 'Windows-1252', $enc);
	if ($res === false) $res = iconv('Windows-1252', 'Windows-1252//IGNORE', $text);

	return $res;
}

function stripHtmlComments($html) {
	// https://stackoverflow.com/questions/11337332/how-to-remove-html-comments-in-php
	$html = preg_replace("~<!--(?!<!)[^\[>].*?-->~s", "", $html);
	return $html;
}

function wildcard_is_dir($dir) {
	// Example usage:  if (!wildcard_is_dir(OIDplus::localpath().'plugins/'.'*'.'/design/'.$value)) throw new Exception("Design does not exist")
	$dirs = @glob($dir);
	if ($dirs) foreach ($dirs as $dir) {
		if (is_dir($dir)) return true;
	}
	return false;
}

function isInternetExplorer() {
	// see also includes/oidplus_base.js
	$ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
	return ((strpos($ua,'MSIE ') !== false) || (strpos($ua,'Trident/') !== false));
}

if (!function_exists('str_ends_with')) {
	// PHP 7.x compatibility
	function str_ends_with($haystack, $needle) {
		$length = strlen($needle);
		return $length > 0 ? substr($haystack, -$length) === $needle : true;
	}
}

if (!function_exists('str_starts_with')) {
	// PHP 7.x compatibility
	function str_starts_with($haystack, $needle) {
		return strpos($haystack, $needle) === 0;
	}
}

function random_bytes_ex($len, $raw=true, $force_cryptographically_secure=true) {
	if ($len === 0) return '';
	assert($len > 0);

	if (function_exists('random_bytes')) {
		try {
			$a = random_bytes($len);
		} catch (Exception $e) { $a = null; }
		if ($a) return $raw ? $a : bin2hex($a);
	}

	if (function_exists('openssl_random_pseudo_bytes')) {
		try {
			$a = openssl_random_pseudo_bytes($len);
		} catch (Exception $e) { $a = null; }
		if ($a) return $raw ? $a : bin2hex($a);
	}

	if (function_exists('mcrypt_create_iv') && defined('MCRYPT_DEV_RANDOM')) {
		try {
			$a = bin2hex(mcrypt_create_iv($len, MCRYPT_DEV_RANDOM));
		} catch (Exception $e) { $a = null; }
		if ($a) return $raw ? $a : bin2hex($a);
	}

	if ($force_cryptographically_secure) {
		$msg = 'Cannot find a fitting Cryptographically Secure Random Number Generator (CSRNG).';
		if (version_compare(PHP_VERSION, '8.2.0') >= 0) {
			throw new \Random\RandomException($msg);
		} else {
			throw new \Exception($msg);
		}
	}

	if (function_exists('mcrypt_create_iv') && defined('MCRYPT_DEV_URANDOM')) {
		// /dev/urandom uses the same entropy pool than /dev/random, but if there is not enough data
		// then the security is lowered.
		try {
			$a = bin2hex(mcrypt_create_iv($len, MCRYPT_DEV_URANDOM));
		} catch (Exception $e) { $a = null; }
		if ($a) return $raw ? $a : bin2hex($a);
	}

	if (function_exists('mcrypt_create_iv') && defined('MCRYPT_RAND')) {
		try {
			$a = bin2hex(mcrypt_create_iv($len, MCRYPT_RAND));
		} catch (Exception $e) { $a = null; }
		if ($a) return $raw ? $a : bin2hex($a);
	}

	// Fallback to non-secure RNG
	$a = '';
	while (strlen($a) < $len*2) {
		$a .= sha1(uniqid((string)mt_rand(), true));
	}
	$a = substr($a, 0, $len*2);
	return $raw ? hex2bin($a) : $a;
}
