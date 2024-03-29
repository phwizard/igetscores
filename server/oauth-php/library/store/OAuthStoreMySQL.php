<?php

/**
 * Storage container for the oauth credentials, both server and consumer side.
 * Based on MySQL
 * 
 * @version $Id: OAuthStoreMySQL.php 49 2008-10-01 09:43:19Z marcw@pobox.com $
 * @author Marc Worrell <marcw@pobox.com>
 * @date  Nov 16, 2007 4:03:30 PM
 * 
 * 
 * The MIT License
 * 
 * Copyright (c) 2007-2008 Mediamatic Lab
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */


require_once dirname(__FILE__) . '/OAuthStoreAbstract.class.php';

class OAuthStoreMySQL extends OAuthStoreAbstract
{
	/**
	 * The MySQL connection 
	 */
	protected $conn;

	/**
	 * Maximum delta a timestamp may be off from a previous timestamp.
	 * Allows multiple consumers with some clock skew to work with the same token.
	 * Unit is seconds, default max skew is 10 minutes.
	 */
	protected $max_timestamp_skew = 600;

	/**
	 * Construct the OAuthStoreMySQL.
	 * In the options you have to supply either:
	 * - server, username, password and database (for a mysql_connect)
	 * - conn (for the connection to be used)
	 * 
	 * @param array options
	 */
	function __construct ( $options = array() )
	{
		if (isset($options['conn']))
		{
			$this->conn = $options['conn'];
		}
		else
		{
			if (isset($options['server']))
			{
				$server   = $options['server'];
				$username = $options['username'];
				
				if (isset($options['password']))
				{
					$this->conn = mysql_connect($server, $username, $options['password']);
				}
				else
				{
					$this->conn = mysql_connect($server, $username);
				}
			}
			else
			{
				// Try the default mysql connect
				$this->conn = mysql_connect();
			}

			if (isset($options['database']))
			{
				if (!mysql_select_db($options['database'], $this->conn))
				{
					$this->sql_errcheck();
				}
			}
			$this->query('set character set utf8');
		}
	}


	/**
	 * Find stored credentials for the consumer key and token. Used by an OAuth server
	 * when verifying an OAuth request.
	 * 
	 * TODO: also check the status of the consumer key
	 * 
	 * @param string consumer_key
	 * @param string token
	 * @param string token_type		false, 'request' or 'access'
	 * @exception OAuthException when no secrets where found
	 * @return array	assoc (consumer_secret, token_secret, osr_id, ost_id, user_id)
	 */
	public function getSecretsForVerify ( $consumer_key, $token, $token_type = 'access' )
	{
		if ($token_type === false)
		{
			$rs = $this->query_row_assoc('
						SELECT	osr_id, 
								osr_consumer_key		as consumer_key,
								osr_consumer_secret		as consumer_secret
						FROM oauth_server_registry
						WHERE osr_consumer_key	= \'%s\'
						  AND osr_enabled		= 1
						', 
						$consumer_key);
			
			if ($rs)
			{
				$rs['token'] 		= false;
				$rs['token_secret']	= false;
				$rs['user_id']		= false;
				$rs['ost_id']		= false;
			}
		}
		else
		{
			$rs = $this->query_row_assoc('
						SELECT	osr_id, 
								ost_id,
								ost_usa_id_ref			as user_id,
								osr_consumer_key		as consumer_key,
								osr_consumer_secret		as consumer_secret,
								ost_token				as token,
								ost_token_secret		as token_secret
						FROM oauth_server_registry
								JOIN oauth_server_token
								ON ost_osr_id_ref = osr_id
						WHERE ost_token_type	= \'%s\'
						  AND osr_consumer_key	= \'%s\'
						  AND ost_token			= \'%s\'
					 	  AND osr_enabled		= 1
						', 
						$token_type, $consumer_key, $token);
		}
		
		if (empty($rs))
		{
			throw new OAuthException('The consumer_key "'.$consumer_key.'" token "'.$token.'" combination does not exist or is not enabled.');
		}
		return $rs;
	}


