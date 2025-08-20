<?php
declare (strict_types=1);

namespace app\admin\controller;

use app\common\service\QYWXService;
use app\services\CardService;
use Throwable;
use ba\ClickCaptcha;
use think\facade\Config;
use think\facade\Validate;
use app\common\facade\Token;
use app\admin\model\AdminLog;
use app\common\controller\Backend;
use app\common\service\GoogleService;
use app\services\EmailService;
use think\facade\Db;
use Google\Client;
use Google;
use ba\Random;
use Google\Service\Sheets;
use GuzzleHttp\Client as GuzzleHttpClient;
use think\facade\Log;
use think\Request;
use think\facade\Queue;
use think\facade\Cache;
use app\admin\model\Admin;
set_time_limit(3600);

//require_once __DIR__.'../vendor/autoload.php';

class Index extends Backend
{
    protected array $noNeedLogin      = ['logout', 'login','sendEmailCode','sendRegsterEmailCode','register'];
    protected array $noNeedPermission = ['index'];



    public function test()
    {


        $result = Db::table('ba_test')->select()->toArray();

        foreach($result as $v){
            Db::table('ba_accountrequest_proposal')->where('account_id',$v['acc_id'])->update(['affiliation_bm'=>$v['text']]);
        }

        dd(1);


    }

    /**
     * 后台初始化请求
     * @return void
     * @throws Throwable
     */
    public function index(): void
    {
        $adminInfo          = $this->auth->getInfo();
        $adminInfo['super'] = $this->auth->isSuperAdmin();
        unset($adminInfo['token'], $adminInfo['refresh_token']);

        $menus = $this->auth->getMenus();
        if (!$menus) {
            $this->error(__('No background menu, please contact super administrator!'));
        }
        $this->success('', [
            'adminInfo'  => $adminInfo,
            'menus'      => $menus,
            'siteConfig' => [
                'siteName' => get_sys_config('site_name'),
                'version'  => get_sys_config('version'),
                'cdnUrl'   => full_url(),
                'apiUrl'   => Config::get('buildadmin.api_url'),
                'upload'   => get_upload_config(),
            ],
            'terminal'   => [
                'installServicePort' => Config::get('terminal.install_service_port'),
                'npmPackageManager'  => Config::get('terminal.npm_package_manager'),
            ]
        ]);
    }

    /**
     * 管理员登录
     * @return void
     * @throws Throwable
     */
    public function login(): void
    {
        // 检查登录态
        if ($this->auth->isLogin()) {
            $this->success(__('You have already logged in. There is no need to log in again~'), [
                'type' => $this->auth::LOGGED_IN
            ], $this->auth::LOGIN_RESPONSE_CODE);
        }

        $captchaSwitch = Config::get('buildadmin.admin_login_captcha');

        // 检查提交
        if ($this->request->isPost()) {
            $username = $this->request->post('username');
            $password = $this->request->post('password')??'';
            $keep     = $this->request->post('keep');
            $code     = $this->request->post('code');

            $rule = [
                'username|' . __('Username') => 'require|length:3,50',
            ];
            $data = [
                'username' => $username,
            ];
            if(!empty($code)){
                $rule['code|' . __('Code')] = 'length:6|number';
                $data['code'] = $code;
            }else{
                $rule['password|' . __('Password')] = 'require|regex:^(?!.*[&<>"\'\n\r]).{6,32}$';
                $data['password'] = $password;
            }
            if ($captchaSwitch) {
                $rule['captchaId|' . __('CaptchaId')] = 'require';
                $rule['captchaInfo|' . __('Captcha')] = 'require';

                $data['captchaId']   = $this->request->post('captchaId');
                $data['captchaInfo'] = $this->request->post('captchaInfo');
            }
            $validate = Validate::rule($rule);
            if (!$validate->check($data)) {
                $this->error($validate->getError());
            }

            if ($captchaSwitch) {
                $captchaObj = new ClickCaptcha();
                if (!$captchaObj->check($data['captchaId'], $data['captchaInfo'])) {
                    $this->error(__('Captcha error'));
                }
            }

            AdminLog::instance()->setTitle(__('Login'));

            $res = $this->auth->login($username, $password, $code,(bool)$keep);
            if ($res === true) {
                $this->success(__('Login succeeded!'), [
                    'userInfo' => $this->auth->getInfo()
                ]);
            } else {
                $msg = $this->auth->getError();
                $msg = $msg ?: __('Incorrect user name or password!');
                $this->error($msg);
            }
        }

        $this->success('', [
            'captcha' => $captchaSwitch
        ]);
    }

