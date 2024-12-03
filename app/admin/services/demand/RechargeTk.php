<?php

namespace app\admin\services\demand;

class RechargeTk
{

    protected object $model;
    protected object $auth;

    public function __construct($auth=null)
    {
        $this->model = new \app\admin\model\demand\RechargeTkModel();
        $this->auth = $auth;
    }

    public function audit(array $data): bool
    {
        $ids = $data['ids'] ?? [];
        $status = $data['status'] ?? null;
        try {
            $rechargeResult = $this->model->whereIn('id',$ids)->where('status',0)->select()->toArray();
            foreach($rechargeResult as $value){
                if($status == 1){
                    switch ($value['type']) {
                        case 1:
                            $this->accountAuditRecharge($value);                        
                            break;
                        case 2:
                            $this->accountAuditDeduct($value);
                            break;                        
                        case 3:
                            $this->accountAuditBlockClear($value);
                            break;
                        case 4:
                            $this->activeAuditClear($value);
                            break;
                        default:
                            break;
                    }
                    $data = [
                        'status'=>1,
                        'operate_admin_id'=>$this->auth->id
                    ];
                    $this->model->where('id',$value['id'])->update($data);
                }else{
                    $data = [
                        'status'=>2,
                        'operate_admin_id'=>$this->auth->id
                    ];
                    $this->model->where('id',$value['id'])->update($data);
                }
            }
            return true;
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    /**
     * 充值
     * @param mixed $params 充值需求Value
     * @return void
     */
    public function accountAuditRecharge($params)
    {
        //充值 = 1
        //throw new \Exception("Error Processing Request", 1);
    }

    /**
     * 扣款
     * @param mixed $params 扣款需求Value
     * @return void
     */
    public function accountAuditDeduct($params)
    {
    }

    /**
     * 封户清零
     * @param mixed $params 清零需求Value
     * @return void
     */
    public function accountAuditBlockClear($params)
    {
    }

    /**
     * 活跃清零
     * @param mixed $params 活跃需求Value
     * @return void
     */
    public function activeAuditClear($params)
    {
    }


    

}