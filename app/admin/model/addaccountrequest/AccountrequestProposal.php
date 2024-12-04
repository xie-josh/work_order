<?php

namespace app\admin\model\addaccountrequest;

use think\Model;
use think\facade\Db;

/**
 * AccountrequestProposal
 */
class AccountrequestProposal extends Model
{
    // 表名
    protected $name = 'accountrequest_proposal';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;

    protected $append = ['serial_name'];


    public function admin(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\Admin::class, 'admin_id', 'id');
    }

    public function affiliationAdmin(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\Admin::class, 'affiliation_admin_id', 'id');
    }

    public function cards(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\card\CardsInfoModel::class, 'cards_id', 'cards_id');
    }

    public function getSerialNameAttr($value,$data)
    {
        $admin = DB::table('ba_admin')->field('nickname,is_name,is_name_key')->where('id',$data['admin_id'])->find();

        $adminKey = json_decode($admin['is_name_key'],true);
        
        $key = [];
        
        $account = DB::table('ba_account')->field('name,admin_id')->where('account_id',$data['account_id'])->find();
        if($admin['is_name'] == 2) return $account['name']??'';

        if($data['status'] != 0 && !empty($account)){
            //渠道 + 序号 + 币种 + 时区 + 时间[分配生成] + 申请用户[分配生成] + 用户自定义
            $accountAdminNickname = DB::table('ba_admin')->where('id',$account['admin_id'])->value('nickname');
            $key[1] = $admin['nickname'];
            $key[2] = str_pad($data['serial_number']??0, 4, '0', STR_PAD_LEFT);
            $key[3] = $data['currency'];

            if(!empty($data['time_zone'])){
                // $timezone = $data['time_zone'];
                // preg_match('/[+-]?\d+/', $timezone, $matches);
                // $timezone = $matches[0]??'';
                // $key[4] = $timezone;
                $key[4] = $this->extractTime($data['time_zone']);
            }

            if(!empty($data['allocate_time'])) $key[5] = $data['allocate_time'];
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
        }else{
            return '';
        }
    }

    function extractTime($timezone) {
        if($timezone == 'GMT 0:00') return '-0';
        preg_match('/([+-]\d+)(?::(\d+))?/', $timezone, $matches);
        return isset($matches[2]) && $matches[2] !== '00' ? $matches[1] . ':' . $matches[2] : $matches[1];
    }
    
    public function getSerialNameAttr2222($value,$data)
    {
        $admin = DB::table('ba_admin')->field('nickname,is_name')->where('id',$data['admin_id'])->find();

        $key = [1,2,3,4,5,6,7];
        
        $account = DB::table('ba_account')->field('name,admin_id')->where('account_id',$data['account_id'])->find();
        if($admin['is_name'] == 2) return $account['name']??'';

        if($data['status'] != 0 && !empty($account)){
            //渠道 + 序号 + 币种 + 时区 + 时间[分配生成] + 申请用户[分配生成] + 用户自定义
            $accountAdminNickname = DB::table('ba_admin')->where('id',$account['admin_id'])->value('nickname');

            $value = $admin['nickname'].'-'.str_pad($data['serial_number']??0, 4, '0', STR_PAD_LEFT).($data['currency']?'-'.$data['currency']:'');
            if(!empty($data['time_zone'])){
                $timezone = $data['time_zone'];
                preg_match('/[+-]?\d+/', $timezone, $matches);
                $timezone = $matches[0]??'';
                $value .= $timezone;
            }

            if(!empty($data['allocate_time']))  $value .= ('-'.$data['allocate_time']);
            if(!empty($accountAdminNickname))  $value .= ('-'.$accountAdminNickname);
            if(!empty($account['name']))  $value .= ('-'.$account['name']).'';

            return $value;
        }else{
            return '';
        }
    }
}