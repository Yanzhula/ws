<?php
namespace ws\mail;

class Sender{
    
    protected $_contentType;
    protected $_encoding;
    
    protected $_from_email;
    protected $_from_name;
    protected $_subject;
    protected $_body;

    public function __construct($contentType='text/plain',$encoding='UTF-8') {
        $this->_contentType = $contentType;
        $this->_encoding = $encoding;
    }
    
    public function from($email, $name=null) {
        $this->_from_email = $email;
        $this->_from_name = $name ? $name : $email;
    }
    public function subject($subject) {
        $this->_subject = $subject;
    }
    public function body($body) {
        $this->_body = $body;
    }
    
    public function send($to) {
        
        $subject = '=?'.$this->_encoding.'?b?'
                .base64_encode( $this->_subject ).'?=';
        
        $from = '=?'.$this->_encoding.'?b?'
                .base64_encode( $this->_from_name ).'?=';
            
        $header  = 'MIME-Version: 1.0'.PHP_EOL;
        $header .= 'Content-Type: '.$this->_contentType.'; charset='.$this->_encoding.PHP_EOL;
        $header .= 'From: '.$from.' <'.$this->_from_email. '>'.PHP_EOL;
        $header .= 'Reply-To: '.$from.' <'.$this->_from_email. '>'.PHP_EOL;
        $header .= 'X-Mailer: PHP/'.PHP_VERSION.PHP_EOL;
            
        if (!is_array($to)){
            $to = func_get_args();
        }
        
        foreach ($to AS $receiver) {
               @mail($receiver, $subject, $this->_body, $header);
        }
    }
}
?>