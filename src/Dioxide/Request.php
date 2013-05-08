<?php
/**
 * Copyright (c)2013 Andrew Heebner
 *
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the
 * "Software"), to deal in the Software without restriction, including
 * without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject to
 * the following conditions:
 * 
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
 * LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
 * OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
 * WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

namespace Dioxide;

use Dioxide\Exceptions\HTTPException;
use Dioxide\Transport\JSON;

/**
 * Request class
 *
 * Abstract class supplying numerous methods for abstracting the HTTP request
 *
 * @author      Andrew Heebner <andrew.heebner@gmail.com>
 * @copyright   (c)2013, Andrew Heebner
 * @license     MIT
 * @package     Dioxide
 */
abstract class Request
{

    protected static $_validNamespaces = [];
    protected static $_requestPath     = null;
    protected static $_requestHeaders  = [];
    protected static $_data            = null;
    protected static $_requestId       = null;

    protected static $_authLocation    = '\Dioxide\Auth\\';
    protected static $_allowedAuth     = ['Basic', 'Bearer'];


    /**
     * Return the current request ID
     *
     * @return null|string
     */
    public function getRequestId()
    {
        return static::$_requestId;
    }

    /**
     * Return the current request method (GET, POST, ...)
     *
     * @return string
     */
    public static function getRequestMethod()
    {
        return $_SERVER['REQUEST_METHOD'];
    }

    /**
     * Check if the request was done securely
     *
     * @return bool
     */
    public static function isSecure()
    {
        return !!(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off');
    }

    /**
     * Check if the request was made via AJAX
     *
     * @return bool
     */
    public static function isAjax()
    {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            return !!(strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');
        }

        return false;
    }

    /**
     * Return the requested HTTP version
     *
     * @return float
     */
    public static function getHTTPVersion()
    {
        return (float)substr($_SERVER['SERVER_PROTOCOL'], -3);
    }

    /**
     * Get the requester's IP address
     *
     * @return null|float
     */
    public static function getRemoteAddr()
    {
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
    }

    /**
     * Get the requester's IP address (cycle through proxies)
     * @return null|float
     */
    public static function getRealRemoteAddr()
    {
        $remoteAddr = self::getRemoteAddr();

        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $remoteAddr = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $remoteAddr = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }

        return $remoteAddr;
    }

    /**
     * Get the accepted content-types from the client
     *
     * @return array|null
     */
    public static function getAccept()
    {
        return isset($_SERVER['HTTP_ACCEPT']) ?
            static::_parseHeaderValue($_SERVER['HTTP_ACCEPT']) :
            null;
    }

    /**
     * Get the accepted charsets from the client
     *
     * @return array|null
     */
    public static function getAcceptCharset()
    {
        return isset($_SERVER['HTTP_ACCEPT_CHARSET']) ?
            static::_parseHeaderValue($_SERVER['HTTP_ACCEPT_CHARSET']) :
            null;
    }

    /**
     * Get the accepted languages from the client
     *
     * @return array|null
     */
    public static function getAcceptLanguage()
    {
        return isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ?
            static::_parseHeaderValue($_SERVER['HTTP_ACCEPT_LANGUAGE']) :
            null;
    }

    /**
     * Get the accepted encodings from the client
     *
     * @return array|null
     */
    public static function getAcceptEncoding()
    {
        return isset($_SERVER['HTTP_ACCEPT_ENCODING']) ?
            static::_parseHeaderValue($_SERVER['HTTP_ACCEPT_ENCODING']) :
            null;
    }

    /**
     * Get the HTTP cookies for the current request
     *
     * @param  null       $cookieName
     * @return array|null
     */
    public function getCookies($cookieName = null)
    {
        $out = [];
        $cookies = static::getHeader('Cookie');

        if (!is_null($cookies)) {
            foreach (explode('; ', $cookies) as $k => $v) {
                $out[$k] = urldecode($v);
            }

            if (!empty($cookieName)) {
                return isset($out[$cookieName]) ? $out[$cookieName] : null;
            } else {
                return $out;
            }
        }

        return null;
    }

    /**
     * Get the requester's user agent
     *
     * @return null|string
     */
    public static function getUserAgent()
    {
        return (self::hasUserAgent()) ? $_SERVER['HTTP_USER_AGENT'] : null;
    }

    /**
     * Check if the request has a user agent
     *
     * @return bool
     */
    public static function hasUserAgent()
    {
        return !!isset($_SERVER['HTTP_USER_AGENT']);
    }

    /**
     * Return all request headers
     *
     * @return array
     */
    public static function getHeaders()
    {
        return static::$_requestHeaders;
    }

