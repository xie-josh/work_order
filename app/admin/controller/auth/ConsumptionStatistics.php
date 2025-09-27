<?php

namespace app\admin\controller\auth;


use app\common\controller\Backend;
use think\facade\Db;
use think\facade\Cache;
use app\admin\model\Admin as AdminModel;

class ConsumptionStatistics extends Backend
{
    protected object $model;

    protected array $withJoinTable = [];

    // 排除字段
    protected string|array $preExcludeFields = [];

    protected string|array $quickSearchField = [];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new AdminModel();
    }

    public function index(): void 
    {
        if ($this->request->param('select')) {
            $this->select();
        }

        list($where, $alias, $limit, $order) = $this->queryBuilder();

        array_push($where,['admin_group_access.group_id','in',[3]]);
        array_push($where,['admin.status','=',1]);

        $time = $this->request->get('time');
        if(empty($time)) $this->error('请选择时间');
        $month = date('Y-m',strtotime($time));

        $res = DB::table('ba_admin')
            ->alias('admin')
            ->field('admin.id,admin.nickname,admin.money,admin_group_access.group_id')
            ->leftJoin('ba_admin_group_access admin_group_access','admin_group_access.uid = admin.id')
            ->where($where)
            ->order($order)
            ->paginate($limit)->appends([]);
        $dataList = [];
        if($res) {
            $adminMoneyList = DB::table('ba_admin_money_log')->field('admin_id,sum(money) money')->group('admin_id')->select()->toArray();
            $adminMoneyList = array_column($adminMoneyList,'money','admin_id');

            $adminConsumptionList = DB::table('ba_account_consumption')->field('admin_id,sum(dollar) dollar')
            ->where([
                ['date_start','>=',$month.'-01'],
                ['date_start','<=',$time],
            ])
            ->group('admin_id')
            ->select()->toArray();
            $adminConsumptionList = array_column($adminConsumptionList,'dollar','admin_id');

            $adminTotalConsumptionList = DB::table('ba_account_consumption')->field('admin_id,sum(dollar) dollar')->group('admin_id')->select()->toArray();
            $adminTotalConsumptionList = array_column($adminTotalConsumptionList,'dollar','admin_id');
            
            foreach($res->toArray()['data'] ?? [] as $v)
            {   
                $money = $adminMoneyList[$v['id']] ?? 0;
                $adminConsumption = $adminConsumptionList[$v['id']] ?? 0;
                $adminTotalConsumption = $adminTotalConsumptionList[$v['id']] ?? 0;
                $money = bcadd((string)$money,'0',2);
                $dataList[] = [
                    'id' => $v['id'],
                    'nickname' => $v['nickname'],
                    'money' => $money,
                    'consumption' => bcadd((string)$adminConsumption,'0',2),
                    'remaining_amount' => bcsub($money,(string)$adminTotalConsumption,'2'),
                ];
            }
            
            $sort = array_column($dataList, 'remaining_amount'); // 提取某列
            array_multisort($sort, SORT_ASC, $dataList);
        }

        

        $this->success('', [
            'list'   => $dataList,
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }


    public function getSettlementExcelItem()
    {
     
        //用户消耗： 总用户数量  /  已保存消耗用户数量   汇中消耗： 汇总数量/完成汇总

        $time = date("Y-m-d");

        $where = [
            ['admin_group_access.group_id','in',[3]],
            ['admin.status','=',1]
        ];
        $adminCount = DB::table('ba_admin')->alias('admin')->leftJoin('ba_admin_group_access admin_group_access','admin_group_access.uid = admin.id')->where($where)->count();
        $adminIds = DB::table('ba_admin')->alias('admin')->leftJoin('ba_admin_group_access admin_group_access','admin_group_access.uid = admin.id')->where($where)->column('id');

        $where = [
            ['date','=',$time],
            ['admin_id','<>',''],
            ['admin_id','in',$adminIds],
        ];
        $settlementCount = DB::table('ba_settlement')->where($where)->count();

        $SETTLEMENT_DAYS = config('basics.SETTLEMENT_DAYS');
        $settlementSummaryCount = count($SETTLEMENT_DAYS);

        $where = [
            ['date','=',$time],
            ['admin_id','null','NULL'],
        ];
        $summaryCount = DB::table('ba_settlement')->where($where)->count();
        
        $dataList = [
            'admin_count'=>$adminCount,
            'settlement_count'=>$settlementCount,
            'settlement_summary_count'=>$settlementSummaryCount,
            'summary_count'=>$summaryCount,
        ];
        $this->success('', $dataList);
    }


    public function downloadZip()
    {
        $month = date('Ym');
        $days = date('d');

        $pathExcel = "storage/excel/{$month}/settlement{$days}";
        // $dirname = (new \app\services\Basics())->getDirname();
        $path = public_path().$pathExcel;

        if(!is_dir($path)) $this->error('文件不存在！');

        $date = date('Ymd');
        $name = "/settlement-{$date}.zip";
        $folderPath = $path;
        $zipPath = $path . $name;
        $resultPath = $pathExcel . $name;

        if(file_exists($resultPath)) unlink($resultPath);

        // 创建 ZipArchive 对象
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== TRUE) {
            $this->error("无法创建 zip 文件: $zipPath");
        }

        // 递归遍历文件夹
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($folderPath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($files as $file) {
            $filePath = $file->getRealPath();
            // zip 内的相对路径
            $relativePath = substr($filePath, strlen($folderPath) + 1);

            if ($file->isDir()) {
                $zip->addEmptyDir($relativePath);
            } else {
                $zip->addFile($filePath, $relativePath);
            }
        }

        $zip->close();

        $this->success('',$resultPath);

        // echo "压缩完成：$resultPath";
    }

}