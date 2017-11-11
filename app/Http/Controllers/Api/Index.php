<?php
/**
 * Created by PhpStorm.
 * User: MR.Z < zsh2088@gmail.com >
 * Date: 2017/9/18
 * Time: 21:07
 */
namespace App\Http\Controllers\Api;


use App\Http\Controllers\Api\Service\v1\ApiService;
use Illuminate\Http\Request;


class Index {

    public $api = NULL;

    public function __construct() {
        $this->api        = ApiService::instance();
        $this->api->debug = FALSE;
    }

    public function index(Request $request , $version , $directory , $action = 'index' ) {

        //取 http 头
        $header = [
            'timestamp'       => $request->header( 'timestamp' ) ,
            'signature'       => $request->header( 'signature' ) ,
            'device'          => $request->header( 'device' ) ,
            'deviceOsVersion' => $request->header( 'device-os-version' ) ,
            'appVersion'      => $request->header( 'app-version' ) ,
            'apiVersion'      => $version ,
        ];


        //取api
        $api = $this->api;
        $api->logStat( $header );
        $api->log( 'headerData' , $header );

        // 检查时间戳
        if ( ! $this->api->validTimestamp( $header['timestamp'] ) ) {
            exit( json( $api->getError( 405 ) )->send() );
        }
        $this->api->log( 'request ' , request()->method() );

        // 取参数
        $params = $request->all();
        $api->log( 'params' , $params );

        //取时间戳
        $params['timestamp'] = $header['timestamp'];

        //检查签名
        if ( ! $this->api->validSignature( $params , $header['signature'] ) ) {
            exit( json( $api->getError( 406 ) )->send() );
        }

        //合并参数
        $params = array_merge( $params , $header );
        $this->api->log( 'params' , $params );

        // 参数错误
        if ( ! is_array( $params ) || empty( $params ) ) {
            exit( json( $api->getError( 400 ) )->send() );
        }

        $result = $this->response( $version , $directory , $action , $params );
        $api->log( '请求结束' );

        return json( $result );
    }

    /**
     * 响应辅助函数
     *
     * @param $version
     * @param $directory
     * @param $action
     * @param $params
     *
     * @return array
     */
    private function response( $version , $directory , $action , $params ) {

        $action  = ucfirst( $action );
        $version = strtolower( $version );
        $class   = '\\App\\Http\\Controllers\\Api\\Service\\' . $version . '\\' . $directory . '\\' . $action . 'Service';
        $this->api->log( 'service file' , $class );

        //检查是否存在响应文件
        if ( ! class_exists( $class ) ) {
            return $this->api->getError( 404 );
        }

        //初始化响应类
        $instance = $class::instance( $params );
        //检查请求方式
        if ( ! $this->checkRequestMethod( $instance->allowRequestMethod ) ) {
            return $this->api->getError( 408 );
        }

        return $instance->response();
    }

    /**
     * 检查 请求方式是否允许
     *
     * @param array $allowRequestMethod
     *
     * @return bool
     */
    private function checkRequestMethod( $allowRequestMethod = [] ) {
        $requestMethod = strtolower( request()->method() );
        if ( empty( $allowRequestMethod ) ) {
            return FALSE;
        }

        return isset( $allowRequestMethod[ $requestMethod ] );
    }
}
