<?php
namespace app\admin\services\addaccountrequest;

use think\facade\Db;

class AccountrequestProposal
{

    protected object $model;
    protected object $auth;

    public function __construct($auth=null)
    {
        $this->model = new \app\admin\model\addaccountrequest\AccountrequestProposal();
        $this->auth = $auth ?? new \stdClass();
    }

    public function getSerialName($params)
    {
        $params['allocate_time'] = date('md',time());

        $admin = DB::table('ba_admin')->field('nickname,is_name,is_name_key')->where('id',$params['admin_id'])->find();

        $adminKey = json_decode($admin['is_name_key'],true);
        
        $key = [];
        
        $account = DB::table('ba_account')->field('name,admin_id,time_zone,currency')->where('account_id',$params['account_id'])->find();
        if($admin['is_name'] == 2) return $account['name']??'';

        if(empty($account) || empty($admin)) throw new \Exception("未找到账户与渠道");

        //渠道 + 序号 + 币种 + 时区 + 时间[分配生成] + 申请用户[分配生成] + 用户自定义
        $accountAdminNickname = DB::table('ba_admin')->where('id',$account['admin_id'])->value('nickname');
        $key[1] = $admin['nickname'];
        $key[2] = str_pad($params['serial_number']??0, 4, '0', STR_PAD_LEFT);

        if((empty($params['currency']) || $params['currency'] == '其他') && $account['currency']) $params['currency'] = $account['currency'];
        $key[3] = $params['currency'];


        if(empty($params['time_zone'])  && !empty($account['time_zone'])) $params['time_zone'] = $account['time_zone'];
        if(!empty($params['time_zone'])) $key[4] = $this->extractTime($params['time_zone']);
        
        if(!empty($params['allocate_time'])) $key[5] = $params['allocate_time'];
        if(!empty($accountAdminNickname)) $key[6] = $accountAdminNickname;
        if(!empty($account['name'])) $key[7] = $account['name'];

        $value = '';
        foreach($adminKey as $v){
            if($v == 4 && !empty($key[$v])){
                $value = substr($value,0,-1);
                $value .= $key[$v].'-';
                continue;
            }
            if(!empty($key[$v])) $value .= $key[$v].'-';
        }

        return substr($value,0,-1);
        
    }

    function extractTime($timezone) {
        if($timezone == 'GMT 0:00') return '-0';
        preg_match('/([+-]\d+)(?::(\d+))?/', $timezone, $matches);
        return isset($matches[2]) && $matches[2] !== '00' ? $matches[1] . ':' . $matches[2] : $matches[1];
    }
    

}