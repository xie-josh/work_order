<?php

namespace app\admin\controller\auth;


use app\common\controller\Backend;
use think\facade\Db;
use app\admin\model\fb\ConsumptionModel;
use think\facade\Cache;


class ConsumptionOne extends Backend
{
    protected object $model;

    protected array $withJoinTable = [];

    // 排除字段
    protected string|array $preExcludeFields = [];

    protected string|array $quickSearchField = [];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new ConsumptionModel();
    }

    public function index(): void 
    {
        if ($this->request->param('select')) {
            $this->select();
        }

        list($where, $alias, $limit, $order) = $this->queryBuilder();

        $start = date('Y-m-d', strtotime('-1 month'));  

        $res   = Db::table('ba_account_consumption')
        ->field("date_start,sum(dollar) dollar")
        ->where('date_start','>', $start)
        ->group('date_start')
        ->order('date_start desc')
        ->paginate(12);//->select()->toArray();

        $dataList = [];
        if($res) {
            foreach($res->toArray()['data'] ?? [] as $v)
            {   
                $dataList[] = [
                    'date_start' => $v['date_start'],
                    'dollar' => number_format($v['dollar'], 2)
                ];
            }
        }

        $this->success('', [
            'list'   => $dataList,
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }

    public function getFbAccountConsumptionTaskCount()
    {
        $taskCount = Cache::store('redis')->handler()->llen('{queues:FbAccountConsumption}'); //5000;
        $comment = '';
        if($taskCount >0){
            $comment = "消耗查询任务正在执行中！( $taskCount )";
        }
        $this->success('', ['comment' => $comment]);
    }

    public function downloadZip()
    {
            $folderPath = __DIR__ . '/test';
            // 生成的 zip 文件路径
            $zipPath = __DIR__ . '/test.zip';

            // 创建 ZipArchive 对象
            $zip = new \ZipArchive();
            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== TRUE) {
                exit("无法创建 zip 文件: $zipPath");
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

            echo "压缩完成：$zipPath";
    }
}