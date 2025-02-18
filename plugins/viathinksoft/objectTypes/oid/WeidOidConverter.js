(function (globalObject) {
  'use strict';

/**
* WEID<=>OID Converter
* (c) Webfan.de, ViaThinkSoft
* Revision 2022-03-26
**/

// What is a WEID?
//     A WEID (WEhowski IDentifier) is an alternative representation of an
//     OID (Object IDentifier) defined by Till Wehowski.
//     In OIDs, arcs are in decimal base 10. In WEIDs, the arcs are in base 36.
//     Also, each WEID has a check digit at the end (called WeLohn Check Digit).
//
// Changes in the December 2021 definition by Daniel Marschall:
//     - There are several classes of WEIDs which have different OID bases:
//           "Class C" WEID:  weid:EXAMPLE-3      (base .1.3.6.1.4.1.37553.8.)
//                            oid:1.3.6.1.4.1.37553.8.32488192274
//           "Class B" WEID:  weid:pen:SX0-7PR-6  (base .1.3.6.1.4.1.)
//                            oid:1.3.6.1.4.1.37476.9999
//           "Class A" WEID:  weid:root:2-RR-2    (base .)
//                            oid:2.999
//     - The namespace (weid:, weid:pen:, weid:root:) is now case insensitive.
//     - Padding with '0' characters is valid (e.g. weid:000EXAMPLE-3)
//       The paddings do not count into the WeLuhn check-digit.

var WeidOidConverter = {

	weLuhnCheckDigit: function(str) {
		// Padding zeros don't count to the check digit (December 2021)
		var ary = str.split('-');
		ary.forEach(function (o,i,a) {
			if (a[i].match(/^0+$/)) {
				a[i] = '0';
			} else {
				a[i] = a[i].replace(/^0+/, '');
			}
		});
		str = ary.join('-');

		// remove separators from the WEID string
		var wrkstr = str.replaceAll('-', '');

		// Replace 'a' with '10', 'b' with '1', etc.
		for (var i=0; i<26; i++) {
			wrkstr = wrkstr.toLowerCase().replaceAll(String.fromCharCode('a'.charCodeAt(0)+i).toLowerCase(), (10+i));
		}

		// At the end, wrkstr should only contain digits! Verify it!
		if (!wrkstr.match(/^\d*$/)) {
			console.error("weLuhnCheckDigit: Invalid input");
			return false;
		}

		// Now do the standard Luhn algorithm
		var nbdigits = wrkstr.length;
		var parity = nbdigits & 1; // mod 2
		var sum = 0;
		for (var n=nbdigits-1; n>=0; n--) {
			var digit = parseInt(wrkstr.substr(n,1));
			if ((n & 1) != parity) digit *= 2;
			if (digit > 9) digit -= 9;
			sum += digit;
		}
		return (sum%10) == 0 ? 0 : 10-(sum%10);
	},

	oidSanitize: function(oid) {
		var oid = oid.trim();

		if (oid.substr(0,1) == '.') oid = oid.substr(1); // remove leading dot

		if (oid != '') {
			var elements = oid.split('.');

			var fail = false;
			elements.forEach(function (o,i,a) {
				if (a[i].trim() == '') fail = true;

				if (!a[i].match(/^\d+$/)) fail = true;

				if (a[i].match(/^0+$/)) {
					a[i] = '0';
				} else {
					a[i] = a[i].replace(/^0+/, '');
				}
			});
			if (fail) return false;

			oid = elements.join(".");

			if ((elements.length > 0) && (elements[0] != '0') && (elements[0] != '1') && (elements[0] != '2')) return false;
			if ((elements.length > 1) && ((elements[0] == '0') || (elements[0] == '1')) && ((elements[1].length > 2) || (elements[1] > 39))) return false;
		}

		return oid;
	},

	// Translates a WEID to an OID
	// "weid:EXAMPLE-3" becomes "1.3.6.1.4.1.37553.8.32488192274"
	// If it failed (e.g. wrong namespace, wrong checksum, etc.) then false is returned.
	// If the weid ends with '?', the checksum will be added
	// Return value is an array with the elements "oid" and "weid".
	// Example:
	//     weid2oid("weid:EXAMPLE-?").weid == "weid:EXAMPLE-3"
	//     weid2oid("weid:EXAMPLE-?").oid  == "1.3.6.1.4.1.37553.8.32488192274"
	weid2oid: function(weid) {
		var weid = weid.trim();

		var p = weid.lastIndexOf(':');
		var namespace = weid.substr(0, p+1);
		var rest = weid.substr(p+1);

		var base = null;
		namespace = namespace.toLowerCase(); // namespace is case insensitive
		if (namespace == 'weid:') {
			// Class C
			base = '1-3-6-1-4-1-SZ5-8';
		} else if (namespace == 'weid:pen:') {
			// Class B
			base = '1-3-6-1-4-1';
		} else if (namespace == 'weid:root:') {
			// Class A
			base = '';
		} else {
			// Wrong namespace
			console.error("weid2oid: Wrong input");
			return false;
		}

		weid = rest;

		var elements = ((base != '') ? base.split('-') : []).concat(weid.split('-'));

		var fail = false;
		elements.forEach(function (o,i,a) {
			if (a[i].trim() == '') fail = true;
		});
		if (fail) return false;

		var actual_checksum = elements.pop();
		var expected_checksum = WeidOidConverter.weLuhnCheckDigit(elements.join('-'));
		if (actual_checksum != '?') {
			if (actual_checksum != expected_checksum) {
				console.error("weid2oid: Wrong checksum");
				return false; // wrong checksum
			}
		} else {
			// If checksum is '?', it will be replaced by the actual checksum,
			// e.g. weid:EXAMPLE-? becomes weid:EXAMPLE-3
			weid = weid.replace('?', expected_checksum);
		}
		elements.forEach(function (o,i,a) {
			a[i] = WeidOidConverter.base_convert_bigint(a[i], 36, 10);
		});
		var oid = elements.join('.');

		weid = namespace.toLowerCase() + weid.toUpperCase(); // add namespace again

		oid = WeidOidConverter.oidSanitize(oid);
		if (oid === false) return false; // invalid OID

		return { "weid": weid, "oid" : oid };
	},

	// Converts an OID to WEID
	// "1.3.6.1.4.1.37553.8.32488192274" becomes "weid:EXAMPLE-3"
	oid2weid: function(oid) {
		var oid = WeidOidConverter.oidSanitize(oid);
		if (oid === false) return false;

		var weidstr = null;
		if (oid != '') {
			var elements = oid.split('.');
			elements.forEach(function (o,i,a) {
				a[i] = WeidOidConverter.base_convert_bigint(a[i], 10, 36);
			});
			weidstr = elements.join("-");
		} else {
			weidstr = '';
		}

		var is_class_c = (weidstr.startsWith('1-3-6-1-4-1-SZ5-8-') || (weidstr == '1-3-6-1-4-1-SZ5-8'));
		var is_class_b = (weidstr.startsWith('1-3-6-1-4-1-') || (weidstr == '1-3-6-1-4-1'));
		var is_class_a = !is_class_b && !is_class_c;

		var checksum = WeidOidConverter.weLuhnCheckDigit(weidstr);

		var namespace = null;
		if (is_class_c) {
			weidstr = weidstr.substr('1-3-6-1-4-1-SZ5-8-'.length);
			namespace = 'weid:';
		} else if (is_class_b) {
			weidstr = weidstr.substr('1-3-6-1-4-1-'.length);
			namespace = 'weid:pen:';
		} else if (is_class_a) {
			// weidstr stays
			namespace = 'weid:root:';
		} else {
			// should not happen
			console.error("oid2weid: Cannot detect namespace");
			return false;
		}

		var weid = namespace + (weidstr == '' ? checksum : weidstr + '-' + checksum);

		return { "weid": weid, "oid": oid };
	},

	base_convert_bigint: function(numstring, frombase, tobase) {

		// This variant would require the "mikemcl/bignumber.js" library:
		//var x = BigNumber(numstr, frombase);
		//return isNaN(x) ? false : x.toString(tobase).toUpperCase();

		var frombase_str = '';
		for (var i=0; i<frombase; i++) {
			frombase_str += parseInt(i, 10).toString(36).toUpperCase();
		}

		var tobase_str = '';
		for (var i=0; i<tobase; i++) {
			tobase_str += parseInt(i, 10).toString(36).toUpperCase();
		}

		for (var i=0; i<numstring.length; i++) {
			if (frombase_str.toLowerCase().indexOf(numstring.substr(i,1).toLowerCase()) < 0) {
				console.error("base_convert_bigint: Invalid input");
				return false;
			}
		}

		var length = numstring.length;
		var result = '';
		var number = [];
		for (var i=0; i<length; i++) {
			number[i] = frombase_str.toLowerCase().indexOf(numstring[i].toLowerCase());
		}
		var newlen = null;
		do { // Loop until whole number is converted
			var divide = 0;
			var newlen = 0;
			for (var i=0; i<length; i++) { // Perform division manually (which is why this works with big numbers)
				divide = divide * frombase + parseInt(number[i]);
				if (divide >= tobase) {
					number[newlen++] = (divide / tobase);
					divide = divide % tobase;
				} else if (newlen > 0) {
					number[newlen++] = 0;
				}
			}
			length = newlen;
			result = tobase_str.substr(divide,1) + result; // Divide is basically numstring % tobase (i.e. the new character)
		}
		while (newlen != 0);

		return result;
	}
};

WeidOidConverter['default'] = WeidOidConverter.WeidOidConverter = WeidOidConverter;

if (typeof define == 'function' && define.amd) {
	define('WeidOidConverter', function () {
		return WeidOidConverter;
	});
} else if (typeof module != 'undefined' && module.exports) {
	module.exports = WeidOidConverter;
} else {
	if (!globalObject) {
		globalObject = typeof self != 'undefined' && self ? self : window;
	}
	globalObject.WeidOidConverter = WeidOidConverter;
}
})(this);
