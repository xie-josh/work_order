<?php

namespace app\admin\model\demand;

use think\Model;
use think\facade\Db;

/**
 * BmTemplate
 */
class BmTemplate extends Model
{
    // 表名
    protected $name = 'bm_template';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;


    public function getTextAttr($value): string
    {
        return !$value ? '' : htmlspecialchars_decode($value);
    }

    public function getTemplateValue($value,$accountId)
    {
        $accountrequestProposal = DB::table('ba_accountrequest_proposal')->field('bm,account_id')->where('account_id',$accountId)->find();
        $data = [
            '{BM}'=>$accountrequestProposal['bm']??'{BM}'
        ];
        $result = str_replace(array_keys($data), array_values($data), $value);
        return $result;
    }
}