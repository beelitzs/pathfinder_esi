<?php

/**
 * Created by PhpStorm.
 * User: Exodus4D
 * Date: 27.03.2017
 * Time: 16:06
 */

namespace Exodus4D\ESI\Lib;

class WebClient extends \Web {

    const ERROR_STATUS_LOG                      = 'HTTP %s: \'%s\' | url: %s \'%s\'%s';
    const ERROR_RESOURCE_LEGACY                 = 'Resource: %s has been marked as legacy.';
    const ERROR_RESOURCE_DEPRECATED             = 'Resource: %s has been marked as deprecated.';

    /**
     * end of line
     * @var string
     */
    private $eol = "\r\n";

    /**
     * @param array $headers
     * @return int
     */
    protected function getStatusCodeFromHeaders($headers = []){
        $statusCode = 0;

        if(
        preg_match(
            '/HTTP\/1\.\d (\d{3}?)/',
            implode($this->eol, (array)$headers),
            $matches
        )
        ){
            $statusCode = (int)$matches[1];
        }
        return $statusCode;
    }

    /**
     * get HTTP status type from HTTP status code (e.g. 404 )> 'err_client')
     * @param int $statusCode
     * @return string
     */
    protected function getStatusType(int $statusCode): string{
        $typeLevel = (int)substr($statusCode, 0, 1);
        switch($typeLevel){
            case 1:
                $statusType = 'info';
                break;
            case 2:
                $statusType = 'ok';
                break;
            case 3:
                $statusType = 'redirect';
                break;
            case 4:
                $statusType = 'err_client';
                break;
            case 5:
                $statusType = 'err_server';
                break;
            default:
                $statusType = 'unknown';
        }

        return $statusType;
    }

    /**
     * @param int $code
     * @param string $method
     * @param string $url
     * @param null $responseBody
     * @return string
     */
    protected function getErrorMessageFromJsonResponse(int $code, string $method, string $url, $responseBody = null):string {
        $message = empty($responseBody->message) ?  @constant('Base::HTTP_' . $code) : $responseBody->message;
        $body = !is_null($responseBody) ? ' | body: ' . print_r($responseBody, true) : '';

        return sprintf(self::ERROR_STATUS_LOG, $code, $message, $method, $url, $body);
    }

    /**
     * get Logger obj for given status type
     * @param string $statusType
     * @return \Log
     */
    protected function getLogger(string $statusType): \Log{
        switch($statusType){
            case 'err_client':
                $logfile = 'esi.error.client';
                break;
            case 'resource_legacy':
                $logfile = 'esi.resource.legacy';
                break;
            case 'resource_deprecated':
                $logfile = 'esi.resource.deprecated';
                break;
            default:
                $logfile = 'esi.error.unknown';
        }
        return new \Log($logfile . '.log');
    }

    /**
     * check response headers for warnings/errors and log them
     * @param array $headers
     * @param string $url
     */
    protected function checkResponseHeaders(array $headers, string $url){
        $headers = (array)$headers;

        if( preg_grep('/^Warning: 199/i', $headers) ){
            $this->getLogger('resource_legacy')->write(sprintf(self::ERROR_RESOURCE_LEGACY, $url));
        }
        if( preg_grep('/^Warning: 299/i', $headers) ){
            $this->getLogger('resource_deprecated')->write(sprintf(self::ERROR_RESOURCE_DEPRECATED, $url));
        }
    }

    public function request($url,array $options = null){

        $response = parent::request($url, $options);

        // check response headers
        $this->checkResponseHeaders($response['headers'], $url);


        $statusCode = $this->getStatusCodeFromHeaders($response['headers']);
        $statusType = $this->getStatusType($statusCode);

        switch($statusType){
            case 'err_client':
                $errorMsg = $this->getErrorMessageFromJsonResponse(
                    $statusCode,
                    $options['method'],
                    $url,
                    json_decode($response['body'])
                );
                $this->getLogger($statusType)->write($errorMsg);
                break;
            default:
        }

        return $response;
    }
}