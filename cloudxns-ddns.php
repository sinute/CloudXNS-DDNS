<?php

/**
 * 获取当前ip
 *
 * @author Sinute
 * @date   2016-05-14
 * @return string/false
 */
function getIPAddress()
{
    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL            => 'http://myip.ipip.net/',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 3,
        CURLOPT_USERAGENT      => 'curl/7.38.0',
    ]);

    $response = curl_exec($curl);
    $err      = curl_error($curl);

    curl_close($curl);

    if (!$err && preg_match('~^当前 IP：(?<ip>[0-9.]*).*$~', $response, $match)) {
        return $match['ip'];
    }
    return false;
}

/**
 * CloudXNS SDK
 */
class CloudXNS
{
    private $apiKey;
    private $secretKey;
    private $url = 'https://www.cloudxns.net/api2/';

    public function __construct($apiKey, $secretKey)
    {
        $this->apiKey    = $apiKey;
        $this->secretKey = $secretKey;
    }

    protected function send($url, $method = 'GET', $params = [])
    {
        $method = strtoupper($method);

        $curl = curl_init();

        $url = $this->url . $url;
        if ($method == 'GET') {
            if ($params) {
                $url .= '?' . http_build_query($params);
            }
        } else {
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($params));
        }

        $date    = date('r');
        $apiHMAC = md5($this->apiKey . $url . (in_array($method, ['POST', 'PUT']) && $params ? json_encode($params) : '') . $date . $this->secretKey);

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 5);
        curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            "API-KEY: {$this->apiKey}",
            "API-REQUEST-DATE: {$date}",
            "API-HMAC: {$apiHMAC}",
            'API-FORMAT: json',
        ]);

        $response = curl_exec($curl);
        $err      = curl_error($curl);

        curl_close($curl);

        if ($err) {
            throw new Exception($err);
        } else {
            return json_decode($response, true);
        }
    }

    /**
     * 获取解析记录列表
     *
     * @author Sinute
     * @date   2016-05-14
     * @param  integer    $domainID 域名ID
     * @param  integer    $hostID   主机记录ID(传 0 查全部)
     * @param  integer    $offset   记录开始的偏移,第一条记录为 0,依次类推,默认取 0
     * @param  integer    $rowNum   要获取的记录的数量,比如获取 30 条,则为 30,最大可取 2000 条,默认取 30 条
     * @return array
     */
    public function getDomainRecord($domainID, $hostID, $offset = 0, $rowNum = 30)
    {
        return $this->send("record/{$domainID}", 'GET', [
            'host_id' => $hostID,
            'offset'  => $offset,
            'row_num' => $rowNum,
        ]);
    }

    /**
     * 获取主机记录列表
     *
     * @author Sinute
     * @date   2016-05-14
     * @param  integer    $domainID 域名ID
     * @param  integer    $offset   记录开始的偏移,第一条记录为 0,依次类推
     * @param  integer    $rowNum   要获取的记录的数量,比如获取 30 条,则为 30,最大可取 2000 条
     * @return array
     */
    public function getDomainHost($domainID, $offset = 0, $rowNum = 30)
    {
        return $this->send("host/{$domainID}", 'GET', [
            'offset'  => $offset,
            'row_num' => $rowNum,
        ]);
    }

    /**
     * 域名列表
     *
     * @author Sinute
     * @date   2016-05-14
     * @param  integer    $offset   记录开始的偏移,第一条记录为 0,依次类推
     * @param  integer    $rowNum   要获取的记录的数量,比如获取 30 条,则为 30,最大可取 2000 条
     * @return array
     */
    public function getDomain($offset = 0, $rowNum = 30)
    {
        return $this->send('domain', 'GET', [
            'offset'  => $offset,
            'row_num' => $rowNum,
        ]);
    }

    /**
     * 更新解析记录
     *
     * @author Sinute
     * @date   2016-05-14
     * @param  integer    $recordID 记录ID
     * @param  integer    $domainID 域名ID
     * @param  string     $host     主机记录名,传空值,则主机记录名作”@”处理
     * @param  string     $value    记录值, 如 IP:8.8.8.8, CNAME:cname.cloudxns.net., MX: mail.cloudxns.net
     * @param  integer    $mx       优先级,当记录类型是 MX/AX/CNAMEX 时有效并且必选
     * @param  integer    $ttl      TTL,范围 1-3600,不同等级域名最小值不同
     * @param  string     $type     记录类型 如 A AX CNAME
     * @param  integer    $lineID   线路ID(通过 API 获取)
     * @param  string     $bakIP    存在备 ip 时可选填
     * @return array
     */
    public function updateRecord(
        $recordID,
        $domainID,
        $host,
        $value = null,
        $mx = null,
        $ttl = null,
        $type = null,
        $lineID = null,
        $bakIP = null
    ) {
        $params = array_filter(
            [
                'domain_id' => $domainID,
                'host'      => $host,
                'value'     => $value,
                'mx'        => $mx,
                'ttl'       => $ttl,
                'type'      => $type,
                'line_id'   => $lineID,
                'bak_ip'    => $bakIP,
            ],
            function ($value) {
                return $value !== null;
            }
        );
        return $this->send("record/{$recordID}", 'PUT', $params);
    }

    /**
     * 获取域名ID
     *
     * @author Sinute
     * @date   2016-05-14
     * @param  string     $domain 域名
     * @return integer/false
     */
    public function getDomainID($domain)
    {
        $domains = $this->getDomain(0, 50);
        if (isset($domains['message']) && $domains['message'] == 'success') {
            foreach ($domains['data'] as $data) {
                if ($domain == substr($data['domain'], 0, -1)) {
                    return $data['id'];
                }
            }
        }
        return false;
    }

    /**
     * 获取主机ID
     *
     * @author Sinute
     * @date   2016-05-14
     * @param  integer    $domainID 域名ID
     * @param  string     $host     主机
     * @return integer/false
     */
    public function getHostID($domainID, $host)
    {
        $hosts = $this->getDomainHost($domainID, 0, 50);
        if (isset($hosts['message']) && $hosts['message'] == 'success') {
            foreach ($hosts['hosts'] as $data) {
                if ($host == $data['host']) {
                    return $data['id'];
                }
            }
        }
        return false;
    }
}

