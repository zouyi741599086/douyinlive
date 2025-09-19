<?php
namespace app;

class AcSignature
{
    public function cal_one_str($one_str, $orgi_iv)
    {
        $k = $orgi_iv;
        for ($i = 0; $i < strlen($one_str); $i++) {
            $a = ord($one_str[$i]);
            $k = (($k ^ $a) * 65599) & 0xFFFFFFFF;
        }
        return $k;
    }

    public function cal_one_str_2($one_str, $orgi_iv)
    {
        $k = $orgi_iv;
        $a = strlen($one_str);
        for ($i = 0; $i < 32; $i++) {
            $char_index = $k % $a;
            $k          = ($k * 65599 + ord($one_str[$char_index])) & 0xFFFFFFFF;
        }
        return $k;
    }

    public function cal_one_str_3($one_str, $orgi_iv)
    {
        $k = $orgi_iv;
        for ($i = 0; $i < strlen($one_str); $i++) {
            $k = ($k * 65599 + ord($one_str[$i])) & 0xFFFFFFFF;
        }
        return $k;
    }

    public function get_one_chr($enc_chr_code)
    {
        if ($enc_chr_code < 26) {
            return chr($enc_chr_code + 65);
        } elseif ($enc_chr_code < 52) {
            return chr($enc_chr_code + 71);
        } elseif ($enc_chr_code < 62) {
            return chr($enc_chr_code - 4);
        } else {
            return chr($enc_chr_code - 17);
        }
    }

    public function enc_num_to_str($one_orgi_enc)
    {
        $s = '';
        for ($i = 24; $i >= 0; $i -= 6) {
            $bits = ($one_orgi_enc >> $i) & 63;
            $s .= $this->get_one_chr($bits);
        }
        return $s;
    }

    public function getAcSignature($one_site, $one_nonce, $ua_n, $one_time_stamp = null)
    {
        if ($one_time_stamp === null) {
            $one_time_stamp = time();
        }

        $sign_head    = '_02B4Z6wo00f01';
        $time_stamp_s = (string) $one_time_stamp;

        $a = $this->cal_one_str($one_site, $this->cal_one_str($time_stamp_s, 0)) % 65521;

        $bin_str = str_pad(decbin($one_time_stamp ^ ($a * 65521)), 32, '0', STR_PAD_LEFT);
        $b       = bindec("10000000110000" . $bin_str);
        $b_s     = (string) $b;

        $c = $this->cal_one_str($b_s, 0);

        $d = $this->enc_num_to_str($b >> 2);
        // $e = ($b / 4294967296) & 0xFFFFFFFF;
        $e = (int) ($b >> 32) & 0xFFFFFFFF;

        $f = $this->enc_num_to_str(($b << 28) | ($e >> 4));
        $g = 582085784 ^ $b;
        $h = $this->enc_num_to_str(($e << 26) | ($g >> 6));
        $i = $this->get_one_chr($g & 63);

        $j = (($this->cal_one_str($ua_n, $c) % 65521) << 16) | ($this->cal_one_str($one_nonce, $c) % 65521);
        $k = $this->enc_num_to_str($j >> 2);
        $l = $this->enc_num_to_str(($j << 28) | ((524576 ^ $b) >> 4));
        $m = $this->enc_num_to_str($a);

        $n = $sign_head . $d . $f . $h . $i . $k . $l . $m;

        $o_hex = dechex($this->cal_one_str_3($n, 0));
        $o     = substr(str_pad($o_hex, 2, '0', STR_PAD_LEFT), -2);

        $signature = $n . $o;
        return $signature;
    }
}
