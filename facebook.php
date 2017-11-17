<?php
/**
 * Copyright 2011 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License"); you may
 * not use this file except in compliance with the License. You may obtain
 * a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
 * License for the specific language governing permissions and limitations
 * under the License.
 */

require_once "base_facebook.php";

/**
 * Extends the BaseFacebook class with the intent of using
 * PHP sessions to store user ids and access tokens.
 */
class Facebook extends BaseFacebook
{
  /**
   * Cookie prefix
   */
  const FBSS_COOKIE_NAME = 'fbss';

  /**
   * We can set this to a high number because the main session
   * expiration will trump this.
   */
  const FBSS_COOKIE_EXPIRE = 31556926; // 1 year

  /**
   * Stores the shared session ID if one is set.
   *
   * @var string
   */
  protected $sharedSessionID;

  /**
   * Identical to the parent constructor, except that
   * we start a PHP session to store the user ID and
   * access token if during the course of execution
   * we discover them.
   *
   * @param array $config the application configuration. Additionally
   * accepts "sharedSession" as a boolean to turn on a secondary
   * cookie for environments with a shared session (that is, your app
   * shares the domain with other apps).
   *
   * @see BaseFacebook::__construct
   */
  public function __construct($config) {
    if ((function_exists('session_status') 
      && session_status() !== PHP_SESSION_ACTIVE) || !session_id()) {
      session_start();
    }
    parent::__construct($config);
    if (!empty($config['sharedSession'])) {
      $this->initSharedSession();

      // re-load the persisted state, since parent
      // attempted to read out of non-shared cookie
      $state = $this->getPersistentData('state');
      if (!empty($state)) {
        $this->state = $state;
      } else {
        $this->state = null;
      }

    }
  }

  /**
   * Supported keys for persistent data
   *
   * @var array
   */
  protected static $kSupportedKeys =
    array('state', 'code', 'access_token', 'user_id');

  /**
   * Initiates Shared Session
   */
  protected function initSharedSession() {
    $cookie_name = $this->getSharedSessionCookieName();
    if (isset($_COOKIE[$cookie_name])) {
      $data = $this->parseSignedRequest($_COOKIE[$cookie_name]);
      if ($data && !empty($data['domain']) &&
          self::isAllowedDomain($this->getHttpHost(), $data['domain'])) {
        // good case
        $this->sharedSessionID = $data['id'];
        return;
      }
      // ignoring potentially unreachable data
    }
    // evil/corrupt/missing case
    $base_domain = $this->getBaseDomain();
    $this->sharedSessionID = md5(uniqid(mt_rand(), true));
    $cookie_value = $this->makeSignedRequest(
      array(
        'domain' => $base_domain,
        'id' => $this->sharedSessionID,
      )
    );
    $_COOKIE[$cookie_name] = $cookie_value;
    if (!headers_sent()) {
      $expire = time() + self::FBSS_COOKIE_EXPIRE;
      setcookie($cookie_name, $cookie_value, $expire, '/', '.'.$base_domain);
    } else {
      // @codeCoverageIgnoreStart
      self::errorLog(
        'Shared session ID cookie could not be set! You must ensure you '.
        'create the Facebook instance before headers have been sent. This '.
        'will cause authentication issues after the first request.'
      );
      // @codeCoverageIgnoreEnd
    }
  }

 public function getSession() {
    if (!$this->sessionLoaded) {
      $session = null;
      $write_cookie = true;
      // try loading session from signed_request in $_REQUEST
      $signedRequest = $this->getSignedRequest();
      if ($signedRequest) {
      	error_log("suca");
        // sig is good, use the signedRequest
        $session = $this->createSessionFromSignedRequest($signedRequest);
      }

      // try loading session from $_REQUEST
      if (!$session && isset($_REQUEST['session'])) {
        $session = json_decode(
          get_magic_quotes_gpc()
            ? stripslashes($_REQUEST['session'])
            : $_REQUEST['session'],
          true
        );
        $session = $this->validateSessionObject($session);
              	error_log("suca");
      }

      // try loading session from cookie if necessary
      if (!$session && $this->useCookieSupport()) {
        $cookieName = $this->getSessionCookieName();
        if (isset($_COOKIE[$cookieName])) {
          $session = array();
          parse_str(trim(
            get_magic_quotes_gpc()
              ? stripslashes($_COOKIE[$cookieName])
              : $_COOKIE[$cookieName],
            '"'
          ), $session);
          $session = $this->validateSessionObject($session);
          // write only if we need to delete a invalid session cookie
          $write_cookie = empty($session);
        }
              	error_log("suca");
      }

      $this->setSession($session, $write_cookie);
    }
    return $this->session;
  }


