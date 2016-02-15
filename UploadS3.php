<?php

namespace keygenqt\uploadS3;

use \yii\base\Exception;
use yii\base\Widget;

class UploadS3 extends Widget
{
    public $debug = true;
    public $key;
    public $secret;
    public $bucket;

    public function upload($path, $name)
    {
        if (strlen($this->secret) != 40) {
            if ($this->debug) {
                throw new Exception("{$this->secret} should be exactly 40 bytes long");
            } else {
                return false;
            }
        }

        $file_data = file_get_contents($path);

        if ($file_data == false) {
            if ($this->debug) {
                throw new Exception("Failed to read file " . $path);
            } else {
                return false;
            }
        }

        $fp = fsockopen("s3.amazonaws.com", 80, $errno, $errstr, 30);

        if (!$fp) {
            if ($this->debug) {
                throw new Exception("$errstr ($errno)");
            } else {
                return false;
            }
        }

        // or other file type like "image/jpeg" for JPEG image,
        // or "binary/octet-stream" for binary file
        $mime = mime_content_type($path);
        if (strpos($mime, 'image') === false) {
            $mime = "binary/octet-stream";
        }

        // Creating or updating bucket
        $dt = gmdate('r'); // GMT based timestamp

        // preparing String to Sign    (see AWS S3 Developer Guide)
        $string2sign = "PUT


{$dt}
/{$this->bucket}";

        $query = "PUT /{$this->bucket} HTTP/1.1
Host: s3.amazonaws.com
Connection: keep-alive
Date: $dt
Authorization: AWS {$this->key}:" . $this->amazon_hmac($string2sign)."\n\n";

        $resp = $this->sendREST($fp, $query);

        if (strpos($resp, '<Error>') !== false) {
            if ($this->debug) {
                throw new Exception($resp);
            } else {
                return false;
            }
        }

        // Uploading object
        $file_length = strlen($file_data); // for Content-Length HTTP field

        $dt = gmdate('r'); // GMT based timestamp
        // preparing String to Sign    (see AWS S3 Developer Guide)
        $string2sign = "PUT

{$mime}
{$dt}
x-amz-acl:public-read
/{$this->bucket}/{$name}";

        $query = "PUT /{$this->bucket}/{$name} HTTP/1.1
Host: s3.amazonaws.com
x-amz-acl: public-read
Connection: keep-alive
Content-Type: {$mime}
Content-Length: {$file_length}
Date: $dt
Authorization: AWS {$this->key}:" . $this->amazon_hmac($string2sign)."\n\n";
        $query .= $file_data;

        $resp = $this->sendREST($fp, $query);

        if (strpos($resp, '<Error>') !== false) {
            if ($this->debug) {
                throw new Exception($resp);
            } else {
                return false;
            }
        }

        fclose($fp);

        return "http://s3.amazonaws.com/{$this->bucket}/{$name}";
    }

    // Sending HTTP query and receiving, with trivial keep-alive support
    function sendREST($fp, $q, $debug = false)
    {
        fwrite($fp, $q);
        $r = '';
        $check_header = true;
        while (!feof($fp)) {

            $tr = fgets($fp, 256);
            $r .= $tr;

            if (($check_header)&&(strpos($r, "\r\n\r\n") !== false))
            {
                // if content-length == 0, return query result
                if (strpos($r, 'Content-Length: 0') !== false)
                    return $r;
            }

            // Keep-alive responses does not return EOF
            // they end with \r\n0\r\n\r\n string
            if (substr($r, -7) == "\r\n0\r\n\r\n")
                return $r;
        }
        return $r;
    }

    // hmac-sha1 code START
    // hmac-sha1 function:  assuming key is global $aws_secret 40 bytes long
    // read more at http://en.wikipedia.org/wiki/HMAC
    // warning: key($aws_secret) is padded to 64 bytes with 0x0 after first function call
    function amazon_hmac($stringToSign)
    {
        $aws_secret = $this->secret;

        if (strlen($aws_secret) == 40)
            $aws_secret = $aws_secret . str_repeat(chr(0), 24);

        $ipad = str_repeat(chr(0x36), 64);
        $opad = str_repeat(chr(0x5c), 64);

        if (version_compare(phpversion(), "5.0.0", ">=")) {
            $hmac = $this->binsha1(($aws_secret^$opad) . $this->binsha1(($aws_secret^$ipad).$stringToSign));
        } else {
            $hmac = $this->binsha2(($aws_secret^$opad) . $this->binsha2(($aws_secret^$ipad).$stringToSign));
        }

        return base64_encode($hmac);
    }
    // hmac-sha1 code END

    function binsha1($d)
    {
        return sha1($d, true);
    }

    function binsha2($d)
    {
        return pack('H*', sha1($d));
    }
}