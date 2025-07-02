<?php

namespace app\admin\model;

use app\admin\model\addaccountrequest\AccountrequestProposal;
use think\Model;

/**
 * Account
 */
class Account extends Model
{
    // 表名
    protected $name = 'account';

    protected $append = ['uuid','currency_number','currency_open_money'];
    protected $currencyRate = ["EUR"=>"0.8","ARS"=>"940","PEN"=>"3.6","IDR"=>"16000","VND"=>"23500","GBP"=>"0.7"];

    protected $ud  = 'KH';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;

    public function getMoneyAttr($value): float
    {
        return (float)$value;
    }

    public function getCurrencyNumberAttr($value,$data){
        $currency = $this->accountrequestProposal()->where('account_id',$data['account_id'])->value('currency');
        $currencyNumber =  '';
        if(!empty($this->currencyRate[$currency])){
            $currencyNumber = bcmul((string)$data['money'], $this->currencyRate[$currency],2);
        }else{
            $currencyNumber = (string)$data['money'];
        }
        return $currencyNumber;
    }

    public function getCurrencyOpenMoneyAttr($value,$data){
        $currency = $this->accountrequestProposal()->where('account_id',$data['account_id'])->value('currency');
        $currencyNumber =  '';
        if(!empty($this->currencyRate[$currency])){
            $currencyNumber = bcmul((string)$data['open_money'], $this->currencyRate[$currency],2);
        }else{
            $currencyNumber = (string)$data['open_money'];
        }
        return $currencyNumber;
    }

    public function getUuidAttr($value,$data)
    {
        return $this->ud.str_pad($data['id'], 6, '0', STR_PAD_LEFT);
    }

    public function admin(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\Admin::class, 'admin_id', 'id');
    }

    public function accountrequestProposal()
    {
        return $this->hasOne(AccountrequestProposal::class,'account_id','account_id');
    }
}