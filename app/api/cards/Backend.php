<?php
namespace app\api\cards;

use GuzzleHttp\Client;

class Backend
{
    public function curlHttp(string $url,string $method='GET',array $header = ['Accept' => 'application/json'],array $data=[])
    {
        try {
            //code...
            $client = new Client(['verify' => false,'headers'=>$header]);
            if($method == 'GET' || $method == 'get'){
                $response = $client->request('GET', $url,['query'=>$data]);
            }elseif($method == 'GET2'){
                $response = $client->request('GET', $url,['form_params'=>$data]);
            }elseif($method == 'POST'){
                $response = $client->request('POST', $url,['body'=>json_encode($data)]);
            }elseif($method == 'POST1'){
                $response = $client->request('POST', $url);
            }elseif($method == 'POST2'){
                $response = $client->request('POST', $url,['form_params'=>$data]);
            }elseif($method == 'PUT'){
                $response = $client->request('PUT', $url,['form_params'=>$data]);
            }elseif($method == 'DELETE'){
                $response = $client->request('DELETE', $url,['body'=>json_encode($data)]);
            }elseif($method == 'PATCH'){
                $response = $client->request('PATCH', $url,['body'=>json_encode($data)]);
            }
            $result = $response->getBody()->getContents();

            $result = json_decode($result, true);
            return $result;

        } catch (\Throwable $th) {
            // dd($th->getResponse()->getBody()->getContents());
            //throw $th;
            return throw new \Exception($th->getMessage());
        }
    }


    public function getUUID()
    {
        return sprintf(
            '%04x%04x%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000
        ) . substr(md5(uniqid(mt_rand(), true)), 0, 12);
    }


    public function returnError(String $msg='error',Array $data = [])
    {
        return [
            'code'=>0,
            'msg'=>$msg,
            'data'=>$data
        ];
    }
     public function returnSucceed(Array $data = [])
    {
        return [
            'code'=>1,
            'msg'=>'succeed',
            'data'=>$data
        ];
    }
}