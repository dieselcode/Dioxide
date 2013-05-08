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

require_once 'autoloader.php';

class TestApi extends Dioxide\App
{

    private $options = array(
        'use_output_compression' => true,
        'require_user_agent'     => true,
        'allow_app_extending'    => true,
    );


    public function __construct($namespaces = [])
    {
        $cache = new \Dioxide\Cache('C:\xampp\htdocs\cache', 600);
        parent::__construct($namespaces, $this->options, $cache);

        // set our own custom content type (must be a json type string, ending in 'json')
        $this->setContentType('application/vnd.sappy+json');

        $this->setExtendables();
        $this->setCallbacks();
        $this->setRoutes();
        $this->testParamRoutes();
    }

    public function setExtendables()
    {
        // becomes available as $this->foo()
        $this->extend('foo', function() {
            return [$this->getUserAgent(), $this->getRemoteAddr()];
        });
    }

    public function setCallbacks()
    {
        // internal callback, used for all auth requests
        //  __AUTH__ must return true or false
        $this->on('__AUTH__', function($auth, $request) {
            if (is_object($auth)) {
                if ($auth->type == 'Basic') {
                    if ($auth->user == 'Andrew' && $auth->password == 'foo') {
                        return true;
                    }
                }
            }

            return false;
        });

        // dummy callback, just shows how to add return headers to be used
        $this->on('rate.limit.headers', function($remoteAddr) {
            // .. do something with the remoteAddr
            return ['X-RateLimit-Limit' => 5000, 'X-RateLimit-Remaining' => 4999];
        });
    }

    public function setRoutes()
    {
        // test the extendables... $this->foo()
        $this->route('/test_extend', function() {
            $this->get(function($request, $response, $params) {
                $array = array_merge($this->foo(), $request->getHeaders());
                $response->write(200, $array);

                return $response;
            })->auth(\Dioxide\AUTH_BASIC);   // require basic auth to use this method
        });


        // send the request headers back to the user
        $this->route('/headers', function() {
            $this->get(function($request, $response, $params) {
                $rateHeaders = $this->emit('rate.limit.headers', [$request->getRealRemoteAddr()]);

                $response->write(200, $request->getHeaders());  // send the request headers back
                $response->headers($rateHeaders);

                return $response;
            });
        });

        $this->route('/post_test', function() {
            $this->post(function($request, $response, $params) {
                $response->write(200, $request->getContent());
                return $response;
            });
        });

        $this->route('/server_vars', function() {
            $this->get(function($request, $response, $params) {
                $response->write(200, $_SERVER);
                return $response;
            });
        });

        $this->route('/cache_test', function() {
            $this->get(function($request, $response, $params) {
                $response->write(200, ['foo' => 'bar']); // will be a 304 with no response body if cached
                return $response;
            });
        });

    }

    // testing hierarchy
    public function testParamRoutes()
    {
        // first param is required, second is optional
        //  - second will only be set if it exists
        $this->route('/params/:test1/?:test2', function() {
            $this->get(function($request, $response, $params) {
                $response->write(200, (array)$params);
                return $response;
            });
        })->namespaces(['v1']);  // only available on the /v1 namespace
    }
}

$api = new TestApi(['v1','v2']);
$api->run();

?>