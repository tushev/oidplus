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

class OIDplusDatabasePluginODBC extends OIDplusDatabasePlugin {
	private $odbc;
	private $last_query;

	public static function getPluginInformation(): array {
		$out = array();
		$out['name'] = 'ODBC';
		$out['author'] = 'ViaThinkSoft';
		$out['version'] = null;
		$out['descriptionHTML'] = null;
		return $out;
	}

	public static function name(): string {
		return "ODBC";
	}

	public function query(string $sql, /*?array*/ $prepared_args=null): OIDplusQueryResult {
		$this->last_query = $sql;
		if (is_null($prepared_args)) {
			$res = @odbc_exec($this->odbc, $sql);

			if ($res === false) {
				throw new OIDplusSQLException($sql, $this->error());
			} else {
				return new OIDplusQueryResultODBC($res);
			}
		} else {
			// TEST: Emulate the prepared statement
			/*
			foreach ($prepared_args as $arg) {
				$needle = '?';
				$replace = "'$arg'"; // TODO: types
				$pos = strpos($sql, $needle);
				if ($pos !== false) {
					$sql = substr_replace($sql, $replace, $pos, strlen($needle));
				}
			}
			return OIDplusQueryResultODBC(@odbc_exec($this->odbc, $sql));
			*/
			if (!is_array($prepared_args)) {
				throw new OIDplusException("'prepared_args' must be either NULL or an ARRAY.");
			}
			
			foreach ($prepared_args as &$value) {
				// ODBC/SQLServer has problems converting "true" to the data type "bit"
				// Error "Invalid character value for cast specification"
				if (is_bool($value)) $value = $value ? '1' : '0';
			}
			
			$ps = @odbc_prepare($this->odbc, $sql);
			if (!$ps) {
				throw new OIDplusSQLException($sql, 'Cannot prepare statement');
			}

			if (!@odbc_execute($ps, $prepared_args)) {
				throw new OIDplusSQLException($sql, $this->error());
			}
			return new OIDplusQueryResultODBC($ps);
		}
	}

	public function insert_id(): int {
		switch ($this->slang()) {
			case 'mysql':
				$res = $this->query("SELECT LAST_INSERT_ID() AS ID");
				$row = $res->fetch_array();
				return (int)$row['ID'];
			case 'pgsql':
				$res = $this->query("SELECT LASTVAL() AS ID");
				$row = $res->fetch_array();
				return (int)$row['ID'];
			case 'mssql':
				$res = $this->query("SELECT SCOPE_IDENTITY() AS ID");
				$row = $res->fetch_array();
				return (int)$row['ID'];
			default:
				throw new OIDplusException("Cannot determine the last inserted ID for your DBMS. The DBMS is probably not supported.");
		}
	}

	public function error(): string {
		return odbc_errormsg($this->odbc);
	}

	protected function doConnect(): void {
		// Try connecting to the database
		$this->odbc = @odbc_connect(OIDPLUS_ODBC_DSN, OIDPLUS_ODBC_USERNAME, base64_decode(OIDPLUS_ODBC_PASSWORD));

		if (!$this->odbc) {
			$message = odbc_errormsg();
			throw new OIDplusConfigInitializationException('Connection to the database failed! '.$message);
		}

		try {
			$this->query("SET NAMES 'utf8'"); // Does most likely NOT work with ODBC. Try adding ";CHARSET=UTF8" (or similar) to the DSN
		} catch (Exception $e) {
		}
	}
	
	protected function doDisconnect(): void {
		@odbc_close($this->odbc);
		$this->odbc = null;
	}

	private $intransaction = false;

	public function transaction_begin(): void {
		if ($this->intransaction) throw new OIDplusException("Nested transactions are not supported by this database plugin.");
		odbc_autocommit($this->odbc, true);
		$this->intransaction = true;
	}

	public function transaction_commit(): void {
		odbc_commit($this->odbc);
		odbc_autocommit($this->odbc, false);
		$this->intransaction = false;
	}

	public function transaction_rollback(): void {
		odbc_rollback($this->odbc);
		odbc_autocommit($this->odbc, false);
		$this->intransaction = false;
	}
}

class OIDplusQueryResultODBC extends OIDplusQueryResult {
	protected $no_resultset;
	protected $res;

	public function __construct($res) {
		$this->no_resultset = is_bool($res);
		
		if (!$this->no_resultset) {
			$this->res = $res;
		}
	}
	
	public function __destruct() {
		// odbc_close_cursor($this->res);
	}

	public function containsResultSet(): bool {
		return !$this->no_resultset;
	}

	public function num_rows(): int {
		if ($this->no_resultset) throw new OIDplusException("The query has returned no result set (i.e. it was not a SELECT query)");
		return odbc_num_rows($this->res);
	}

	public function fetch_array()/*: ?array*/ {
		if ($this->no_resultset) throw new OIDplusException("The query has returned no result set (i.e. it was not a SELECT query)");
		$ret = odbc_fetch_array($this->res);
		if ($ret === false) $ret = null;
		if (!is_null($ret)) {
			// ODBC gives bit(1) as binary, MySQL as integer and PDO as string.
			// We'll do it like MySQL does, even if ODBC is actually more correct.
			foreach ($ret as &$value) {
				if ($value === chr(0)) $value = 0;
				if ($value === chr(1)) $value = 1;
			}
		}
		return $ret;
	}

	public function fetch_object()/*: ?object*/ {
		if ($this->no_resultset) throw new OIDplusException("The query has returned no result set (i.e. it was not a SELECT query)");
		$ret = odbc_fetch_object($this->res);
		if ($ret === false) $ret = null;
		if (!is_null($ret)) {
			// ODBC gives bit(1) as binary, MySQL as integer and PDO as string.
			// We'll do it like MySQL does, even if ODBC is actually more correct.
			foreach ($ret as &$value) {
				if ($value === chr(0)) $value = 0;
				if ($value === chr(1)) $value = 1;
			}
		}
		return $ret;
	}
}
