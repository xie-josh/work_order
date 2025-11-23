<?php
namespace app\admin\middleware;

use think\Request;

class AdminRouteAuth
{
    public function handle(Request $request, \Closure $next)
    {


        
        $path = $request->pathinfo(); // 比如 admin/user.Team/index
        // dd($path,$this->au());
        $token = $request->header('Authorization') ?: $request->get('token');

        if (!$token) {
            return json(['error' => '缺少 token'], 401);
        }

        $payload = decode_token($token); // 自定义解析函数

        // 判断路径类型
        if (str_starts_with($path, 'admin/user.')) {
            // 用户接口
            if ($payload['type'] != 0 && $payload['type'] != 1) {
                return json(['error' => '无效用户'], 403);
            }
        } else {
            // 管理员接口
            if ($payload['type'] != 1) {
                return json(['error' => '仅管理员可访问'], 403);
            }
        }

        // 把用户信息注入
        $request->user = $payload;

        return $next($request);
    }
}
