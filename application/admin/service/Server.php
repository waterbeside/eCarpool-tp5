<?php

namespace app\admin\service;

use app\common\service\Service;
use my\RedisData;
use my\CertSerialUtil;

class Server extends Service
{



    //获取硬盘情况
    public function getDisk()
    {
        $d['t'] = round(@disk_total_space(".") / (1024 * 1024 * 1024), 3);
        $d['f'] = round(@disk_free_space(".") / (1024 * 1024 * 1024), 3);
        $d['u'] = $d['t'] - $d['f'];
        $d['PCT'] = (floatval($d['t']) != 0) ? round($d['u'] / $d['t'] * 100, 2) : 0;
        return $d;
    }

    public function GetCPUUse()
    {
        $data = @file('/proc/stat');
        $cores = array();
        foreach ($data as $line) {
            if (preg_match('/^cpu[0-9]/', $line)) {
                $info = explode(' ', $line);
                $cores[] = array('user' => $info[1], 'nice' => $info[2], 'sys' => $info[3], 'idle' => $info[4], 'iowait' => $info[5], 'irq' => $info[6], 'softirq' => $info[7]);
            }
        }
        return $cores;
    }

    public function getCPUPercent($CPU1, $CPU2)
    {
        $num = count($CPU1);
        if ($num !== count($CPU2)) {
            return;
        }
        $cpus = array();
        for ($i = 0; $i < $num; $i++) {
            $dif = array();
            $dif['user']    = $CPU2[$i]['user'] - $CPU1[$i]['user'];
            $dif['nice']    = $CPU2[$i]['nice'] - $CPU1[$i]['nice'];
            $dif['sys']     = $CPU2[$i]['sys'] - $CPU1[$i]['sys'];
            $dif['idle']    = $CPU2[$i]['idle'] - $CPU1[$i]['idle'];
            $dif['iowait']  = $CPU2[$i]['iowait'] - $CPU1[$i]['iowait'];
            $dif['irq']     = $CPU2[$i]['irq'] - $CPU1[$i]['irq'];
            $dif['softirq'] = $CPU2[$i]['softirq'] - $CPU1[$i]['softirq'];
            $total = array_sum($dif);
            $cpu = array();
            foreach ($dif as $x => $y) {
                $cpu[$x] = round($y / $total * 100, 2);
            }
            $cpus['cpu_' . $i] = $cpu;
        }
        return $cpus;
    }

    public function getMemory()
    {
        //内存
        $str = @file("/proc/meminfo");
        if (!$str) {
            return false;
        }
        $str = implode("", $str);
        preg_match_all("/MemTotal\s{0,}\:+\s{0,}([\d\.]+).+?MemFree\s{0,}\:+\s{0,}([\d\.]+).+?Cached\s{0,}\:+\s{0,}([\d\.]+).+?SwapTotal\s{0,}\:+\s{0,}([\d\.]+).+?SwapFree\s{0,}\:+\s{0,}([\d\.]+)/s", $str, $buf);
        preg_match_all("/Buffers\s{0,}\:+\s{0,}([\d\.]+)/s", $str, $buffers);
        $resmem['memTotal'] = round($buf[1][0] / 1024, 2);
        $resmem['memFree'] = round($buf[2][0] / 1024, 2);
        $resmem['memBuffers'] = round($buffers[1][0] / 1024, 2);
        $resmem['memCached'] = round($buf[3][0] / 1024, 2);
        $resmem['memUsed'] = $resmem['memTotal'] - $resmem['memFree'];
        $resmem['memPercent'] = (floatval($resmem['memTotal']) != 0) ? round($resmem['memUsed'] / $resmem['memTotal'] * 100, 2) : 0;
        $resmem['memRealUsed'] = $resmem['memTotal'] - $resmem['memFree'] - $resmem['memCached'] - $resmem['memBuffers']; //真实内存使用
        $resmem['memRealFree'] = $resmem['memTotal'] - $resmem['memRealUsed']; //真实空闲
        $resmem['memRealPercent'] = (floatval($resmem['memTotal']) != 0) ? round($resmem['memRealUsed'] / $resmem['memTotal'] * 100, 2) : 0; //真实内存使用率
        $resmem['memCachedPercent'] = (floatval($resmem['memCached']) != 0) ? round($resmem['memCached'] / $resmem['memTotal'] * 100, 2) : 0; //Cached内存使用率
        $resmem['swapTotal'] = round($buf[4][0] / 1024, 2);
        $resmem['swapFree'] = round($buf[5][0] / 1024, 2);
        $resmem['swapUsed'] = round($resmem['swapTotal'] - $resmem['swapFree'], 2);
        $resmem['swapPercent'] = (floatval($resmem['swapTotal']) != 0) ? round($resmem['swapUsed'] / $resmem['swapTotal'] * 100, 2) : 0;
        // $resmem = $this->formatmem($resmem); //格式化内存显示单位
        return $resmem;
    }

    //格试化内存显示单位
    private function formatmem($mem)
    {
        if (!is_array($mem)) {
            return $mem;
        }
        $tmp = array(
            'memTotal', 'memUsed', 'memFree', 'memPercent',
            'memCached', 'memRealPercent',
            'swapTotal', 'swapUsed', 'swapFree', 'swapPercent'
        );
        foreach ($mem as $k => $v) {
            if (!strpos($k, 'Percent')) {
                // $v = $v < 1024 ? $v . ' M' : $v . ' G';
            }
            $mem[$k] = $v;
        }
        foreach ($tmp as $v) {
            $mem[$v] = $mem[$v] ? $mem[$v] : 0;
        }
        return $mem;
    }

    /**
    * 获取证书有效期
    * @param String $domain 要查询的域名；
    */
    public function getCertInfo($domain)
    {
        $context = stream_context_create(['ssl' => [
            'capture_peer_cert' => true,
            'capture_peer_cert_chain' => false,
            ],
        ]);
        try {
            $client = stream_socket_client("ssl://".$domain.":443", $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context);
            if ($client==false) {
                return false;
            }

            $params = stream_context_get_params($client);
            // var_dump($params['options']['ssl']['peer_certificate']);
            // $cert_info = CertSerialUtil::getSerial($params, $errMsg);

            $cert = $params['options']['ssl']['peer_certificate'];
            // var_dump($cert);
            $cert_info = openssl_x509_parse($cert);
            openssl_x509_free($cert);
        } catch (\Exception $e) {
            return false;
        }
        return $cert_info;
    }
}