function main($apiKey, $secretKey, $domain, $host)
{
    $cloudXNS = new CloudXNS($apiKey, $secretKey);

    $domainID = $cloudXNS->getDomainID($domain);
    if (!$domainID) {
        echo "DOMAIN NOT FOUND\n";
        return;
    }
    $hostID = $cloudXNS->getHostID($domainID, $host);
    if (!$hostID) {
        echo "HOST NOT FOUND\n";
        return;
    }
    $IP = getIPAddress();
    $IPRecord     = '';
    $recordID     = '';
    $domainRecord = $cloudXNS->getDomainRecord($domainID, $hostID);
    if (isset($domainRecord['message']) && $domainRecord['message'] == 'success') {
        $domainRecord = $domainRecord['data'][0];
        $IPRecord     = $domainRecord['value'];
        $recordID     = $domainRecord['record_id'];
    } else {
        echo "RECORD NOT FOUND\n";
        return;
    }
    echo "domain id : {$domainID}\n";
    echo "host id   : {$hostID}\n";
    echo "record id : {$recordID}\n";
    echo "curr ip   : {$IP}\n";
    echo "last ip   : {$IPRecord}\n";
    if ($IP && $IPRecord && $IP != $IPRecord) {
        $result = $cloudXNS->updateRecord(
            $recordID,
            $domainID,
            $domainRecord['host'],
            $IP
        );
        echo $result['message'] . "\n";
    }
}

$apiKey    = ''; // api key
$secretKey = ''; // secret key
$domain    = ''; // domain
$host      = ''; // host
main($apiKey, $secretKey, $domain, $host);
