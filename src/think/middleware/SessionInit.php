<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2019 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace think\middleware;

use Closure;
use think\App;
use think\Request;
use think\Response;
use think\Session;

/**
 * Session初始化
 */
class SessionInit
{

    /**
     * Session初始化
     * @access public
     * @param Request $request
     * @param Closure $next
     * @param App     $app
     * @param Session $session
     * @return Response
     */
    public function handle($request, Closure $next, App $app, Session $session)
    {
        // Session初始化
        $varSessionId = $app->config->get('session.var_session_id');
        $cookieName   = $session->getName();

        if ($varSessionId && $request->request($varSessionId)) {
            $sessionId = $request->request($varSessionId);
        } else {
            $sessionId = $request->cookie($cookieName);
        }

        $session->setId($sessionId);
        $session->init();

        $request->withSession($session);

        /** @var Response $response */
        $response = $next($request);

        $response->setSession($session);

        $app->cookie->set($cookieName, $session->getId());

        $session->save();

        return $response;
    }
}
