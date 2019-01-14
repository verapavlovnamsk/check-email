<?php
class verifyEmail {
    /**
     * User name
     * @var string
     */
    private $_fromName;
    /**
     * Domain name
     * @var string
     */
    private $_fromDomain;
    /**
     * SMTP port number
     * @var int
     */
    private $_port;
    /**
     * The connection timeout, in seconds.
     * @var int
     */
    private $_maxConnectionTimeout;
    /**
     * The timeout on socket connection
     * @var int
     */
    private $_maxStreamTimeout;
    public function __construct() {
        $this->_fromName = 'noreply';
        $this->_fromDomain = 'localhost';
        $this->_port = 25;
        $this->_maxConnectionTimeout = 30;
        $this->_maxStreamTimeout = 5;
    }
    /**
     * Set email address for SMTP request
     * @param string $email Email address
     */
    public function setEmailFrom($email) {
        list($this->_fromName, $this->_fromDomain) = $this->_parseEmail($email);
    }
    /**
     * Set connection timeout, in seconds.
     * @param int $seconds
     */
    public function setConnectionTimeout($seconds) {
        $this->_maxConnectionTimeout = $seconds;
    }
    /**
     * Set the timeout on socket connection
     * @param int $seconds
     */
    public function setStreamTimeout($seconds) {
        $this->_maxStreamTimeout = $seconds;
    }
    /**
     * Validate email address.
     * @param string $email
     * @return boolean  True if valid.
     */
    public function isValid($email) {
        return (false !== filter_var($email, FILTER_VALIDATE_EMAIL));
    }
    /**
     * Get array of MX records for host. Sort by weight information.
     * @param string $hostname The Internet host name.
     * @return array Array of the MX records found.
     */
    public function getMXrecords($hostname) {
        $mxhosts = array();
        $mxweights = array();
        if (getmxrr($hostname, $mxhosts, $mxweights)) {
            array_multisort($mxweights, $mxhosts);
        }
        /**
         * Add A-record as last chance (e.g. if no MX record is there).
         * Thanks Nicht Lieb.
         */
        $mxhosts[] = $hostname;
        return $mxhosts;
    }
    /**
     * check up e-mail
     * @param string $email Email address
     * @return boolean True if the valid email also exist
     */
    public function check($email) {
        $result = false;
        if ($this->isValid($email)) {
            list($user, $domain) = $this->_parseEmail($email);
            $mxs = $this->getMXrecords($domain);
            $fp = false;
            $timeout = ceil($this->_maxConnectionTimeout / count($mxs));
            foreach ($mxs as $host) {
//                if ($fp = @fsockopen($host, $this->_port, $errno, $errstr, $timeout)) {
                if ($fp = @stream_socket_client("tcp://" . $host . ":" . $this->_port, $errno, $errstr, $timeout)) {
                    stream_set_timeout($fp, $this->_maxStreamTimeout);
                    stream_set_blocking($fp, 1);
//                    stream_set_blocking($fp, 0);
                    $code = $this->_fsockGetResponseCode($fp);
                    if ($code == '220') {
                        break;
                    } else {
                        fclose($fp);
                        $fp = false;
                    }
                }
            }
            if ($fp) {
                $this->_fsockquery($fp, "HELO " . $this->_fromDomain);
                $this->_fsockquery($fp, "MAIL FROM: <" . $this->_fromName . '@' . $this->_fromDomain . ">");
                $code = $this->_fsockquery($fp, "RCPT TO: <" . $user . '@' . $domain . ">");
                $this->_fsockquery($fp, "RSET");
                $this->_fsockquery($fp, "QUIT");
                fclose($fp);
                if ($code == '250') {
                    /**
                     * 
                     * 250 Requested mail action okay, completed
                     * email address was accepted
                     */
                    $result = true;
                } elseif ($code == '450' || $code == '451' || $code == '452') {
                    /**
                     * 
                     * 450 Requested action not taken: the remote mail server
                     *     does not want to accept mail from your server for
                     *     some reason (IP address, blacklisting, etc..)
                     *     Thanks Nicht Lieb.
                     */
                    $result = true;
                }
            }
        }
        return $result;
    }
	
        $email = 'filatova@gmail.com';
        $vmail = new verifyEmail();
        if ($vmail->check($email)) {
            echo 'email &lt;' . $email . '&gt; exist!';
        } elseif ($vmail->isValid($email)) {
            echo 'email &lt;' . $email . '&gt; valid, but not exist!';
        } else {
            echo 'email &lt;' . $email . '&gt; not valid and not exist!';
        }
?>
   
