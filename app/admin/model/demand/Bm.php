<?php

namespace app\admin\model\demand;

use app\admin\model\addaccountrequest\AccountrequestProposal;
use think\Model;


/**
 * Bm
 */
class Bm extends Model
{
    // 表名
    protected $name = 'bm';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;



    public function accountrequestProposal()
    {
        return $this->hasOne(AccountrequestProposal::class,'account_id','account_id');
    }


}