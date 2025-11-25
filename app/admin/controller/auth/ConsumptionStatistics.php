<?php

namespace app\admin\controller\auth;


use app\common\controller\Backend;
use think\facade\Db;
use think\facade\Cache;

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
        $this->model = new \app\admin\model\user\Company();
    }

    public function index(): void 
    {
        if ($this->request->param('select')) {
            $this->select();
        }

        list($where, $alias, $limit, $order) = $this->queryBuilder();

        array_push($where,['status','=',1]);
        $time2 = [];
        foreach($where as $k => &$v){
            if($v[0] == 'company.time2'){
                $time2 = [date('Y-m-d',$v[2][0]),date('Y-m-d',$v[2][1])];
                unset($where[$k]);
                continue;
            } 
            if($v[0] == 'company.nickname'){
                $companyIds = DB::table('ba_admin')->where('nickname','like','%'.$v[2].'%')->column('company_id');
                array_push($where,['id','IN',$companyIds]);
                unset($where[$k]);
                continue;
            }
        }

        $time = $this->request->get('time');
        if(empty($time)) $this->error('请选择时间');
        $month = date('Y-m',strtotime($time));

        $adminList = Db::table('ba_admin')->where('type',2)->field('nickname,company_id')->select()->toArray();
        $adminList = array_column($adminList,'nickname','company_id');

        // dd($adminList);

        $res = $this->model
            ->field('id,company_name,money')
            ->where($where)
            ->order($order)
            ->paginate($limit)->appends([]);
        $dataList = [];
        if($res) {
            $companyMoneyList = DB::table('ba_admin_money_log')->field('company_id,sum(money) money')->group('company_id')->select()->toArray();
            $companyMoneyList = array_column($companyMoneyList,'money','company_id');

            $consumptionWhere = [];
            if(!empty($time2))
            {
                $consumptionWhere[] = ['date_start','>=',$time2[0]];
                $consumptionWhere[] = ['date_start','<=',$time2[1]];
            }else{
                $consumptionWhere[] = ['date_start','>=',$month.'-01'];
                $consumptionWhere[] = ['date_start','<=',$time];
            }

            $companyConsumptionList = DB::table('ba_account_consumption')->field('company_id,sum(dollar) dollar')
            ->where($consumptionWhere)
            ->group('company_id')
            ->select()->toArray();
            $companyConsumptionList = array_column($companyConsumptionList,'dollar','company_id');

            $companyTotalConsumptionList = DB::table('ba_account_consumption')->field('company_id,sum(dollar) dollar')->group('company_id')->select()->toArray();
            $companyTotalConsumptionList = array_column($companyTotalConsumptionList,'dollar','company_id');
            
            foreach($res->toArray()['data'] ?? [] as $v)
            {   
                $money = $companyMoneyList[$v['id']] ?? 0;
                $companyConsumption = $companyConsumptionList[$v['id']] ?? 0;
                $companyTotalConsumption = $companyTotalConsumptionList[$v['id']] ?? 0;
                $money = bcadd((string)$money,'0',2);
                $dataList[] = [
                    'id' => $v['id'],
                    'nickname' => $adminList[$v['id']]??'',
                    'money' => $money,
                    'consumption' => bcadd((string)$companyConsumption,'0',2),
                    'remaining_amount' => bcsub($money,(string)$companyTotalConsumption,'2'),
                    'company_name' => $v['company_name']
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
            ['status','=',1]
        ];
        $adminCount = DB::table('ba_company')->where($where)->count();
        $adminIds = DB::table('ba_company')->where($where)->column('id');

        $where = [
            ['date','=',$time],
            ['company_id','<>',''],
            ['company_id','in',$adminIds],
        ];
        $settlementCount = DB::table('ba_settlement')->where($where)->count();

        $SETTLEMENT_DAYS = config('basics.SETTLEMENT_DAYS');
        $settlementSummaryCount = count($SETTLEMENT_DAYS);

        $where = [
            ['date','=',$time],
            ['company_id','null','NULL'],
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