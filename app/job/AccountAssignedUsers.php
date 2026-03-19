<?php

namespace app\job;

use think\queue\Job;
use app\services\CardService;
use think\facade\Db;

class AccountAssignedUsers
{
    public function fire(Job $job, $data)
    {
        //php think queue:listen --queue AccountAssignedUsers
        sleep(1);
        //：1=待授权，2=授权成功，3=授权失败，4=限额成功，5=限额失败

        try {
            $accountId = $data['account_id'];
            $bmTokenId = $data['bm_token_id']??0;
            $type = $data['type']??0;
            $accountPlatformId = $data['account_platform_id']??1;

            if($accountPlatformId == 1)
            {
                $params = DB::table('ba_fb_bm_token')->where('assigned_status',1)->where('id',$bmTokenId)->find();

                $FacebookService = new \app\services\FacebookService();

                if($params['is_token'] !=1) $params['token'] = (new \app\admin\services\fb\FbService())->getPersonalbmToken($params['token']);
                if(!empty($params) && !empty($params['user_id'])){
                    $params['account_id'] = $accountId;
                    $result = $FacebookService->assignedUsers($params);
                    if($type == 1 && $result['code'] == 1)
                    {                                    
                        sleep(4);
                        $params['spend'] = 0.01;
                        $result3 = $FacebookService->adAccountsLimit($params);
                        if($result3['code'] != 1)  DB::table('ba_fb_logs')->insert(['log_id'=>$accountId,'type'=>'FB_add_account_limit','data'=>json_encode($params),'logs'=>$result3['msg'],'create_time'=>date('Y-m-d H:i:s',time())]);
                        // if($result3['code'] == 1) DB::table('ba_accountrequest_proposal')->where('account_id',$accountId)->update(['authorization_status'=>4]);
                        // else DB::table('ba_accountrequest_proposal')->where('account_id',$accountId)->update(['authorization_status'=>5]);
                    }else{
                        DB::table('ba_fb_logs')->insert(['log_id'=>$accountId,'type'=>'FB_add_account_users','data'=>json_encode($params),'logs'=>$result['msg'],'create_time'=>date('Y-m-d H:i:s',time())]);
                        // DB::table('ba_accountrequest_proposal')->where('account_id',$accountId)->update(['authorization_status'=>3]);
                    }
                }
            }elseif($accountPlatformId == 2){
                $tkCurrencyValue = config('basics.TK_CURRENCY_VALUE');

                $appApi = (new \app\admin\services\TkService())->ApplicationApi([]);

                $result = $appApi->tiktokAccounts([
                    'account_id' => $accountId,
                ]);                

                $currency = $result['data']['list']['0']['currency']??0;
                $status = $result['data']['list']['0']['status']??0;
                
                $timezone = $result['data']['list']['0']['timezone']??'';
                if(!empty($timezone))
                {
                    preg_match('/\((UTC[^\)]+)\)/', $timezone, $match);
                    $timezone = str_replace('UTC', 'GMT ', $match[1]);
                }

                $params = [];
                $result = [];
                $data = [
                    'currency'=>$tkCurrencyValue[$currency]??'',
                    'time_zone'=>$timezone,
                    // 'account_status'=>$status,
                    'account_status'=>1,
                ];
                DB::table('ba_accountrequest_proposal')->where('account_id',$accountId)->update($data);

                $params = [
                    'bc_id' => '7504233300559872017',
                    'user_id' => '7604826193457021968',
                    'asset_type' => 'ADVERTISER',
                    'asset_id' => $accountId,
                    'advertiser_role' => 'ADMIN',
                    'catalog_role' => 'ADMIN',
                    'form_library_role' => 'ADMIN',
                    'tt_account_roles' => ['POST'],
                    'business_account_roles' => ['BUSINESS_ACCOUNT_ADMIN'],
                    'store_role' => 'AD_PROMOTION'
                ];

                $appApi = (new \app\admin\services\TkService())->TikTokBusiness([]);

                $result = $appApi->assignBcAsset($params);
            }
            
            $job->delete();
        } catch (\Throwable $th) {

            if ($job->attempts() >= 3) {
                $job->delete();
            }
        }
        if ($job->attempts() >= 3) {
            $job->delete();
        }
    }
}
