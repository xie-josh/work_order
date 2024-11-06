<?php

namespace app\admin\controller\card;
use think\cache\driver\Redis;

set_time_limit(600);
use app\common\controller\Backend;
use app\admin\model\card\CardsModel;
use think\facade\Db;
use app\services\CardService;
use think\facade\Cache;

class Cards extends Backend{
     /**
     * 模型
     * @var object
     * @phpstan-var CardsModel
     */
    protected object $model;

    protected array|string $preExcludeFields = ['create_time', 'update_time', 'password', 'salt', 'login_failure', 'last_login_time', 'last_login_ip'];

    protected array|string $quickSearchField = ['username', 'nickname'];
    protected array $noNeedPermission = ['index','channel','getCard','cardInfo','cardsTransactions','cardFreeze','updateCard'];

    protected string $dataLimitField = 'id';

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new CardsModel();
    }

    public function index(): void
    {
        if ($this->request->param('select')) {
            $this->select();
        }

        $this->withJoinTable = ['cardInfo'];

        list($where, $alias, $limit, $order) = $this->queryBuilder();
        $res = $this->model
            ->field($this->indexField)
            ->withJoin($this->withJoinTable, $this->withJoinType)
            ->alias($alias)
            ->where($where)
            ->order($order)
            ->paginate($limit);

        $res->visible(['cardInfo' => ['nickname','card_no','card_status']]);


        $this->success('', [
            'list'   => $res->items(),
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }


    public function cardInfo()
    {
        $data = $this->request->get();
        $id = $data['id'];
        
        $row = Db::table('ba_cards_info')->field('card_no,account_id,card_id')->where('cards_id',$id)->find();
        (new CardsModel())->updateCardsInfo($row);
        $this->success('', [
            'row' => $row
        ]);
    }

    public function cardsTransactions()
    {
        $data = $this->request->get();
        $id = $data['id'];
        $row = Db::table('ba_cards_transactions')->where('cards_id',$id)->limit(50)->order('created_at','desc')->select()->toArray();
        $this->success('', [
            'row' => $row
        ]);
    }


    public function cardFreeze()
    {
        $data = $this->request->post();
        $status = $data['status']; //freeze：冻结卡； unfreeze：解冻卡。
        $ids = $data['ids'];
        $error = [];
        
        $cards = Db::table('ba_cards_info')->whereIn('cards_id',$ids)->field('id,account_id,card_id,card_no')->select()->toArray();

        if(empty($cards)) $this->error('未找到卡！');

        $result = false;
        try {
            $l = 0;
            foreach($cards as $v){
                $l++;
                $accountId = $v['account_id'];
                $cardId = $v['card_id'];
                $cardNo = $v['card_no'];

                if($status =='freeze')
                {
                    $result = (new CardService($accountId))->cardFreeze(['card_id'=>$cardId]);
                }elseif($status == 'unfreeze'){
                    $result = (new CardService($accountId))->cardUnfreeze(['card_id'=>$cardId]);
                }
                if(isset($result['data']['cardStatus'])) DB::table('ba_cards_info')->where('id',$v['id'])->update(['card_status'=>$result['data']['cardStatus']]);
                if($result['code'] != 1) $error[] = ['card_no'=>$cardNo ,'msg'=>$result['msg']];
                //if($l % 3 == 0) sleep(1);
            }
            $result = true;
        } catch (\Throwable $th) {
            $this->error($th->getMessage());
        }

        if ($result !== false) {
            $this->success(__('Update successful'),['error'=>$error]);
        } else {
            $this->error(__('No rows updated'),['error'=>$error]);
        }
    }

    public function updateCard()
    {
        $data = $this->request->post();
        $ids = $data['ids'];
        $error = [];
        
        $cards = Db::table('ba_cards_info')->whereIn('cards_id',$ids)->field('account_id,card_id,card_no')->select()->toArray();

        $param = [];
       
        if(!empty($data['nickname'])) $param['nickname'] = $data['nickname'];
        if(!empty($data['max_on_daily'])) $param['max_on_daily'] = $data['max_on_daily'];
        if(!empty($data['max_on_monthly'])) $param['max_on_monthly'] = $data['max_on_monthly'];
        if(!empty($data['max_on_percent'])) $param['max_on_percent'] = $data['max_on_percent'];
        if(!empty($data['transaction_limit_type'])) $param['transaction_limit_type'] = $data['transaction_limit_type'];
        if(!empty($data['transaction_limit_change_type'])) $param['transaction_limit_change_type'] = $data['transaction_limit_change_type'];
        if(!empty($data['transaction_limit'])) $param['transaction_limit'] = $data['transaction_limit'];    
        if(!empty($data['transaction_limit_type']) && $data['transaction_limit_type'] == 'cover'){
            $param['transaction_is'] = 1;
        }else{
            $param['transaction_is'] = 0;
        }

        if(!empty($data['transaction_limit_type']) && $data['transaction_limit_type'] == 'cover' && empty($data['transaction_limit']))  $this->error('请填写覆盖金额！');

        $result = false;
        try {
            $l = 0;
            foreach($cards as $v){
                $l++;
                $accountId = $v['account_id'];
                $cardId = $v['card_id'];
                $cardNo = $v['card_no'];

                $param['card_id'] = $cardId;

                $result = (new CardService($accountId))->updateCard($param);
                if($result['code'] == 1){
                    $cardInfo = (new CardService($accountId))->cardInfo(['card_id'=>$cardId]);
                    $infoData = $cardInfo['data'];
                    if(!empty($param['nickname'])) DB::table('ba_cards_info')->where('card_id',$cardId)->update(['nickname'=>$infoData['nickname']]);
                    
                    if(!empty($param['transaction_limit_type']))
                    {
                        $data = [];
                        if(!empty($infoData['nickname'])) $data['nickname'] = $infoData['nickname'];
                        if(!empty($infoData['maxOnDaily'])) $data['max_on_daily'] = $infoData['maxOnDaily'];
                        if(!empty($infoData['maxOnMonthly'])) $data['max_on_monthly'] = $infoData['maxOnMonthly'];
                        if(!empty($infoData['maxOnPercent'])) $data['max_on_percent'] = $infoData['maxOnPercent'];
                        if(!empty($infoData['totalTransactionLimit'])) $data['total_transaction_limit'] = $infoData['totalTransactionLimit'];
                        if(!empty($infoData['transactionLimitType'])) $data['transaction_limit_type'] = $infoData['transactionLimitType'];
                        if(!empty($infoData['availableTransactionLimit'])) $data['available_transaction_limit'] = $infoData['availableTransactionLimit'];
                        
                        DB::table('ba_cards_info')->where('account_id',$accountId)->where('card_id',$cardId)->update($data);
                    }
                }else{
                    $error[] = ['card_no'=>$cardNo ,'msg'=>$result['msg']];
                }
                //if($l % 2 == 0) sleep(1);
            }

            $result = true;
        } catch (\Throwable $th) {
            $this->error($th->getMessage());
        }

        if ($result !== false) {
            $this->success(__('Update successful'),['error'=>$error]);
        } else {
            $this->error(__('No rows updated'),['error'=>$error]);
        }
    }



    public function getCard()
    {
        $randomNumber = mt_rand(1,1);
        $cardsInfo = DB::table('ba_card_account')
        ->alias('card_account')
        ->field('cards_info.cards_id,cards_info.card_no')
        ->where('card_account.card_platform_id',$randomNumber)
        ->leftJoin('ba_cards_info cards_info','cards_info.account_id=card_account.id')
        ->where('cards_info.is_use',0)
        ->where('cards_info.card_status','normal')
        ->order('cards_info.number','asc')
        ->find();
        DB::table('ba_cards_info')->where('cards_id',$cardsInfo['cards_id'])->inc('number',1)->update();

        if (!empty($cardsInfo)) {
            $this->success('',$cardsInfo);
        } else {
            $this->error('没有未使用的卡！');
        }
    }


    /**
     * 若需重写查看、编辑、删除等方法，请复制 @see \app\admin\library\traits\Backend 中对应的方法至此进行重写
     */

}