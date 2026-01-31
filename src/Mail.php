<?php
namespace Fluxion;

class Mail {

    private $_from;
    private $_reply_to;
    private $_to;
    private $_cc = [];
    private $_bcc = [];
    private $_subject;
    private $_body;

    private $_headers;
    private $_multipart;

    private $_boundary;

    public function __construct()
    {
        $this->_boundary = '--' . md5(uniqid(time()));
    }

    public function setFrom($from)
    {
        $this->_from = $from;
    }

    public function setReplyTo($reply_to)
    {
        $this->_reply_to = $reply_to;
    }

    public function setBody($body)
    {
        $this->_body = $body;
    }

    public function setSubject($subject)
    {
        $this->_subject = $subject;
    }

    public function setTo($to)
    {
        $this->_to = str_replace(';', ',', $to);
    }

    public function addCc($cc)
    {
        $this->_cc[] = $cc;
    }

    public function addBcc($bbc)
    {
        $this->_bcc[] = $bbc;
    }

    private function prepare()
    {

        $this->_headers = '';
        $this->_multipart = '';

        $paths = [];

        if (preg_match_all('/<img.*?src=.([\/.a-z0-9:_-]+).*?>/si', $this->_body, $matches)) {

            foreach ($matches[1] as $img) {

                $img_old = $img;

                $img = preg_replace('/^\//m', '', $img);

                if (strpos($img, 'http://') == false) {

                    $uri = parse_url($img);

                    $content_id = md5($img);

                    $this->_body = str_replace($img_old, 'cid:' . $content_id, $this->_body);

                    $paths[] = [
                        'path' => $uri['path'],
                        'cid' => $content_id,
                    ];

                }

            }

        }

        $this->_headers .= "MIME-Version: 1.0" . PHP_EOL;
        $this->_headers .= "Content-Type: multipart/mixed; boundary=\"$this->_boundary\"" . PHP_EOL;
        $this->_headers .= 'Date: ' . gmdate('D, d M Y H:i:s', time()) . ' GMT' . PHP_EOL;
        $this->_headers .= 'X-Mailer: PHP/' . phpversion() . PHP_EOL;

        if ($this->_from)
            $this->_headers .= "From: $this->_from" . PHP_EOL;

        if ($this->_reply_to)
            $this->_headers .= "Reply-To: $this->_reply_to" . PHP_EOL;

        foreach ($this->_cc as $cc)
            if ($cc != '')
                $this->_headers .= 'Cc: ' . $cc . PHP_EOL;

        foreach ($this->_bcc as $bcc)
            if ($bcc != '')
                $this->_headers .= 'Bcc: ' . $bcc . PHP_EOL;

        $this->_multipart .= "--$this->_boundary" . PHP_EOL;
        $this->_multipart .= "Content-Type: text/html; charset=utf-8" . PHP_EOL;
        $this->_multipart .= "Content-Transfer-Encoding: Quot-Printed" . PHP_EOL . PHP_EOL;
        $this->_multipart .= $this->_body . PHP_EOL . PHP_EOL;

        foreach ($paths as $path) {

            if (file_exists($path['path'])) {

                $file = file_get_contents($path['path']);

                $type = strtolower(substr(strrchr($path['path'], '.'), 1));

                $part = '';

                switch ($type) {

                    case 'png':
                        $part .= "Content-Type: image/png";
                        break;

                    case 'jpg':
                    case 'jpeg':
                        $part .= "Content-Type: image/jpeg";
                        break;

                    case 'gif':
                        $part .= "Content-Type: image/gif";
                        break;

                }

                $part .= "; file_name = \"{$path['path']}\"" . PHP_EOL;
                $part .= 'Content-ID: <' . $path['cid'] . ">" . PHP_EOL;
                $part .= "Content-Transfer-Encoding: base64" . PHP_EOL;
                $part .= "Content-Disposition: inline; filename = \"" . basename($path['path']) . "\"" . PHP_EOL . PHP_EOL;
                $part .= chunk_split(base64_encode($file)) . PHP_EOL;

                $this->_multipart .= "--$this->_boundary" . PHP_EOL . $part . PHP_EOL;

            }

        }

        $this->_multipart .= "--$this->_boundary--" . PHP_EOL;

    }

    public function send(): bool
    {

        $this->prepare();

        return @mail($this->_to, $this->_subject, $this->_multipart, $this->_headers);

    }

}
