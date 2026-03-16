<?php
namespace app\admin\services;

use app\services\FacebookService;
use think\facade\Db;
use Goletter\Akms\Client as AkmsClient;
use Goletter\Akms\DashboardApi;
use Goletter\Akms\ApplicationApi;

class TkService
{


   public function ApplicationApi($params=[])
   {
        $client = new AkmsClient('eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJ1c2VyX2lkIjo3NzA2LCJleHAiOjE4MDQzMjI3OTIsInJvbGVfaWQiOiIzIn0.7wa6Wf3ugJQHCYIo4hvyFm7QPerToR5UEha2IfgUxQU');
        return new ApplicationApi($client);
   }

}