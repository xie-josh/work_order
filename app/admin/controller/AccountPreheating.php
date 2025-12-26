<?php

namespace app\admin\controller;

use app\admin\model\card\CardsModel;
use think\facade\Cache;
use Throwable;
use app\common\controller\Backend;
use think\facade\Db;
use app\services\CardService;
use app\common\service\QYWXService;
use think\facade\Queue;

/**
 * 账户管理
 */
class AccountPreheating extends Backend
{

    protected object $model;

    protected array|string $preExcludeFields = ['id', 'account_id', 'admin_id', 'create_time', 'update_time'];

    protected array $withJoinTable = ['admin'];
    protected array $noNeedPermission = ['index'];
    protected string|array $quickSearchField = ['id'];

    // protected bool|string|int $dataLimit = 'parent';

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\Account();
    }

    /**
     * 查看
     * @throws Throwable
     */
    public function index(): void
    {
        //$this->quickSearchField = 'account_id';
        // 如果是 select 则转发到 select 方法，若未重写该方法，其实还是继续执行 index
        if ($this->request->param('select')) {
            $this->select();
        }

        $status = $this->request->get('status');
        $conserve = $this->request->get('conserve');
        $isConserve = $this->request->get('is_conserve');

        list($where, $alias, $limit, $order) = $this->queryBuilder();
        $whereOr = [];

        array_push($this->withJoinTable,'accountrequestProposal');

        $adminChannel = Db::table('ba_admin')->column('nickname','id');
        foreach($where as $k => &$v){
            if($v[0] == 'accountrequestProposal.admin_id' && $v[1] == 'LIKE'){
                $v[1] = '=';
                $v[2] = array_flip($adminChannel)[substr($v[2], 1, -1)]??'';
                continue;
                // unset($where[$k]);
            }
            if($v[0] == 'account.id' && $v[1] == 'IN'){
                foreach($v[2] as &$item){
                    if (preg_match('/\d+/', $item, $matches)) {
                        $number = ltrim($matches[0], '0'); // 移除开头的零
                        $item = $number;
                    } else {
                        //$v[2] = $number;
                    }
                    continue;
                }
                continue;
            }
            if($v[0] == 'account.id'){
                if (preg_match('/\d+/', $v[2], $matches)) {
                    $number = ltrim($matches[0], '0'); // 移除开头的零
                    $v[2] = '%'.$number.'%';
                } else {
                    //$v[2] = $number;
                }
                continue;
            }
            if($v[0] == 'account.uuid'){
                if (preg_match('/\d+/', $v[2], $matches)) {
                    $number = ltrim($matches[0], '0'); // 移除开头的零
                    $v[0] = 'account.id';
                    $v[2] = $number;
                } else {
                    //$v[2] = $number;
                }
                continue;
            }
            if($v[0] == 'account.time_zone'){
                $whereOr[] = ['account.time_zone',$v[1],$v[2]];
                $whereOr[] = ['accountrequestProposal.time_zone',$v[1],$v[2]];
                unset($where[$k]);
                continue;
            }
            if($v[0] == 'account.account_is_keep'){
                switch ($v[2]) {
                    case '1':
                        array_push($where,['account.keep_succeed','=',1]);
                        // array_push($where,['account.is_keep','=',1]);
                        break;
                    case '2':
                        array_push($where,['account.keep_succeed','<>',1]);
                        array_push($where,['account.status','IN',[4,6]]);
                        // array_push($where,['account.is_keep','=',1]);
                        // array_push($where,['account.keep_succeed','=',0]);
                        break;            
                    case '3':                        
                        array_push($where,['account.keep_succeed','<>',1]);
                        array_push($where,['account.status','IN',[0,1,3]]);
                        // array_push($where,['account.is_keep','=',1]);
                        // array_push($where,['account.keep_succeed','=',1]);
                        break;       
                }

                unset($where[$k]);
                continue;
            }
        }
        if($status == 1){
            array_push($where,['account.status','in',[1,3,4,5,6]]);
        }elseif($status == 3){
            array_push($where,['account.status','in',[4]]);
        }
        $where[] = ['account.status','in',[1,3,4,6]];
        $where[] = ['account.is_keep','=',1];
        // $where[] = ['account.keep_succeed','<>',1];
        

        $res = $this->model
            ->withJoin($this->withJoinTable, $this->withJoinType)
            ->alias($alias)
            ->where($where)
            ->where(function($query) use($whereOr){
                $query->whereOr($whereOr);
            })
            ->order('id','desc')//->find(); dd($order$this->withJoinTable, $this->withJoinType,$this->model->getLastSql());
            ->paginate($limit);

        // dd($this->withJoinTable);
        $dataList = $res->toArray()['data'];
        if($dataList){           

            $cardsIds = array_filter(array_map(function($dataList) {
                return $dataList['accountrequestProposal']['cards_id'] ?? null;
            }, $dataList));

            $cardNo = DB::table('ba_cards_info')->whereIn('cards_id',$cardsIds)->column('card_no','cards_id');
            $cardNo = array_map(function($cardNo) {
                return substr($cardNo, -4);
            }, $cardNo);

            $resultTypeList = DB::table('ba_account_type')->select()->toArray();
            $typeList = array_column($resultTypeList,'name','id');

            $bmList = [];
            if($status == 3){
                $accountIds = array_column($dataList,'account_id');
                $resultBm = DB::table('ba_bm')->where('status',1)
                ->whereIn('account_id',$accountIds)
                ->whereIn('demand_type',[1,4])
                ->where('dispose_type',1)
                ->where('new_status',1)
                ->select()->toArray();
                foreach($resultBm as $v){
                    $bmList[$v['account_id']][] = $v['bm'];
                }
            }

            $companyAdminNameArr = DB::table('ba_admin')->field('company_id,nickname,id')->where('type',2)->select()->toArray();
            $companyAdminNameArr = array_column($companyAdminNameArr,null,'company_id');
            $adminNameArr = DB::table('ba_admin')->field('nickname,id')->select()->toArray();
            $adminNameArr = array_column($adminNameArr,'nickname','id');

            foreach($dataList as &$v){
                // $spendCap = $v['accountrequestProposal']['spend_cap'];
                // $amountSpent = $v['accountrequestProposal']['amount_spent'];
                // $balance = bcsub((string)$spendCap,(string)$amountSpent,'2');

                if($v['keep_succeed'] == 1)
                {
                    $v['account_is_keep'] = 1;                
                }else{
                    if(in_array($v['status'],[4,6]))
                    {
                        $v['account_is_keep'] = 2;
                    }elseif(in_array($v['status'],[0,1,3]))
                    {
                        $v['account_is_keep'] = 3;
                    }
                }

                $openTime = $v['open_time']??'';
                $accountSpent2 = 0;
                if(!empty($openTime)){
                    $openAccountTime = date('Y-m-d',$openTime);
                    $consumptionWhere = [
                        ['account_id','=',$v['account_id']],
                        ['date_start','>=',$openAccountTime]
                    ];
                    $accountSpent2 = DB::table('ba_account_consumption')->where($consumptionWhere)->sum('spend');
                }

                // $v['fb_balance'] = $balance;
                $v['fb_spand'] = bcadd( (string)$accountSpent2,'0',2);

                $companyId = $v['company_id'];
                $nickname = '';
                if($v['admin_id'] == $companyAdminNameArr[$companyId]['id']) $nickname = $companyAdminNameArr[$companyId]['nickname'];
                else $nickname = $companyAdminNameArr[$companyId]['nickname']."(".$adminNameArr[$v['admin_id']].")";

                $v['account_type_name'] = '';
                if($v['status'] != 4 && $status != 1) $v['account_id'] = '';
                if(!empty($typeList[$v['account_type']])) $v['account_type_name'] = $typeList[$v['account_type']];
                $v['bm_list'] = $bmList[$v['account_id']]??[];
                if(empty($v['bes'])){
                    $bes =[];
                    if(!empty($v['email'])) $bes[]=$v['email'];
                    if(!empty($v['bm'])) $bes[]=$v['bm'];                    
                    $v['bes'] = $bes;
                }else{
                    $v['bes'] = json_decode($v['bes']??'',true);
                }
                
                if($conserve && $v['is_keep'] == 1 && $v['keep_succeed'] != 1 && $v['status'] == 4){
                    //养护未完成账户id不显示&&状态是3分配账户
                    $v['account_id'] = '';
                    $v['status'] = 3;
                }
                $v['admin'] = [
                    'username'=>$v['admin']['username']??"",
                    // 'nickname'=>$v['admin']['nickname']??""
                    'nickname'=>$nickname
                ];
                if(isset($v['accountrequestProposal']['admin_id']) && $adminChannel[$v['accountrequestProposal']['admin_id']]){
                    $v['channelName'] = $adminChannel[$v['accountrequestProposal']['admin_id']];
                }
                $v['card_no_c'] = '';
                if(!empty($v['accountrequestProposal']['cards_id'])) $v['card_no_c'] = $cardNo[$v['accountrequestProposal']['cards_id']]??'';
            }
        }
        //$res->visible(['admin' => ['username']]);

        $this->success('', [
            'list'   => $dataList,
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }


   

    /**
     * 若需重写查看、编辑、删除等方法，请复制 @see \app\admin\library\traits\Backend 中对应的方法至此进行重写
     */
}