  protected function parseSignedRequest($signed_request) {
    list($encoded_sig, $payload) = explode('.', $signed_request, 2);

    // decode the data
    $sig = self::base64UrlDecode($encoded_sig);
    $data = json_decode(self::base64UrlDecode($payload), true);

    if (strtoupper($data['algorithm']) !== 'HMAC-SHA256') {
      self::errorLog('Unknown algorithm. Expected HMAC-SHA256');
      return null;
    }

    // check sig
    $expected_sig = hash_hmac('sha256', $payload,
                              $this->getApiSecret(), $raw = true);
    if ($sig !== $expected_sig) {
      self::errorLog('Bad Signed JSON signature!');
      return null;
    }

    return $data;
  }
  /**
   * Provides the implementations of the inherited abstract
   * methods. The implementation uses PHP sessions to maintain
   * a store for authorization codes, user ids, CSRF states, and
   * access tokens.
   */

  /**
   * {@inheritdoc}
   *
   * @see BaseFacebook::setPersistentData()
   */
  protected function setPersistentData($key, $value) {
    if (!in_array($key, self::$kSupportedKeys)) {
      self::errorLog('Unsupported key passed to setPersistentData.');
      return;
    }

    $session_var_name = $this->constructSessionVariableName($key);
    $_SESSION[$session_var_name] = $value;
  }

  protected function createSessionFromSignedRequest($data) {
    if (!isset($data['oauth_token'])) {
      return null;
    }

    $session = array(
      'uid'          => $data['user_id'],
      'access_token' => $data['oauth_token'],
      'expires'      => $data['expires'],
    );

    // put a real sig, so that validateSignature works
    $session['sig'] = self::generateSignature(
      $session,
      $this->getApiSecret()
    );

    return $session;
  }

  public function useCookieSupport() {
    return $this->cookieSupport;
  }
  /**
   * {@inheritdoc}
   *
   * @see BaseFacebook::getPersistentData()
   */
  protected function getPersistentData($key, $default = false) {
    if (!in_array($key, self::$kSupportedKeys)) {
      self::errorLog('Unsupported key passed to getPersistentData.');
      return $default;
    }

    $session_var_name = $this->constructSessionVariableName($key);
    return isset($_SESSION[$session_var_name]) ?
      $_SESSION[$session_var_name] : $default;
  }
    protected function validateSessionObject($session) {
    // make sure some essential fields exist
    if (is_array($session) &&
        isset($session['uid']) &&
        isset($session['access_token']) &&
        isset($session['sig'])) {
      // validate the signature
      $session_without_sig = $session;
      unset($session_without_sig['sig']);
      $expected_sig = self::generateSignature(
        $session_without_sig,
        $this->getApiSecret()
      );
      if ($session['sig'] != $expected_sig) {
        self::errorLog('Got invalid session signature in cookie.');
        $session = null;
      }
      // check expiry time
    } else {
      $session = null;
    }
    return $session;
  }
  
  public function setSession($session=null, $write_cookie=true) {

    $session = $this->validateSessionObject($session);
    $this->sessionLoaded = true;
    $this->session = $session;
    if ($write_cookie) {
      $this->setCookieFromSession($session);
    }
    
    return $this;
  }
  
  
  /**
   * {@inheritdoc}
   *
   * @see BaseFacebook::clearPersistentData()
   */
  protected function clearPersistentData($key) {
    if (!in_array($key, self::$kSupportedKeys)) {
      self::errorLog('Unsupported key passed to clearPersistentData.');
      return;
    }

    $session_var_name = $this->constructSessionVariableName($key);
    if (isset($_SESSION[$session_var_name])) {
      unset($_SESSION[$session_var_name]);
    }
  }

