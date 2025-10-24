<?php

namespace app\admin\model\recycle;

use think\Model;

/**
 * RechargeRecycleModel
 */
class RechargeRecycleModel extends Model
{
    // 表名
    protected $name = 'recharge_recycle';
    protected $append = ['uuid','currency_number'];
    protected $currencyRate = ["EUR"=>"0.8","ARS"=>"940","PEN"=>"3.6","IDR"=>"16000","VND"=>"23500","GBP"=>"0.7"];
    protected $ud  = 'CZ';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;

    public function admin(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\Admin::class, 'admin_id', 'id');
    }

    public function getUuidAttr($value,$data)
    {
        return $data['id'];
    }

    public function getCurrencyNumberAttr($value,$data){
        $currency = $this->accountrequestProposal()->where('account_id',$data['account_id'])->value('currency');
        $currencyNumber =  '';
        $currencyRate = json_decode(env('APP.currency'),true);
        if(!empty($currencyRate[$currency])){
            $currencyNumber = bcmul((string)$data['number'], $currencyRate[$currency],2);
        }else{
            $currencyNumber = (string)$data['number'];
        }
        return $currencyNumber;
    }
    public function accountrequestProposal(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\addaccountrequest\AccountrequestProposal::class, 'account_id', 'account_id');
    }
}