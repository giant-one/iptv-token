<?php

class SignatureValidator {
    private $secretKey;
    private $expireSeconds;

    public function __construct($secretKey = null, $expireSeconds = 86400) {
        $this->secretKey = $secretKey ?? (defined('SIGNATURE_SECRET_KEY') ? SIGNATURE_SECRET_KEY : 'efb61c6eee44c37dda4136850527cb74');
        $this->expireSeconds = $expireSeconds; // 默认24小时
    }

    /**
     * 生成签名和过期时间
     * @param string $token
     * @return array [exp: int, sign: string]
     */
    public function generateSignature($token) {
        //$exp = time() + $this->expireSeconds;
        $exp = $this->expireSeconds;
        // 构建待签名参数
        $params = [
            'token' => $token,
            'exp' => $exp
        ];

        // 按参数名排序
        ksort($params);

        // 构建字符串
        $queryString = http_build_query($params);
        $stringToSign = $queryString . $this->secretKey;

        // 计算SHA256签名
        $signature = substr(hash('sha256', $stringToSign), 0, 16);

        return [
            'exp' => $exp,
            'sign' => $signature
        ];
    }

    /**
     * 验证签名
     * @param array $queryParameters 请求参数
     * @return array [valid: bool, message: string]
     */
    public function validate($queryParameters) {
        // 检查必需参数
        if (!isset($queryParameters['token']) || !isset($queryParameters['exp']) || !isset($queryParameters['sign'])) {
            return ["valid" => false, "message" => "缺少必要参数: token, exp, sign"];
        }

        $token = $queryParameters['token'];
        $exp = $queryParameters['exp'];
        $signature = $queryParameters['sign'];

        // 验证token
        if (empty($token)) {
            return ["valid" => false, "message" => "token不能为空"];
        }

        // 验证过期时间
        if (!is_numeric($exp)) {
            return ["valid" => false, "message" => "exp必须是数字时间戳"];
        }

        $currentTimestamp = time();
        if ($exp < $currentTimestamp) {
            return ["valid" => false, "message" => "链接已过期"];
        }

        // 验证过期时间是否在合理范围内（不超过1天）
        if ($exp > $currentTimestamp + $this->expireSeconds) {
            return ["valid" => false, "message" => "链接过期时间过长"];
        }

        // 构建待签名字符串
        // 只包含token和exp参数，按参数名字母顺序排序
        $params = [];
        $params['token'] = $queryParameters['token'];
        $params['exp'] = $queryParameters['exp'];

        // 按参数名排序
        ksort($params);

        // 构建字符串
        $queryString = http_build_query($params);
        $stringToSign = $queryString . $this->secretKey;

        // 计算SHA256签名
        $expectedSignature = hash('sha256', $stringToSign);

        // 比较签名
        if (!hash_equals($expectedSignature, $signature)) {
            return ["valid" => false, "message" => "签名验证失败"];
        }

        return ["valid" => true, "message" => "验证通过"];
    }
}