  /**
   * {@inheritdoc}
   *
   * @see BaseFacebook::clearAllPersistentData()
   */
  protected function clearAllPersistentData() {
    foreach (self::$kSupportedKeys as $key) {
      $this->clearPersistentData($key);
    }
    if ($this->sharedSessionID) {
      $this->deleteSharedSessionCookie();
    }
  }

  protected $appId;

  /**
   * The Application API Secret.
   */
  protected $apiSecret;

  /**
   * The active user session, if one is available.
   */
  protected $session;

  /**
   * The data from the signed_request token.
   */
  protected $signedRequest;

  /**
   * Indicates that we already loaded the session as best as we could.
   */
  protected $sessionLoaded = false;

  /**
   * Indicates if Cookie support should be enabled.
   */
  protected $cookieSupport = false;
  /**
   * Deletes Shared session cookie
   */
  protected function deleteSharedSessionCookie() {
    $cookie_name = $this->getSharedSessionCookieName();
    unset($_COOKIE[$cookie_name]);
    $base_domain = $this->getBaseDomain();
    setcookie($cookie_name, '', 1, '/', '.'.$base_domain);
  }


  /**
   * Returns the Shared session cookie name
   *
   * @return string The Shared session cookie name
   */
   
    
  public function getAppId() {
    return $this->appId;
  }

  /**
   * Set the App Secret.
   *
   * @param string $apiSecret The App Secret
   *
   * @return BaseFacebook
   * @deprecated Use setAppSecret instead.
   * @see setAppSecret()
   */
  public function setApiSecret($apiSecret) {
    $this->setAppSecret($apiSecret);
    return $this;
  }

  /**
   * Set the App Secret.
   *
   * @param string $appSecret The App Secret
   *
   * @return BaseFacebook
   */
  public function setAppSecret($appSecret) {
    $this->appSecret = $appSecret;
    return $this;
  }

  /**
   * Get the App Secret.
   *
   * @return string the App Secret
   *
   * @deprecated Use getAppSecret instead.
   * @see getAppSecret()
   */
  public function getApiSecret() {
    return $this->getAppSecret();
  }
  protected function getSharedSessionCookieName() {
    return self::FBSS_COOKIE_NAME . '_' . $this->getAppId();
  }

  protected function setCookieFromSession($session=null) {
    if (!$this->useCookieSupport()) {
      return;
    }

    $cookieName = $this->getSessionCookieName();
    $value = 'deleted';
    $expires = time() - 3600;
    $domain = $this->getBaseDomain();
    if ($session) {
      $value = '"' . http_build_query($session, null, '&') . '"';
      if (isset($session['base_domain'])) {
        $domain = $session['base_domain'];
      }
      $expires = $session['expires'];
    }

    // prepend dot if a domain is found
    if ($domain) {
      $domain = '.' . $domain;
    }

    // if an existing cookie is not set, we dont need to delete it
    if ($value == 'deleted' && empty($_COOKIE[$cookieName])) {
      return;
    }

    if (headers_sent()) {
      self::errorLog('Could not set cookie. Headers already sent.');

    // ignore for code coverage as we will never be able to setcookie in a CLI
    // environment
    // @codeCoverageIgnoreStart
    } else {
      setcookie($cookieName, $value, $expires, '/', $domain);
    }
    // @codeCoverageIgnoreEnd
  }
  /**
   * Constructs and returns the name of the session key.
   *
   * @see setPersistentData()
   * @param string $key The key for which the session variable name to construct.
   *
   * @return string The name of the session key.
   */
  protected function constructSessionVariableName($key) {
    $parts = array('fb', $this->getAppId(), $key);
    if ($this->sharedSessionID) {
      array_unshift($parts, $this->sharedSessionID);
    }
    return implode('_', $parts);
  }
}
?>