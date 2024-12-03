<?php
namespace app\admin\services\addaccountrequest;

class AccounttkrequestProposal
{

    protected object $model;
    protected object $auth;

    public function __construct($auth=null)
    {
        $this->model = new \app\admin\model\addaccountrequest\AccounttkrequestProposal();
        $this->auth = $auth;
    }

    function getModel($modelValue)
    {
        switch ($modelValue) {
            case 'CardsInfoModel':
                return new \app\admin\model\card\CardsInfoModel();
            case 'CardsModel':
                return new \app\admin\model\card\CardsModel();
            default:
                # code...
                break;
        }
    }

    function getServices($serviceValue)
    {
        switch ($serviceValue) {
            case '1':
                return '';
            default:
                # code...
                break;
        }
    }

    public function distribution($params)
    {
        try {
            $id = $params['id'];
            $status = $params['status'];
            $cardNo = $params['card_no'];
            $timeZone = $params['time_zone'];
            $cardStatus = $params['card_status']??0;
            $accountStatus = $params['account_status']??0;
            $cardLimitedStatus = $params['card_limited_status']??0;

            $cardsInfoModel = $this->getModel('CardsInfoModel');
            $accountrequestProposal = $cardsInfoModel->where('id',$id)->find();
            if(empty($accountrequestProposal) || !empty($accountrequestProposal['cards_id'])) throw new \Exception('错误：未找到账户或已经分配了卡！'); 

            $cards = $cardsInfoModel->where('card_no',$cardNo)->where('is_use',0)->find();
            if(empty($cards)) throw new \Exception('错误：[未找到卡]或[卡已经被使用]或[卡不可使用]！');
            $cardsId = $cards['cards_id'];

            $param = [];
            $param['card_id'] = $cards['card_id'];
            $param['nickname'] = $this->getNickname($accountrequestProposal['account_id']);
            if($cardLimitedStatus == 1){
                $param['max_on_percent'] = env('CARD.MAX_ON_PERCENT',901);
                $param['transaction_limit_type'] = 'limited';
                $param['transaction_limit_change_type'] = 'increase';
                $param['transaction_limit'] = env('CARD.LIMIT_AMOUNT',2);
                $param['transaction_is'] = 1;
            }
            
            $proposalData = [
                // 'status'=>$accountStatus,
                'time_zone'=>$timeZone,
            ];

            if(!empty($accountStatus)) $proposalData['status'] = $accountStatus;
            
            if($status == 1){
                $cardsInfo = $cardsInfoModel->where('cards_id',$cards['cards_id'])->where('is_use',0)->update(['is_use'=>1]);

                if(!$cardsInfo) throw new \Exception("请刷新,卡已经被占用！");

                $resultCards = $this->getModel('CardsModel')->updateCard($cards,$param);

                if($resultCards['code'] != 1) throw new \Exception($resultCards['msg']);
                $proposalData['cards_id'] = $cardsId;
            }else if($status == 2){
                if($cardStatus == 1){
                    $cardsInfo = $cardsInfoModel->where('cards_id',$cards['cards_id'])->where('is_use',0)->update(['is_use'=>1]);

                    if(!$cardsInfo) throw new \Exception("请刷新,卡已经被占用！");

                    $resultCards = $this->getModel('CardsModel')->updateCard($cards,$param);

                    if($resultCards['code'] != 1) throw new \Exception($resultCards['msg']);
                    
                    $proposalData['cards_id'] = $cardsId;
                }
            }        
            $this->model->where('id',$id)->update($proposalData);
            $result = true;
        } catch (\Throwable $th) {
            throw $th;
        }

        return $result;
    }

    public function inDistribution($params)
    {
        try {
            $id = $params['id'];
            $cardNo = $params['card_no'];                
            $limited = $params['limited'];
            
            if(empty($id) || empty($cardNo) || empty($limited)) throw new \Exception('Params Required');

            $accountrequestProposal = $this->model->where('id',$id)->find();
            if(empty($accountrequestProposal)) throw new \Exception('错误：未找到账户！'); 

            $cardsInfoModel = $this->getModel('CardsInfoModel');

            $cards = $cardsInfoModel->where('card_no',$cardNo)->where('is_use',0)->find();
            if(empty($cards)) throw new \Exception('错误：[未找到卡]或[卡已经被使用]或[卡不可使用]！');

            $cardsId = $cards['cards_id'];

            $param = [];
            $param['card_id'] = $cards['card_id'];
            $param['nickname'] = $this->getNickname($accountrequestProposal['account_id']);
            $param['max_on_percent'] = env('CARD.MAX_ON_PERCENT',901);
            $param['transaction_limit_type'] = 'limited';
            $param['transaction_limit_change_type'] = 'increase';
            $param['transaction_limit'] = $limited;
            $param['transaction_is'] = 1;
            
            $proposalData = [];

            $cardsInfo = $cardsInfoModel->where('cards_id',$cards['cards_id'])->where('is_use',0)->update(['is_use'=>1]);

            if(!$cardsInfo) throw new \Exception("请刷新,卡已经被占用！");

            $resultCards = $this->getModel('CardsModel')->updateCard($cards,$param);

            if($resultCards['code'] != 1) throw new \Exception($resultCards['msg']);
            $proposalData['cards_id'] = $cardsId;
            
            $this->model->where('id',$id)->update($proposalData);

            $result = true;
        } catch (\Throwable $th) {
            throw $th;
        }
        return $result;
    }


    function getNickname($nickname)
    {
        $nickname = (string)$nickname;
        if(in_array($nickname[0],[1,4]) && strlen($nickname) >= 16) $nickname = substr($nickname,0,15);
        return $nickname;
    }

}