    /**
     * 管理员注销
     * @return void
     */
    public function logout(): void
    {
        if ($this->request->isPost()) {
            $refreshToken = $this->request->post('refreshToken', '');
            if ($refreshToken) Token::delete((string)$refreshToken);
            $this->auth->logout();
            $this->success();
        }
    }

    public function sendEmailCode()
    {
        // 检查提交
        if ($this->request->isPost()) {
            $username = $this->request->post('username');

            $rule = [
                'username|' . __('Email') => 'require|length:3,50|email',
            ];
            $data = [
                'username' => $username,
            ];

            $validate = Validate::rule($rule);
            if (!$validate->check($data)) {
                $this->error($validate->getError());
            }

            $admin = Admin::where('email', $username)->find();
            if (!$admin) {
                $this->error('未找到该用户!');
                return false;
            }
            $res = (new EmailService())->sendEmail($username);

            if (isset($res['code']) && $res['code'] == 1) {
                (new \app\services\RedisLock())->set('sendEmailCode_'.$admin->id, (string)$res['data']['code'], 300);
                $this->success(__('Send email code succeeded!'), []);
            } else {
                $msg = $this->auth->getError();
                $msg = $msg ?: __('Send email error!');
                $this->error($msg);
            }

        }
        $this->success('', []);
    }

    public function sendRegsterEmailCode()
    {
        // 检查提交
        if ($this->request->isPost()) {
            $username = $this->request->post('username');

            $rule = [
                'username|' . __('Email') => 'require|length:3,50|email',
            ];
            $data = [
                'username' => $username,
            ];

            $validate = Validate::rule($rule);
            if (!$validate->check($data)) {
                $this->error($validate->getError());
            }

            $res = (new EmailService())->sendEmail($username);

            if (isset($res['code']) && $res['code'] == 1) {
                (new \app\services\RedisLock())->set('sendEmailCode_'.$username, (string)$res['data']['code'], 300);
                $this->success(__('Send email code succeeded!'), []);
            } else {
                $msg = $this->auth->getError();
                $msg = $msg ?: __('Send email error!');
                $this->error($msg);
            }

        }
        $this->success('', []);
    }

    /**
     * 注册
     */
    public function register()
    {   
        $info     = $this->request->post();
        $company  =  $info['company']??''; 
        $email    =  $info['email']??''; 
        $password =  $info['password']??''; 
        $confirmPassword =  $info['confirm_password']??''; 
        $code =  $info['code']??''; 
        $register = [];
        if(empty($company)){
                $this->error('请填写您的公司名称！');
        }
        if(!empty($email)){
            if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
                $this->error('输入的邮箱格式不正确！');
            }
        }else{
                $this->error('请填写您的联系邮箱！');
        }
        if(empty($password) || empty($confirmPassword)){
                $this->error('请两次输入注册密码,并且保持一致！');
        }
        if($password != $confirmPassword){
                $this->error('您输入的两次密码不一致！');
        }
        $register['email']   =  $email;
        $register['username']=  $email;
        $register['nickname']=  $email;
        $register['company'] =  $company;
        $salt = Random::build('alnum', 16);
        $register['salt']     = $salt;
        $register['password'] = encrypt_password($password, $salt);
        $isEmail = DB::table('ba_admin')->where('email',$email)->find();
        if(!empty($isEmail)){
            $this->error('邮箱验已存在！');
        }
        if(empty($code))
        {
            $this->error('您输入邮箱验证码！');
        }else{
            $redisLock = new \app\services\RedisLock();
            $redisCode = $redisLock->get('sendEmailCode_'.$email);
            if($redisCode != $code)
            {
                $this->error('邮箱验证码不正确！');
            }
            $redisLock->delete('sendEmailCode_'.$email);
        }
       
        $register['status'] = 2;
        $register['create_time'] = time();
        $id = DB::table('ba_admin')->insertGetId($register);
       
        $groupAccess = [
            'uid'      => $id,
            'group_id' => 3,   //默认用户
        ];
        Db::name('admin_group_access')->insert($groupAccess); 

        if($id){
            $this->success('', []);
        }else{
            $this->error('注册失败！请联系管理员！');
        }
    }
}
