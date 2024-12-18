<?php

namespace app\admin\controller\fb;

use Throwable;
use ba\Random;
use app\common\controller\Backend;
use think\facade\Cache;

class Consumption extends Backend
{
    /**
     * @var object
     * @phpstan-var BmToken
     */
    protected object $model;

    protected array $withJoinTable = [];

    // 排除字段
    protected string|array $preExcludeFields = ['last_login_time', 'login_failure', 'password', 'salt'];

    protected string|array $quickSearchField = ['username', 'nickname', 'id'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\fb\ConsumptionModel();
    }


    public function export()
    {
        set_time_limit(300);

        $data = $this->request->post();

        $isCount = $data['is_count']??2;
        $startTime = $data['start_time']??'';
        $endTime = $data['end_time']??'';

        list($where, $alias, $limit, $order) = $this->queryBuilder();

        $batchSize = 2000;
        $processedCount = 0;
        $redisKey = 'export_consumpotion'.'_'.$this->auth->id;
        

        if(!empty($startTime) && !empty($endTime)){
            array_push($where,['date_start','>=',$startTime]);
            array_push($where,['date_stop','<=',$endTime]);
        }

        $query =  $this->model
        ->alias('account_consumption')        
        ->leftJoin('ba_accountrequest_proposal accountrequest_proposal','accountrequest_proposal.account_id=account_consumption.account_id')
        ->where($where);

        if($isCount == 1){
            $query->field('accountrequest_proposal.serial_name_2,min(account_consumption.date_start) date_start,max(account_consumption.date_stop) date_stop,accountrequest_proposal.account_id,accountrequest_proposal.bm,accountrequest_proposal.affiliation_bm,sum(account_consumption.spend) as spend');
            $query->group('account_id');
        }else{
            $query->field('accountrequest_proposal.serial_name_2,account_consumption.spend,account_consumption.date_start,account_consumption.date_stop,accountrequest_proposal.account_id,accountrequest_proposal.bm,accountrequest_proposal.affiliation_bm');
        }

        $total = $query->count(); 

        $folders = (new \app\common\service\Utils)->getExcelFolders();
        $header = [
            '管理BM',
            '归属BM',
            '账户名称',
            '账户ID',
            '消耗',
            '开始时间',
            '结束时间',
        ];

        $config = [
            'path' => $folders['path']
        ];
        $excel  = new \Vtiful\Kernel\Excel($config);

        $name = $folders['name'].'.xlsx';

        for ($offset = 0; $offset < $total; $offset += $batchSize) {
            $data = $query->limit($offset, $batchSize)->select()->append([])->toArray();
            $dataList=[];
            foreach($data as $v){
                $dataList[] = [
                    $v['bm'],
                    $v['affiliation_bm'],
                    $v['serial_name_2'],
                    $v['account_id'],
                    $v['spend'],
                    $v['date_start'],
                    $v['date_stop'],
                ];  
                $processedCount++;
            }
            $filePath = $excel->fileName($folders['name'].'.xlsx', 'sheet1')
            ->header($header)
            ->data($dataList);
            $progress = min(100, ceil($processedCount / $total * 100));
            Cache::store('redis')->set($redisKey, $progress, 300);
        }

        $excel->output();
        Cache::store('redis')->delete($redisKey);

        $this->success('',['path'=>$folders['filePath'].'/'.$name]);
    }


    public function getExportProgress()
    {
        $progress = Cache::store('redis')->get('export_consumpotion'.'_'.$this->auth->id, 0);
        return $this->success('',['progress' => $progress]);
    }

}