    /**
     * Return a specified request header
     *
     * @param  $header
     * @return null|string
     */
    public static function getHeader($header)
    {
        return isset(static::$_requestHeaders[$header]) ? static::$_requestHeaders[$header] : null;
    }

    /**
     * Return the current *real* request path (sans namespace)
     *
     * @return null|string
     */
    public static function getRequestPath()
    {
        if (!empty(static::$_validNamespaces)) {
            $_parts = explode('/', static::$_requestPath);
            if (in_array($_parts[0], static::$_validNamespaces)) {
                array_shift($_parts);
                return join('/', $_parts);
            }
        }

        return static::$_requestPath;
    }

    /**
     * Return the real request path, as is came in via the request
     *
     * @return mixed
     */
    public static function getRealRequestPath()
    {
        return $_SERVER['REQUEST_URI'];
    }

    /**
     * Get the current requested namespace
     *
     * @return null
     */
    public static function getNamespace()
    {
        if (!empty(static::$_validNamespaces)) {
            $_parts = explode('/', static::$_requestPath);
            if (in_array($_parts[0], static::$_validNamespaces)) {
                return $_parts[0];
            }
        }

        return null;
    }

    /**
     * Set the current request content
     *
     * @param  $data
     * @return void
     */
    public function setContent($data)
    {
        static::$_data = $data;
    }

    /**
     * Return the current request content
     *
     * @return mixed
     * @throws HTTPException
     */
    public function getContent()
    {
        $cType = static::getHeader('Content-Type');

        switch ($cType) {
            case 'application/json':
                return JSON::decode(static::$_data);
                break;
            case 'application/x-www-form-urlencoded':
                $out = [];
                parse_str(static::$_data, $out);
                return $out;
                break;
            default:
                throw new HTTPException('Incoming content not a proper content-type format', 400);
                break;
        }
    }

    /**
     * Allow API creator to set their own custom JSON content-type
     *    ex: application/vnd.sappy+json (must end in "json")
     *
     * @param  string $contentType
     * @return void
     */
    public static function setContentType($contentType)
    {
        if (strtolower(substr($contentType, -4)) == 'json') {
            JSON::setContentType($contentType);
        }
    }

    /**
     * Get authorization data via HTTP headers
     *
     * @param  string $forceClass Class to force inject to test against
     * @return object
     * @throws HTTPException
     */
    public static function getAuthData($forceClass = null)
    {
        $auth = static::getHeader('Authorization');

        if (!empty($auth)) {
            list($type, $data) = explode(' ', $auth, 2);

            $type = !is_null($forceClass) ? $forceClass : ucfirst($type);

            if (in_array($type, static::$_allowedAuth)) {
                $classStr = static::$_authLocation . $type;

                if (class_exists($classStr)) {
                    $class = new $classStr($data);
                    return $class->getData();
                } else {
                    throw new HTTPException('Authorization scheme not found', 403);
                }
            } else {
                throw new HTTPException('Authorization scheme not allowed', 403);
            }
        }

        return false;
    }

    /**
     * Normalize a route/request path
     *
     * @param  $path
     * @return string
     */
    public static function normalizePath($path)
    {
        $path = trim($path, '/');
        return empty($path) ? '/' : $path;
    }

    /**
     * Set the current request headers
     *
     * @return void
     */
    protected static function _setRequestHeaders()
    {
        $http = [];

        if (!function_exists('getallheaders')) {
            $parseHeader = function ($name) {
                $out   = [];
                $parts = explode('_', $name);
                array_shift($parts);

                foreach ($parts as $value) {
                    $out[] = ucfirst(strtolower($value));
                }

                return join('-', $out);
            };

            foreach ($_SERVER as $k => $v) {
                if (substr($k, 0, 5) == 'HTTP_') {
                    $http[$parseHeader($k)] = $v;
                } elseif ($k == 'CONTENT_TYPE') {
                    $http['Content-Type'] = $v;
                } elseif (!isset($_SERVER['HTTP_AUTHORIZATION']) && $k == 'REDIRECT_HTTP_AUTHORIZATION' && !empty($v)) {
                    $http['Authorization'] = $v;
                }
            }
        } else {
            $http = getallheaders();
        }

        static::$_requestHeaders = $http;
    }

    /**
     * Parse a header line into an array based on preference
     *
     * @param  $value
     * @return array
     */
    private static function _parseHeaderValue($value)
    {
        $out = [];
        $data = explode(',', str_replace(' ', '', $value));

        foreach ($data as $val) {

            $_val = $val;
            $_qval = 1.0;

            if (false !== strstr($val, ';q=')) {
                list($_val, $tmp) = explode(';', $val);
                $q = explode('=', $tmp);
                $_qval = (float)$q[1];
            }

            $out[urldecode($_val)] = $_qval;
        }

        arsort($out);
        return $out;
    }

}

?>