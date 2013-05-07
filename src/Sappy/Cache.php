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


use Dioxide\Interfaces\CacheInterface;

/**
 * Class Cache
 *
 * @package Dioxide
 */
class Cache implements CacheInterface
{

    private $_cacheDir = null;
    private $_maxAge   = 600;

    /**
     * Cache constructor
     *
     * @param null $cacheDir
     * @param int  $maxAge
     */
    public function __construct($cacheDir = null, $maxAge = 600) // 10 minute default
    {
        $this->_cacheDir = $cacheDir;
        $this->_maxAge   = $maxAge;
    }

    /**
     * Return the max age
     *
     * @return int
     */
    public function getMaxAge()
    {
        return $this->_maxAge;
    }

    /**
     * Get a cache entry based on $key
     *
     * @param  $key
     * @return array|bool
     */
    public function get($key)
    {
        if ($this->_exists($key) && !$this->isExpired($key)) {
            $out = [
                'content'   => unserialize(file_get_contents($this->_cacheFilePath($key))),
                'filemtime' => gmdate('U', filemtime($this->_cacheFilePath($key))),
            ];

            return $out;
        }

        return false;
    }

    /**
     * Set a cache entry based on $key
     *
     * @param  $key
     * @param  $contents
     * @return bool
     */
    public function set($key, $contents)
    {
        return !!file_put_contents($this->_cacheFilePath($key), serialize($contents));
    }

    /**
     * Check if a cache entry sis expired
     *
     * @param  $key
     * @return bool
     */
    public function isExpired($key)
    {
        if ($this->_exists($key)) {
            $fileTime = gmdate('U', filemtime($this->_cacheFilePath($key)));
            return (!$fileTime || ((gmdate('U', time()) - $fileTime) >= $this->_maxAge)) ? true : false;
        } else {
            return false;
        }
    }

    /**
     * Generate a valid etag for a cache entries contents
     *
     * @param  $key
     * @return string
     */
    public function getETag($key)
    {
        return '"' . md5(serialize($this->get($key)['content'])) . '"';
    }

    /**
     * Generate a fill file path for a cache entry
     *
     * @param  $key
     * @return string
     */
    private function _cacheFilePath($key)
    {
        return $this->_cacheDir . DIRECTORY_SEPARATOR . md5($key) . '.cache';
    }

    /**
     * Check if a cache entry exists
     *
     * @param  $key
     * @return bool
     */
    private function _exists($key)
    {
        return !!file_exists($this->_cacheFilePath($key));
    }

}

?>