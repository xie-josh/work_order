<?php

namespace app\admin\model\demand;

use app\admin\model\addaccountrequest\AccountrequestProposal;
use app\admin\model\Admin;
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

    protected $append = ['uuid'];
    protected $ud  = 'BM';

    public function accountrequestProposal()
    {
        return $this->hasOne(AccountrequestProposal::class,'account_id','account_id');
    }


    // public function accountrequestProposalAdmin()
    // {
    //     return $this->hasOneThrough(Admin::class, AccountrequestProposal::class, 'id', 'account_id', 'admin_id', 'account_id');
    // }

    public function getUuidAttr($value,$data)
    {
        return $this->ud.str_pad($data['id'], 6, '0', STR_PAD_LEFT);
    }

    public function admin(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\Admin::class, 'admin_id', 'id');
    }

    
    public function companyAccount()
    {
        return $this->hasOne(\app\admin\model\Account::class,'account_id','account_id');
    }

    public function teamAccount()
    {
        return $this->hasOne(\app\admin\model\user\AccountTeam::class,'account_id','account_id');
    }
}