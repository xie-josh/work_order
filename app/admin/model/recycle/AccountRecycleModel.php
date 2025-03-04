<?php

namespace app\admin\model\recycle;

use think\Model;
use app\admin\model\addaccountrequest\AccountrequestProposal;

/**
 * AccountRecycleModel
 */
class AccountRecycleModel extends Model
{
    // 表名
    protected $name = 'account_recycle';

    protected $append = ['uuid','currency_number','currency_open_money'];
    protected $currencyRate = ["EUR"=>"0.84","ARS"=>"940","PEN"=>"3.6","IDR"=>"16000"];

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;

    public function admin(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\Admin::class, 'admin_id', 'id');
    }
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
        return $data['id'];
    }


    public function accountrequestProposal()
    {
        return $this->hasOne(AccountrequestProposal::class,'account_id','account_id');
    }


}