	/**
	 * Find the server details for signing a request, always looks for an access token.
	 * The returned credentials depend on which local user is making the request.
	 * 
	 * The consumer_key must belong to the user or be public (user id is null)
	 * 
	 * For signing we need all of the following:
	 * 
	 * consumer_key			consumer key associated with the server
	 * consumer_secret		consumer secret associated with this server
	 * token				access token associated with this server
	 * token_secret			secret for the access token
	 * signature_methods	signing methods supported by the server (array)
	 * 
	 * @todo filter on token type (we should know how and with what to sign this request, and there might be old access tokens)
	 * @param string uri	uri of the server
	 * @param int user_id	id of the logged on user
	 * @exception OAuthException when no credentials found
	 * @return array
	 */
	public function getSecretsForSignature ( $uri, $user_id )
	{
		// Find a consumer key and token for the given uri
		$ps		= parse_url($uri);
		$host	= isset($ps['host']) ? $ps['host'] : 'localhost';
		$path	= isset($ps['path']) ? $ps['path'] : '';
		
		if (empty($path) || substr($path, -1) != '/')
		{
			$path .= '/';
		}

		// The owner of the consumer_key is either the user or nobody (public consumer key)
		$secrets = $this->query_row_assoc('
					SELECT	ocr_consumer_key		as consumer_key,
							ocr_consumer_secret		as consumer_secret,
							oct_token				as token,
							oct_token_secret		as token_secret,
							ocr_signature_methods	as signature_methods
					FROM oauth_consumer_registry
						JOIN oauth_consumer_token ON oct_ocr_id_ref = ocr_id
					WHERE ocr_server_uri_host = \'%s\'
					  AND ocr_server_uri_path = LEFT(\'%s\', LENGTH(ocr_server_uri_path))
					  AND (ocr_usa_id_ref = %s OR ocr_usa_id_ref IS NULL)
					  AND oct_usa_id_ref	  = %d
					  AND oct_token_type      = \'access\'
					ORDER BY ocr_usa_id_ref DESC, ocr_consumer_secret DESC, LENGTH(ocr_server_uri_path) DESC
					LIMIT 0,1
					', $host, $path, $user_id, $user_id
					);
		
		if (empty($secrets))
		{
			throw new OAuthException('No server tokens available for '.$uri);
		}
		$secrets['signature_methods'] = explode(',', $secrets['signature_methods']);
		return $secrets;
	}


	/**
	 * Get the token and token secret we obtained from a server.
	 * 
	 * @param string	consumer_key
	 * @param string 	token
	 * @param string	token_type
	 * @param int		user_id			the user owning the token
	 * @exception OAuthException when no credentials found
	 * @return array
	 */
	public function getServerTokenSecrets ( $consumer_key, $token, $token_type, $user_id )
	{
		if ($token_type != 'request' && $token_type != 'access')
		{
			throw new OAuthException('Unkown token type "'.$token_type.'", must be either "request" or "access"');
		}

		// Take the most recent token of the given type
		$r = $this->query_row_assoc('
					SELECT	ocr_consumer_key		as consumer_key,
							ocr_consumer_secret		as consumer_secret,
							oct_token				as token,
							oct_token_secret		as token_secret,
							ocr_signature_methods	as signature_methods,
							ocr_server_uri			as server_uri,
							ocr_request_token_uri	as request_token_uri,
							ocr_authorize_uri		as authorize_uri,
							ocr_access_token_uri	as access_token_uri
					FROM oauth_consumer_registry
							JOIN oauth_consumer_token
							ON oct_ocr_id_ref = ocr_id
					WHERE ocr_consumer_key = \'%s\'
					  AND oct_token_type   = \'%s\'
					  AND oct_token        = \'%s\'
					  AND oct_usa_id_ref   = %d
					', $consumer_key, $token_type, $token, $user_id
					);
					
		if (empty($r))
		{
			throw new OAuthException('Could not find a "'.$token_type.'" token for consumer "'.$consumer_key.'" and user '.$user_id);
		}
		if (isset($r['signature_methods']) && !empty($r['signature_methods']))
		{
			$r['signature_methods'] = explode(',',$r['signature_methods']);
		}
		else
		{
			$r['signature_methods'] = array();
		}
		return $r;		
	}


	/**
	 * Add a request token we obtained from a server.
	 * 
	 * @todo remove old tokens for this user and this ocr_id
	 * @param string consumer_key	key of the server in the consumer registry
	 * @param string token_type		one of 'request' or 'access'
	 * @param string token
	 * @param string token_secret
	 * @param int 	 user_id			the user owning the token
	 * @exception OAuthException when server is not known
	 * @exception OAuthException when we received a duplicate token
	 */
	public function addServerToken ( $consumer_key, $token_type, $token, $token_secret, $user_id )
	{
		if ($token_type != 'request' && $token_type != 'access')
		{
			throw new OAuthException('Unknown token type "'.$token_type.'", must be either "request" or "access"');
		}

		$ocr_id = $this->query_one('
					SELECT ocr_id
					FROM oauth_consumer_registry
					WHERE ocr_consumer_key = \'%s\'
					', $consumer_key);
					
		if (empty($ocr_id))
		{
			throw new OAuthException('No server associated with consumer_key "'.$consumer_key.'"');
		}
		
		// Delete any old tokens with the same type for this user/server combination
		$this->query('
					DELETE FROM oauth_consumer_token
					WHERE oct_ocr_id_ref	= %d
					  AND oct_usa_id_ref	= %d
					  AND oct_token_type	= LOWER(\'%s\')
					',
					$ocr_id,
					$user_id,
					$token_type);

		// Insert the new token
		$this->query('
					INSERT IGNORE INTO oauth_consumer_token
					SET oct_ocr_id_ref	= %d,
						oct_usa_id_ref  = %d,
						oct_token		= \'%s\',
						oct_token_secret= \'%s\',
						oct_token_type	= LOWER(\'%s\'),
						oct_timestamp	= NOW()
					',
					$ocr_id,
					$user_id,
					$token,
					$token_secret,
					$token_type);
		
		if (!$this->query_affected_rows())
		{
			throw new OAuthException('Received duplicate token "'.$token.'" for the same consumer_key "'.$consumer_key.'"');
		}
	}


	/**
	 * Delete a server key.  This removes access to that site.
	 * 
	 * @param string consumer_key
	 * @param int user_id	user registering this server
	 * @param boolean user_is_admin
	 */
	public function deleteServer ( $consumer_key, $user_id, $user_is_admin = false )
	{
		if ($user_is_admin)
		{
			$this->query('
					DELETE FROM oauth_consumer_registry
					WHERE ocr_consumer_key = \'%s\'
					  AND (ocr_usa_id_ref = %d OR ocr_usa_id_ref IS NULL)
					', $consumer_key, $user_id);
		}
		else
		{
			$this->query('
					DELETE FROM oauth_consumer_registry
					WHERE ocr_consumer_key = \'%s\'
					  AND ocr_usa_id_ref   = %d
					', $consumer_key, $user_id);
		}
	}
	
	
	/**
	 * Get a server from the consumer registry using the consumer key
	 * 
	 * @param string consumer_key
	 * @param int user_id
	 * @param boolean user_is_admin (optional)
	 * @exception OAuthException when server is not found
	 * @return array
	 */	
	public function getServer ( $consumer_key, $user_id, $user_is_admin = false )
	{
		$r = $this->query_row_assoc('
				SELECT	ocr_id					as id,
						ocr_usa_id_ref			as user_id,
						ocr_consumer_key 		as consumer_key,
						ocr_consumer_secret 	as consumer_secret,
						ocr_signature_methods	as signature_methods,
						ocr_server_uri			as server_uri,
						ocr_request_token_uri	as request_token_uri,
						ocr_authorize_uri		as authorize_uri,
						ocr_access_token_uri	as access_token_uri
				FROM oauth_consumer_registry
				WHERE ocr_consumer_key = \'%s\'
				  AND (ocr_usa_id_ref = %d OR ocr_usa_id_ref IS NULL)
				',	$consumer_key, $user_id);
		
		if (empty($r))
		{
			throw new OAuthException('No server with consumer_key "'.$consumer_key.'" has been registered (for this user)');
		}
			
		if (isset($r['signature_methods']) && !empty($r['signature_methods']))
		{
			$r['signature_methods'] = explode(',',$r['signature_methods']);
		}
		else
		{
			$r['signature_methods'] = array();
		}
		return $r;
	}



	/**
	 * Find the server details that might be used for a request
	 * 
	 * The consumer_key must belong to the user or be public (user id is null)
	 * 
	 * @param string uri	uri of the server
	 * @param int user_id	id of the logged on user
	 * @exception OAuthException when no credentials found
	 * @return array
	 */
	public function getServerForUri ( $uri, $user_id )
	{
		// Find a consumer key and token for the given uri
		$ps		= parse_url($uri);
		$host	= isset($ps['host']) ? $ps['host'] : 'localhost';
		$path	= isset($ps['path']) ? $ps['path'] : '';
		
		if (empty($path) || substr($path, -1) != '/')
		{
			$path .= '/';
		}

		// The owner of the consumer_key is either the user or nobody (public consumer key)
		$server = $this->query_row_assoc('
					SELECT	ocr_id					as id,
							ocr_usa_id_ref			as user_id,
							ocr_consumer_key		as consumer_key,
							ocr_consumer_secret		as consumer_secret,
							ocr_signature_methods	as signature_methods,
							ocr_server_uri			as server_uri,
							ocr_request_token_uri	as request_token_uri,
							ocr_authorize_uri		as authorize_uri,
							ocr_access_token_uri	as access_token_uri
					FROM oauth_consumer_registry
					WHERE ocr_server_uri_host = \'%s\'
					  AND ocr_server_uri_path = LEFT(\'%s\', LENGTH(ocr_server_uri_path))
					  AND (ocr_usa_id_ref = %s OR ocr_usa_id_ref IS NULL)
					ORDER BY ocr_usa_id_ref DESC, consumer_secret DESC, LENGTH(ocr_server_uri_path) DESC
					LIMIT 0,1
					', $host, $path, $user_id
					);
		
		if (empty($server))
		{
			throw new OAuthException('No server available for '.$uri);
		}
		$server['signature_methods'] = explode(',', $server['signature_methods']);
		return $server;
	}


	/**
	 * Get a list of all server token this user has access to.
	 * 
	 * @param int usr_id
	 * @return array
	 */
	public function listServerTokens ( $user_id )
	{
		$ts = $this->query_all_assoc('
					SELECT	ocr_consumer_key		as consumer_key,
							ocr_consumer_secret		as consumer_secret,
							oct_id					as token_id,
							oct_token				as token,
							oct_token_secret		as token_secret,
							oct_usa_id_ref			as user_id,
							ocr_signature_methods	as signature_methods,
							ocr_server_uri			as server_uri,
							ocr_server_uri_host		as server_uri_host,
							ocr_server_uri_path		as server_uri_path,
							ocr_request_token_uri	as request_token_uri,
							ocr_authorize_uri		as authorize_uri,
							ocr_access_token_uri	as access_token_uri,
							oct_timestamp			as timestamp
					FROM oauth_consumer_registry
							JOIN oauth_consumer_token
							ON oct_ocr_id_ref = ocr_id
					WHERE oct_usa_id_ref = %d
					  AND oct_token_type = \'access\'
					ORDER BY ocr_server_uri_host, ocr_server_uri_path
					', $user_id);
		return $ts;
	}


	/**
	 * Count how many tokens we have for the given server
	 * 
	 * @param string consumer_key
	 * @return int
	 */
	public function countServerTokens ( $consumer_key )
	{
		$count = $this->query_one('
					SELECT COUNT(oct_id)
					FROM oauth_consumer_token
							JOIN oauth_consumer_registry
							ON oct_ocr_id_ref = ocr_id
					WHERE oct_token_type   = \'access\'
					  AND ocr_consumer_key = \'%s\'
					', $consumer_key);
		
		return $count;
	}


	/**
	 * Get a specific server token for the given user
	 * 
	 * @param string consumer_key
	 * @param string token
	 * @param int user_id
	 * @exception OAuthException when no such token found
	 * @return array
	 */
	public function getServerToken ( $consumer_key, $token, $user_id )
	{
		$ts = $this->query_row_assoc('
					SELECT	ocr_consumer_key		as consumer_key,
							ocr_consumer_secret		as consumer_secret,
							oct_token				as token,
							oct_token_secret		as token_secret,
							oct_usa_id_ref			as usr_id,
							ocr_signature_methods	as signature_methods,
							ocr_server_uri			as server_uri,
							ocr_server_uri_host		as server_uri_host,
							ocr_server_uri_path		as server_uri_path,
							ocr_request_token_uri	as request_token_uri,
							ocr_authorize_uri		as authorize_uri,
							ocr_access_token_uri	as access_token_uri,
							oct_timestamp			as timestamp
					FROM oauth_consumer_registry
							JOIN oauth_consumer_token
							ON oct_ocr_id_ref = ocr_id
					WHERE ocr_consumer_key = \'%s\'
					  AND oct_usa_id_ref   = %d
					  AND oct_token_type   = \'access\'
					  AND oct_token        = \'%s\'
					', $consumer_key, $user_id, $token);
		
		if (empty($ts))
		{
			throw new OAuthException('No such consumer key ('.$consumer_key.') and token ('.$token.') combination for user "'.$user_id.'"');
		}
		return $ts;
	}


	/**
	 * Delete a token we obtained from a server.
	 * 
	 * @param string consumer_key
	 * @param string token
	 * @param int user_id
	 * @param boolean no_user_check
	 */
	public function deleteServerToken ( $consumer_key, $token, $user_id, $user_is_admin = false )
	{
		if ($user_is_admin)
		{
			$this->query('
				DELETE oauth_consumer_token 
				FROM oauth_consumer_token
						JOIN oauth_consumer_registry
						ON oct_ocr_id_ref = ocr_id
				WHERE ocr_consumer_key	= \'%s\'
				  AND oct_token			= \'%s\'
				', $consumer_key, $token);
		}
		else
		{
			$this->query('
				DELETE oauth_consumer_token 
				FROM oauth_consumer_token
						JOIN oauth_consumer_registry
						ON oct_ocr_id_ref = ocr_id
				WHERE ocr_consumer_key	= \'%s\'
				  AND oct_token			= \'%s\'
				  AND oct_usa_id_ref	= %d
				', $consumer_key, $token, $user_id);
		}
	}


	/**
	 * Get a list of all consumers from the consumer registry.
	 * The consumer keys belong to the user or are public (user id is null)
	 * 
	 * @param string q	query term
	 * @param int user_id
	 * @return array
	 */	
	public function listServers ( $q = '', $user_id )
	{
		$q    = trim(str_replace('%', '', $q));
		$args = array();

		if (!empty($q))
		{
			$where = ' WHERE (	ocr_consumer_key like \'%%%s%%\'
						  	 OR ocr_server_uri like \'%%%s%%\'
						  	 OR ocr_server_uri_host like \'%%%s%%\'
						  	 OR ocr_server_uri_path like \'%%%s%%\')
						 AND (ocr_usa_id_ref = %d OR ocr_usa_id_ref IS NULL)
					';
			
			$args[] = $q;
			$args[] = $q;
			$args[] = $q;
			$args[] = $q;
			$args[] = $user_id;
		}
		else
		{
			$where  = ' WHERE ocr_usa_id_ref = %d OR ocr_usa_id_ref IS NULL';
			$args[] = $user_id;
		}

		$servers = $this->query_all_assoc('
					SELECT	ocr_id					as id,
							ocr_usa_id_ref			as user_id,
							ocr_consumer_key 		as consumer_key,
							ocr_consumer_secret 	as consumer_secret,
							ocr_signature_methods	as signature_methods,
							ocr_server_uri			as server_uri,
							ocr_server_uri_host		as server_uri_host,
							ocr_server_uri_path		as server_uri_path,
							ocr_request_token_uri	as request_token_uri,
							ocr_authorize_uri		as authorize_uri,
							ocr_access_token_uri	as access_token_uri
					FROM oauth_consumer_registry
					'.$where.'
					ORDER BY ocr_server_uri_host, ocr_server_uri_path
					', $args);
		return $servers;
	}


	/**
	 * Register or update a server for our site (we will be the consumer)
	 * 
	 * (This is the registry at the consumers, registering servers ;-) )
	 * 
	 * @param array server
	 * @param int user_id	user registering this server
	 * @param boolean user_is_admin
	 * @exception OAuthException when fields are missing or on duplicate consumer_key
	 * @return consumer_key
	 */
	public function updateServer ( $server, $user_id, $user_is_admin = false )
	{
		foreach (array('consumer_key', 'server_uri') as $f)
		{
			if (empty($server[$f]))
			{
				throw new OAuthException('The field "'.$f.'" must be set and non empty');
			}
		}
		
		if (!empty($server['id']))
		{
			$exists = $this->query_one('
						SELECT ocr_id
						FROM oauth_consumer_registry
						WHERE ocr_consumer_key = \'%s\'
						  AND ocr_id <> %d
						  AND (ocr_usa_id_ref = %d OR ocr_usa_id_ref IS NULL)
						', $server['consumer_key'], $server['id'], $user_id);
		}
		else
		{
			$exists = $this->query_one('
						SELECT ocr_id
						FROM oauth_consumer_registry
						WHERE ocr_consumer_key = \'%s\'
						  AND (ocr_usa_id_ref = %d OR ocr_usa_id_ref IS NULL)
						', $server['consumer_key'], $user_id);
		}

		if ($exists)
		{
			throw new OAuthException('The server with key "'.$server['consumer_key'].'" has already been registered');
		}

		$parts = parse_url($server['server_uri']);
		$host  = (isset($parts['host']) ? $parts['host'] : 'localhost');
		$path  = (isset($parts['path']) ? $parts['path'] : '/');

		if (isset($server['signature_methods']))
		{
			if (is_array($server['signature_methods']))
			{
				$server['signature_methods'] = strtoupper(implode(',', $server['signature_methods']));
			}
		}	
		else
		{
			$server['signature_methods'] = '';
		}

		// When the user is an admin, then the user can update the user_id of this record
		if ($user_is_admin && array_key_exists('user_id', $server))
		{
			if (is_null($server['user_id']))
			{
				$update_user =  ', ocr_usa_id_ref = NULL';
			}
			else
			{
				$update_user =  ', ocr_usa_id_ref = '.intval($server['user_id']);
			}
		}
		else
		{
			$update_user = '';
		}
		
		if (!empty($server['id']))
		{
			// Check if the current user can update this server definition
			if (!$user_is_admin)
			{
				$ocr_usa_id_ref = $this->query_one('
									SELECT ocr_usa_id_ref
									FROM oauth_consumer_registry
									WHERE ocr_id = %d
									', $server['id']);
				
				if ($ocr_usa_id_ref != $user_id)
				{
					throw new OAuthException('The user "'.$user_id.'" is not allowed to update this server');
				}
			}
			
			// Update the consumer registration	
			$this->query('
					UPDATE oauth_consumer_registry
					SET ocr_consumer_key    	= \'%s\',
						ocr_consumer_secret 	= \'%s\',
						ocr_server_uri	    	= \'%s\',
						ocr_server_uri_host 	= \'%s\',
						ocr_server_uri_path 	= \'%s\',
						ocr_timestamp       	= NOW(),
						ocr_request_token_uri	= \'%s\',
						ocr_authorize_uri		= \'%s\',
						ocr_access_token_uri	= \'%s\',
						ocr_signature_methods	= \'%s\'
						'.$update_user.'
					WHERE ocr_id = %d
					', 
					$server['consumer_key'],
					$server['consumer_secret'],
					$server['server_uri'],
					strtolower($host),
					$path,
					isset($server['request_token_uri']) ? $server['request_token_uri'] : '',
					isset($server['authorize_uri'])     ? $server['authorize_uri']     : '',
					isset($server['access_token_uri'])  ? $server['access_token_uri']  : '',
					$server['signature_methods'],
					$server['id']
					);
		}
		else
		{
			if (empty($update_user))
			{
				// Per default the user owning the key is the user registering the key
				$update_user =  ', ocr_usa_id_ref = '.intval($user_id);
			}

			$this->query('
					INSERT INTO oauth_consumer_registry
					SET ocr_consumer_key    	= \'%s\',
						ocr_consumer_secret 	= \'%s\',
						ocr_server_uri	    	= \'%s\',
						ocr_server_uri_host 	= \'%s\',
						ocr_server_uri_path 	= \'%s\',
						ocr_timestamp       	= NOW(),
						ocr_request_token_uri	= \'%s\',
						ocr_authorize_uri		= \'%s\',
						ocr_access_token_uri	= \'%s\',
						ocr_signature_methods	= \'%s\'
						'.$update_user, 
					$server['consumer_key'],
					$server['consumer_secret'],
					$server['server_uri'],
					strtolower($host),
					$path,
					isset($server['request_token_uri']) ? $server['request_token_uri'] : '',
					isset($server['authorize_uri'])     ? $server['authorize_uri']     : '',
					isset($server['access_token_uri'])  ? $server['access_token_uri']  : '',
					$server['signature_methods']
					);
		
			$ocr_id = $this->query_insert_id();
		}
		return $server['consumer_key'];
	}


	/**
	 * Insert/update a new consumer with this server (we will be the server)
	 * When this is a new consumer, then also generate the consumer key and secret.
	 * Never updates the consumer key and secret.
	 * When the id is set, then the key and secret must correspond to the entry
	 * being updated.
	 * 
	 * (This is the registry at the server, registering consumers ;-) )
	 * 
	 * @param array consumer
	 * @param int user_id	user registering this consumer
	 * @param boolean user_is_admin
	 * @return string consumer key
	 */
	public function updateConsumer ( $consumer, $user_id, $user_is_admin = false )
	{
		if (!$user_is_admin)
		{
			foreach (array('requester_name', 'requester_email') as $f)
			{
				if (empty($consumer[$f]))
				{
					throw new OAuthException('The field "'.$f.'" must be set and non empty');
				}
			}
		}
		
		if (!empty($consumer['id']))
		{
			if (empty($consumer['consumer_key']))
			{
				throw new OAuthException('The field "consumer_key" must be set and non empty');
			}
			if (!$user_is_admin && empty($consumer['consumer_secret']))
			{
				throw new OAuthException('The field "consumer_secret" must be set and non empty');
			}

			// Check if the current user can update this server definition
			if (!$user_is_admin)
			{
				$osr_usa_id_ref = $this->query_one('
									SELECT osr_usa_id_ref
									FROM oauth_server_registry
									WHERE osr_id = %d
									', $consumer['id']);
				
				if ($osr_usa_id_ref != $user_id)
				{
					throw new OAuthException('The user "'.$user_id.'" is not allowed to update this consumer');
				}
			}
			else
			{
				// User is an admin, allow a key owner to be changed or key to be shared
				if (array_key_exists('user_id',$consumer))
				{
					if (is_null($consumer['user_id']))
					{
						$this->query('
							UPDATE oauth_server_registry
							SET osr_usa_id_ref = NULL
							WHERE osr_id = %d
							', $consumer['id']);
					}
					else
					{
						$this->query('
							UPDATE oauth_server_registry
							SET osr_usa_id_ref = %d
							WHERE osr_id = %d
							', $consumer['user_id'], $consumer['id']);	
					}
				}
			}
			
			$this->query('
				UPDATE oauth_server_registry
				SET osr_requester_name		= \'%s\',
					osr_requester_email		= \'%s\',
					osr_callback_uri		= \'%s\',
					osr_application_uri		= \'%s\',
					osr_application_title	= \'%s\',
					osr_application_descr	= \'%s\',
					osr_application_notes	= \'%s\',
					osr_application_type	= \'%s\',
					osr_application_commercial = IF(%d,1,0),
					osr_timestamp			= NOW()
				WHERE osr_id              = %d
				  AND osr_consumer_key    = \'%s\'
				  AND osr_consumer_secret = \'%s\'
				',
				$consumer['requester_name'],
				$consumer['requester_email'],
				isset($consumer['callback_uri']) 		? $consumer['callback_uri'] 			 : '',
				isset($consumer['application_uri']) 	? $consumer['application_uri'] 			 : '',
				isset($consumer['application_title'])	? $consumer['application_title'] 		 : '',
				isset($consumer['application_descr'])	? $consumer['application_descr'] 		 : '',
				isset($consumer['application_notes'])	? $consumer['application_notes'] 		 : '',
				isset($consumer['application_type']) 	? $consumer['application_type'] 		 : '',
				isset($consumer['application_commercial']) ? $consumer['application_commercial'] : 0,
				$consumer['id'],
				$consumer['consumer_key'],
				$consumer['consumer_secret']
				);
				

			$consumer_key = $consumer['consumer_key'];
		}
		else
		{
			$consumer_key	= $this->generateKey(true);
			$consumer_secret= $this->generateKey();

			// When the user is an admin, then the user can be forced to something else that the user
			if ($user_is_admin && array_key_exists('user_id',$consumer))
			{
				if (is_null($consumer['user_id']))
				{
					$owner_id = 'NULL';
				}
				else
				{
					$owner_id = intval($consumer['user_id']);
				}
			}
			else
			{
				// No admin, take the user id as the owner id.
				$owner_id = intval($user_id);
			}

			$this->query('
				INSERT INTO oauth_server_registry
				SET osr_enabled				= 1,
					osr_status				= \'active\',
					osr_usa_id_ref			= %s,
					osr_consumer_key		= \'%s\',
					osr_consumer_secret		= \'%s\',
					osr_requester_name		= \'%s\',
					osr_requester_email		= \'%s\',
					osr_callback_uri		= \'%s\',
					osr_application_uri		= \'%s\',
					osr_application_title	= \'%s\',
					osr_application_descr	= \'%s\',
					osr_application_notes	= \'%s\',
					osr_application_type	= \'%s\',
					osr_application_commercial = IF(%d,1,0),
					osr_timestamp			= NOW(),
					osr_issue_date			= NOW()
				',
				$owner_id,
				$consumer_key,
				$consumer_secret,
				$consumer['requester_name'],
				$consumer['requester_email'],
				isset($consumer['callback_uri']) 		? $consumer['callback_uri'] 			 : '',
				isset($consumer['application_uri']) 	? $consumer['application_uri'] 			 : '',
				isset($consumer['application_title'])	? $consumer['application_title'] 		 : '',
				isset($consumer['application_descr'])	? $consumer['application_descr'] 		 : '',
				isset($consumer['application_notes'])	? $consumer['application_notes'] 		 : '',
				isset($consumer['application_type']) 	? $consumer['application_type'] 		 : '',
				isset($consumer['application_commercial']) ? $consumer['application_commercial'] : 0
				);
		}
		return $consumer_key;

	}



	/**
	 * Delete a consumer key.  This removes access to our site for all applications using this key.
	 * 
	 * @param string consumer_key
	 * @param int user_id	user registering this server
	 * @param boolean user_is_admin
	 */
	public function deleteConsumer ( $consumer_key, $user_id, $user_is_admin = false )
	{
		if ($user_is_admin)
		{
			$this->query('
					DELETE FROM oauth_server_registry
					WHERE osr_consumer_key = \'%s\'
					  AND (osr_usa_id_ref = %d OR osr_usa_id_ref IS NULL)
					', $consumer_key, $user_id);
		}
		else
		{
			$this->query('
					DELETE FROM oauth_server_registry
					WHERE osr_consumer_key = \'%s\'
					  AND osr_usa_id_ref   = %d
					', $consumer_key, $user_id);
		}
	}	
	
	
	
	/**
	 * Fetch a consumer of this server, by consumer_key.
	 * 
	 * @param string consumer_key
	 * @param int user_id
	 * @param boolean user_is_admin (optional)
	 * @exception OAuthException when consumer not found
	 * @return array
	 */
	public function getConsumer ( $consumer_key, $user_id, $user_is_admin = false )
	{
		$consumer = $this->query_row_assoc('
						SELECT	*
						FROM oauth_server_registry
						WHERE osr_consumer_key = \'%s\'
						', $consumer_key);
		
		if (!is_array($consumer))
		{
			throw new OAuthException('No consumer with consumer_key "'.$consumer_key.'"');
		}

		$c = array();
		foreach ($consumer as $key => $value)
		{
			$c[substr($key, 4)] = $value;
		}
		$c['user_id'] = $c['usa_id_ref'];

		if (!$user_is_admin && !empty($r['user_id']) && $r['user_id'] != $user_id)
		{
			throw new OAuthException('No access to the consumer information for consumer_key "'.$consumer_key.'"');
		}
		return $c;
	}


	/**
	 * Fetch the static consumer key for this provider.  The user for the static consumer 
	 * key is NULL (no user, shared key).  If the key did not exist then the key is created.
	 * 
	 * @return string
	 */
	public function getConsumerStatic ()
	{
		$consumer = $this->query_one('
						SELECT osr_consumer_key
						FROM oauth_server_registry
						WHERE osr_consumer_key LIKE \'sc-%%\'
						  AND osr_usa_id_ref IS NULL
						');

		if (empty($consumer))
		{
			$consumer_key = 'sc-'.$this->generateKey(true);
			$this->query('
				INSERT INTO oauth_server_registry
				SET osr_enabled				= 1,
					osr_status				= \'active\',
					osr_usa_id_ref			= NULL,
					osr_consumer_key		= \'%s\',
					osr_consumer_secret		= \'\',
					osr_requester_name		= \'\',
					osr_requester_email		= \'\',
					osr_callback_uri		= \'\',
					osr_application_uri		= \'\',
					osr_application_title	= \'Static shared consumer key\',
					osr_application_descr	= \'\',
					osr_application_notes	= \'Static shared consumer key\',
					osr_application_type	= \'\',
					osr_application_commercial = 0,
					osr_timestamp			= NOW(),
					osr_issue_date			= NOW()
				',
				$consumer_key
				);
			
			// Just make sure that if the consumer key is truncated that we get the truncated string
			$consumer = $this->getConsumerStatic();
		}
		return $consumer;
	}


	/**
	 * Add an unautorized request token to our server.
	 * 
	 * @param string consumer_key
	 * @return array (token, token_secret)
	 */
	public function addConsumerRequestToken ( $consumer_key )
	{
		$token  = $this->generateKey(true);
		$secret = $this->generateKey();
		$osr_id	= $this->query_one('
						SELECT osr_id
						FROM oauth_server_registry
						WHERE osr_consumer_key = \'%s\'
						  AND osr_enabled      = 1
						', $consumer_key);

		if (!$osr_id)
		{
			throw new OAuthException('No server with consumer_key "'.$consumer_key.'" or consumer_key is disabled');
		}	

		$this->query('
				INSERT INTO oauth_server_token
				SET ost_osr_id_ref		= %d,
					ost_usa_id_ref		= %d,
					ost_token			= \'%s\',
					ost_token_secret	= \'%s\',
					ost_token_type		= \'request\',
					ost_authorized		= 1
				ON DUPLICATE KEY UPDATE
					ost_osr_id_ref		= VALUES(ost_osr_id_ref),
					ost_usa_id_ref		= VALUES(ost_usa_id_ref),
					ost_token			= VALUES(ost_token),
					ost_token_secret	= VALUES(ost_token_secret),
					ost_token_type		= VALUES(ost_token_type),
					ost_authorized 		= 1,
					ost_timestamp		= NOW()
				', $osr_id, $osr_id, $token, $secret);
		
		return array('token'=>$token, 'token_secret'=>$secret);
	}
	
	
	/**
	 * Fetch the consumer request token, by request token.
	 * 
	 * @param string token
	 * @return array  token and consumer details
	 */
	public function getConsumerRequestToken ( $token )
	{
		$rs = $this->query_row_assoc('
				SELECT	ost_token			as token,
						ost_token_secret	as token_secret,
						osr_consumer_key	as consumer_key,
						osr_consumer_secret	as consumer_secret,
						ost_token_type		as token_type
				FROM oauth_server_token
						JOIN oauth_server_registry
						ON ost_osr_id_ref = osr_id
				WHERE ost_token_type = \'request\'
				  AND ost_token      = \'%s\'
				', $token);
		
		return $rs;
	}
	

	/**
	 * Delete a consumer token.  The token must be a request or authorized token.
	 * 
	 * @param string token
	 */
	public function deleteConsumerRequestToken ( $token )
	{
		$this->query('
					DELETE FROM oauth_server_token
					WHERE ost_token 	 = \'%s\'
					  AND ost_token_type = \'request\'
					', $token);
	}
	

	/**
	 * Upgrade a request token to be an authorized request token.
	 * 
	 * @param string token
	 * @param int	 user_id  user authorizing the token
	 * @param string referrer_host used to set the referrer host for this token, for user feedback
	 */
	public function authorizeConsumerRequestToken ( $token, $user_id, $referrer_host = '' )
	{
		$this->query('
					UPDATE oauth_server_token
					SET ost_authorized    = 1,
						ost_usa_id_ref    = %d,
						ost_timestamp     = NOW(),
						ost_referrer_host = \'%s\'
					WHERE ost_token      = \'%s\'
					  AND ost_token_type = \'request\'
					', $user_id, $referrer_host, $token);
	}


	/**
	 * Count the consumer access tokens for the given consumer.
	 * 
	 * @param string consumer_key
	 * @return int
	 */
	public function countConsumerAccessTokens ( $consumer_key )
	{
		$count = $this->query_one('
					SELECT COUNT(ost_id)
					FROM oauth_server_token
							JOIN oauth_server_registry
							ON ost_osr_id_ref = osr_id
					WHERE ost_token_type   = \'access\'
					  AND osr_consumer_key = \'%s\'
					', $consumer_key);
		
		return $count;
	}


	/**
	 * Exchange an authorized request token for new access token.
	 * 
	 * @param string token
	 * @param int	 user_id  user authorizing the token
	 * @exception OAuthException when token could not be exchanged
	 * @return array (token, token_secret)
	 */
	public function exchangeConsumerRequestForAccessToken ( $token )
	{
		$new_token  = $this->generateKey(true);
		$new_secret = $this->generateKey();

		$this->query('
					UPDATE oauth_server_token
					SET ost_token			= \'%s\',
						ost_token_secret	= \'%s\',
						ost_token_type		= \'access\',
						ost_timestamp		= NOW()
					WHERE ost_token      = \'%s\'
					  AND ost_token_type = \'request\'
					  AND ost_authorized = 1
					', $new_token, $new_secret, $token);
		
		if ($this->query_affected_rows() != 1)
		{
			throw new OAuthException('Can\'t exchange request token "'.$token.'" for access token. No such token or not authorized');
		}
		return array('token' => $new_token, 'token_secret' => $new_secret);
	}


	/**
	 * Fetch the consumer access token, by access token.
	 * 
	 * @param string token
	 * @param int user_id
	 * @exception OAuthException when token is not found
	 * @return array  token and consumer details
	 */
	public function getConsumerAccessToken ( $token, $user_id )
	{
		$rs = $this->query_row_assoc('
				SELECT	ost_token				as token,
						ost_token_secret		as token_secret,
						ost_referrer_host		as token_referrer_host,
						osr_consumer_key		as consumer_key,
						osr_consumer_secret		as consumer_secret,
						osr_application_uri		as application_uri,
						osr_application_title	as application_title,
						osr_application_descr	as application_descr
				FROM oauth_server_token
						JOIN oauth_server_registry
						ON ost_osr_id_ref = osr_id
				WHERE ost_token_type = \'access\'
				  AND ost_token      = \'%s\'
				  AND ost_usa_id_ref = %d
				', $token, $user_id);
		
		if (empty($rs))
		{
			throw new OAuthException('No server_token "'.$token.'" for user "'.$user_id.'"');
		}
		return $rs;
	}


	/**
	 * Delete a consumer access token.
	 * 
	 * @param string token
	 * @param int user_id
	 * @param boolean user_is_admin
	 */
	public function deleteConsumerAccessToken ( $token, $user_id, $user_is_admin = false )
	{
		if ($user_is_admin)
		{
			$this->query('
						DELETE FROM oauth_server_token
						WHERE ost_token 	 = \'%s\'
						  AND ost_token_type = \'access\'
						', $token);
		}
		else
		{
			$this->query('
						DELETE FROM oauth_server_token
						WHERE ost_token 	 = \'%s\'
						  AND ost_token_type = \'access\'
						  AND ost_usa_id_ref = %d
						', $token, $user_id);
		}
	}


	/**
	 * Fetch a list of all consumer keys, secrets etc.
	 * Returns the public (user_id is null) and the keys owned by the user
	 * 
	 * @param int user_id
	 * @return array
	 */
	public function listConsumers ( $user_id )
	{
		$rs = $this->query_all_assoc('
				SELECT	osr_id					as id,
						osr_usa_id_ref			as user_id,
						osr_consumer_key 		as consumer_key,
						osr_consumer_secret		as consumer_secret,
						osr_enabled				as enabled,
						osr_status 				as status,
						osr_issue_date			as issue_date,
						osr_application_uri		as application_uri,
						osr_application_title	as application_title,
						osr_application_descr	as application_descr,
						osr_requester_name		as requester_name,
						osr_requester_email		as requester_email
				FROM oauth_server_registry
				WHERE (osr_usa_id_ref = %d OR osr_usa_id_ref IS NULL)
				ORDER BY osr_application_title
				', $user_id);
		return $rs;
	}


	/**
	 * Fetch a list of all consumer tokens accessing the account of the given user.
	 * 
	 * @param int user_id
	 * @return array
	 */
	public function listConsumerTokens ( $user_id )
	{
		$rs = $this->query_all_assoc('
				SELECT	osr_consumer_key 		as consumer_key,
						osr_consumer_secret		as consumer_secret,
						osr_enabled				as enabled,
						osr_status 				as status,
						osr_application_uri		as application_uri,
						osr_application_title	as application_title,
						osr_application_descr	as application_descr,
						ost_timestamp			as timestamp,	
						ost_token				as token,
						ost_token_secret		as token_secret,
						ost_referrer_host		as token_referrer_host
				FROM oauth_server_registry
					JOIN oauth_server_token
					ON ost_osr_id_ref = osr_id
				WHERE ost_usa_id_ref = %d
				  AND ost_token_type = \'access\'
				ORDER BY osr_application_title
				', $user_id);
		return $rs;
	}


	/**
	 * Check an nonce/timestamp combination.  Clears any nonce combinations
	 * that are older than the one received.
	 * 
	 * @param string	consumer_key
	 * @param string 	token
	 * @param int		timestamp
	 * @param string 	nonce
	 * @exception OAuthException	thrown when the timestamp is not in sequence or nonce is not unique
	 */
	public function checkServerNonce ( $consumer_key, $token, $timestamp, $nonce )
	{
		$r = $this->query_row('
							SELECT MAX(osn_timestamp), MAX(osn_timestamp) > %d + %d
							FROM oauth_server_nonce
							WHERE osn_consumer_key = \'%s\'
							  AND osn_token        = \'%s\'
							', $timestamp, $this->max_timestamp_skew, $consumer_key, $token);

		if (!empty($r) && $r[1])
		{
			throw new OAuthException('Timestamp is out of sequence. Request rejected. Got '.$timestamp.' last max is '.$r[0].' allowed skew is '.$this->max_timestamp_skew);
		}
		
		// Insert the new combination
		$this->query('
				INSERT IGNORE INTO oauth_server_nonce
				SET osn_consumer_key	= \'%s\',
					osn_token			= \'%s\',
					osn_timestamp		= %d,
					osn_nonce			= \'%s\'
				', $consumer_key, $token, $timestamp, $nonce);
		
		if ($this->query_affected_rows() == 0)
		{
			throw new OAuthException('Duplicate timestamp/nonce combination, possible replay attack.  Request rejected.');
		}

		// Clean up all timestamps older than the one we just received
		$this->query('
				DELETE FROM oauth_server_nonce
				WHERE osn_consumer_key	= \'%s\'
				  AND osn_token			= \'%s\'
				  AND osn_timestamp     < %d - %d
				', $consumer_key, $token, $timestamp, $this->max_timestamp_skew);
	}


	/**
	 * Add an entry to the log table
	 * 
	 * @param array keys (osr_consumer_key, ost_token, ocr_consumer_key, oct_token)
	 * @param string received
	 * @param string sent
	 * @param string base_string
	 * @param string notes
	 * @param int (optional) user_id
	 */
	public function addLog ( $keys, $received, $sent, $base_string, $notes, $user_id = null )
	{
		$args = array();
		$ps   = array();
		foreach ($keys as $key => $value)
		{
			$args[] = $value;
			$ps[]   = "olg_$key = '%s'";
		}

		if (!empty($_SERVER['REMOTE_ADDR']))
		{
			$remote_ip = $_SERVER['REMOTE_ADDR'];
		}	
		else if (!empty($_SERVER['REMOTE_IP']))
		{
			$remote_ip = $_SERVER['REMOTE_IP'];
		}
		else
		{
			$remote_ip = '0.0.0.0';
		}

		// Build the SQL
		$ps[] = "olg_received  	= '%s'";						$args[] = $this->makeUTF8($received);
		$ps[] = "olg_sent   	= '%s'";						$args[] = $this->makeUTF8($sent);
		$ps[] = "olg_base_string= '%s'";						$args[] = $base_string;
		$ps[] = "olg_notes   	= '%s'";						$args[] = $this->makeUTF8($notes);
		$ps[] = "olg_usa_id_ref = NULLIF(%d,0)";				$args[] = $user_id;
		$ps[] = "olg_remote_ip  = IFNULL(INET_ATON('%s'),0)";	$args[] = $remote_ip;

		$this->query('INSERT INTO oauth_log SET '.implode(',', $ps), $args);
	}
	
	
	/**
	 * Get a page of entries from the log.  Returns the last 100 records
	 * matching the options given.
	 * 
	 * @param array options
	 * @param int user_id	current user
	 * @return array log records
	 */
	public function listLog ( $options, $user_id )
	{
		$where = array();
		$args  = array();
		if (empty($options))
		{
			$where[] = 'olg_usa_id_ref = %d';
			$args[]  = $user_id;
		}
		else
		{
			foreach ($options as $option => $value)
			{
				if (strlen($value) > 0)
				{
					switch ($option)
					{
					case 'osr_consumer_key':
					case 'ocr_consumer_key':
					case 'ost_token':
					case 'oct_token':
						$where[] = 'olg_'.$option.' = \'%s\'';
						$args[]  = $value;	
						break;				
					}
				}
			}
			
			$where[] = '(olg_usa_id_ref IS NULL OR olg_usa_id_ref = %d)';
			$args[]  = $user_id;
		}

		$rs = $this->query_all_assoc('
					SELECT olg_id,
							olg_osr_consumer_key 	AS osr_consumer_key,
							olg_ost_token			AS ost_token,
							olg_ocr_consumer_key	AS ocr_consumer_key,
							olg_oct_token			AS oct_token,
							olg_usa_id_ref			AS user_id,
							olg_received			AS received,
							olg_sent				AS sent,
							olg_base_string			AS base_string,
							olg_notes				AS notes,
							olg_timestamp			AS timestamp,
							INET_NTOA(olg_remote_ip) AS remote_ip
					FROM oauth_log
					WHERE '.implode(' AND ', $where).'
					ORDER BY olg_id DESC
					LIMIT 0,100', $args);

		return $rs;
	}



	/**
	 * Initialise the database
	 */
	public function install ()
	{
		require_once dirname(__FILE__) . '/mysql/install.php';
	}
	
	
	/* ** Some simple helper functions for querying the mysql db ** */

	/**
	 * Perform a query, ignore the results
	 * 
	 * @param string sql
	 * @param vararg arguments (for sprintf)
	 */
	protected function query ( $sql )
	{
		$sql = $this->sql_printf(func_get_args());
		if (!($res = mysql_query($sql, $this->conn)))
		{
			$this->sql_errcheck($sql);
		}
		if (is_resource($res))
		{
			mysql_free_result($res);
		}
	}
	

	/**
	 * Perform a query, ignore the results
	 * 
	 * @param string sql
	 * @param vararg arguments (for sprintf)
	 * @return array
	 */
	protected function query_all_assoc ( $sql )
	{
		$sql = $this->sql_printf(func_get_args());
		if (!($res = mysql_query($sql, $this->conn)))
		{
			$this->sql_errcheck($sql);
		}
		$rs = array();
		while ($row  = mysql_fetch_assoc($res))
		{
			$rs[] = $row;
		}
		mysql_free_result($res);
		return $rs;
	}
	
	
	/**
	 * Perform a query, return the first row
	 * 
	 * @param string sql
	 * @param vararg arguments (for sprintf)
	 * @return array
	 */
	protected function query_row_assoc ( $sql )
	{
		$sql = $this->sql_printf(func_get_args());
		if (!($res = mysql_query($sql, $this->conn)))
		{
			$this->sql_errcheck($sql);
		}
		if ($row = mysql_fetch_assoc($res))
		{
			$rs = $row;
		}
		else
		{
			$rs = false;
		}
		mysql_free_result($res);
		return $rs;
	}

	
	/**
	 * Perform a query, return the first row
	 * 
	 * @param string sql
	 * @param vararg arguments (for sprintf)
	 * @return array
	 */
	protected function query_row ( $sql )
	{
		$sql = $this->sql_printf(func_get_args());
		if (!($res = mysql_query($sql, $this->conn)))
		{
			$this->sql_errcheck($sql);
		}
		if ($row = mysql_fetch_array($res))
		{
			$rs = $row;
		}
		else
		{
			$rs = false;
		}
		mysql_free_result($res);
		return $rs;
	}
	
		
	/**
	 * Perform a query, return the first column of the first row
	 * 
	 * @param string sql
	 * @param vararg arguments (for sprintf)
	 * @return mixed
	 */
	protected function query_one ( $sql )
	{
		$sql = $this->sql_printf(func_get_args());
		if (!($res = mysql_query($sql, $this->conn)))
		{
			$this->sql_errcheck($sql);
		}
		$val = @mysql_result($res, 0, 0);
		mysql_free_result($res);
		return $val;
	}
	
	
	/**
	 * Return the number of rows affected in the last query
	 */
	protected function query_affected_rows ()
	{
		return mysql_affected_rows($this->conn);
	}


	/**
	 * Return the id of the last inserted row
	 * 
	 * @return int
	 */
	protected function query_insert_id ()
	{
		return mysql_insert_id($this->conn);
	}
	
	
	protected function sql_printf ( $args )
	{
		$sql  = array_shift($args);
		if (count($args) == 1 && is_array($args[0]))
		{
			$args = $args[0];
		}
		$args = array_map(array($this, 'sql_escape_string'), $args);
		return vsprintf($sql, $args);
	}
	
	
	protected function sql_escape_string ( $s )
	{
		if (is_string($s))
		{
			return mysql_real_escape_string($s, $this->conn);
		}
		else if (is_null($s))
		{
			return NULL;
		}
		else if (is_bool($s))
		{
			return intval($s);
		}
		else if (is_int($s) || is_float($s))
		{
			return $s;
		}
		else
		{
			return mysql_real_escape_string(strval($s), $this->conn);
		}
	}
	
	
	protected function sql_errcheck ( $sql )
	{
		if (mysql_errno($this->conn))
		{
			echo "SQL Error in OAuthStoreMySQL: ".mysql_error($this->conn)."\n\n";
			echo $sql;
			die();
		}
	}
}


/* vi:set ts=4 sts=4 sw=4 binary noeol: */

?>