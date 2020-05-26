<?php
//'===========性能分析生成文件==========='
if (!function_exists('tidewaysAfterRequest')) {
    include_once dirname(__DIR__) . '/vendor/autoload.php';
    function tidewaysAfterRequest()
    {
        $extension = Xhgui_Config::read('extension');
        if ($extension == 'uprofiler' && extension_loaded('uprofiler')) {
            $data['profile'] = uprofiler_disable();
        } else if ($extension == 'tideways_xhprof' && extension_loaded('tideways_xhprof')) {
            $data['profile'] = tideways_xhprof_disable();
        } else if ($extension == 'tideways' && extension_loaded('tideways')) {
            $data['profile'] = tideways_disable();
            $sqlData         = tideways_get_spans();
            $data['sql']     = array();
            if (isset($sqlData[1])) {
                foreach ($sqlData as $val) {
                    if (isset($val['n']) && $val['n'] === 'sql' && isset($val['a']) && isset($val['a']['sql'])) {
                        $_time_tmp = (isset($val['b'][0]) && isset($val['e'][0])) ? ($val['e'][0] - $val['b'][0]) : 0;
                        if (!empty($val['a']['sql'])) {
                            $data['sql'][] = array(
                                'time' => $_time_tmp,
                                'sql'  => $val['a']['sql']
                            );
                        }
                    }
                }
            }
        } else {
            $data['profile'] = xhprof_disable();
        }
        $profile = array();
        foreach ($data['profile'] as $key => $value) {
            $profile[strtr($key, array('.' => '_'))] = $value;
        }
        $data['profile'] = $profile;
        // ignore_user_abort(true) allows your PHP script to continue executing, even if the user has terminated their request.
        // Further Reading: http://blog.preinheimer.com/index.php?/archives/248-When-does-a-user-abort.html
        // flush() asks PHP to send any data remaining in the output buffers. This is normally done when the script completes, but
        // since we're delaying that a bit by dealing with the xhprof stuff, we'll do it now to avoid making the user wait.
        ignore_user_abort(true);
        flush();

        if (!defined('XHGUI_ROOT_DIR')) {
            require dirname(dirname(__FILE__)) . '/src/bootstrap.php';
        }

        $uri = array_key_exists('REQUEST_URI', $_SERVER)
            ? $_SERVER['REQUEST_URI']
            : null;
        if (empty($uri) && isset($_SERVER['argv'])) {
            $cmd = basename($_SERVER['argv'][0]);
            $uri = $cmd . ' ' . implode(' ', array_slice($_SERVER['argv'], 1));
        }

        $time             = array_key_exists('REQUEST_TIME', $_SERVER)
            ? $_SERVER['REQUEST_TIME']
            : time();
        $requestTimeFloat = explode('.', $_SERVER['REQUEST_TIME_FLOAT']);
        if (!isset($requestTimeFloat[1])) {
            $requestTimeFloat[1] = 0;
        }

        if (Xhgui_Config::read('save.handler') === 'file') {
            $requestTs      = array('sec' => $time, 'usec' => 0);
            $requestTsMicro = array('sec' => $requestTimeFloat[0], 'usec' => $requestTimeFloat[1]);
        } else {
            $requestTs      = new MongoDate($time);
            $requestTsMicro = new MongoDate($requestTimeFloat[0], $requestTimeFloat[1]);
        }

        $data['meta'] = array(
            'url'              => $uri,
            'SERVER'           => $_SERVER,
            'get'              => $_GET,
            'env'              => $_ENV,
            'simple_url'       => Xhgui_Util::simpleUrl($uri),
            'request_ts'       => $requestTs,
            'request_ts_micro' => $requestTsMicro,
            'request_date'     => date('Y-m-d', $time),
        );

        try {
            $config = Xhgui_Config::all();
            $config += array('db.options' => array());
            $saver = Xhgui_Saver::factory($config);
            $saver->save($data);
        } catch (Exception $e) {
            error_log('xhgui - ' . $e->getMessage());
        }
    }
}

tidewaysAfterRequest();
