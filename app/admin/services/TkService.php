<?php
namespace app\admin\services;

use app\services\FacebookService;
use think\facade\Db;
use Goletter\Akms\Client as AkmsClient;
use Goletter\Akms\DashboardApi;
use Goletter\Akms\ApplicationApi;
use Goletter\Adv\Platforms\TikTok\TikTokAccount;
use Goletter\Adv\Platforms\TikTok\TikTokClient;
use Goletter\Adv\Platforms\TikTok\TikTokReport;
use Goletter\Adv\Platforms\TikTok\TikTokBusiness;

class TkService
{


   public function ApplicationApi($params=[])
   {
        $client = new AkmsClient('eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJ1c2VyX2lkIjo3NzA2LCJleHAiOjE4MDQzMjI3OTIsInJvbGVfaWQiOiIzIn0.7wa6Wf3ugJQHCYIo4hvyFm7QPerToR5UEha2IfgUxQU');
        return new ApplicationApi($client);
   }


   public function TikTokAccount($params=[])
   {
        $client = new TikTokClient('09a511c5bd8bd91182d5c0df32d2ad1f859710ac');
        return new TikTokAccount($client);        
   }

   public function TikTokReport($params=[])
   {
        $client = new TikTokClient('09a511c5bd8bd91182d5c0df32d2ad1f859710ac');
        return new TikTokReport($client);        
   }

   public function TikTokBusiness($params=[])
   {
        $client = new TikTokClient('09a511c5bd8bd91182d5c0df32d2ad1f859710ac');
        return new TikTokBusiness($client);        
   }

   




   

}