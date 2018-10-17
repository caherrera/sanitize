<?php

/**
 * Offers different sanitize methods.
 *
 * @author        Carlos Herrera
 * @author        Tomo Tsuyuki
 * @since         19 May 2016
 * @version       1
 */
class Sanitizer
{

    private static $instance;

    /**
     * Set the value of methods $check param.
     *
     * @var string
     * @access public
     */
    private $check = null;

    /**
     * Some class methods use a country to determine proper validation.
     * This can be passed to methods in the $country param
     *
     * @var string
     * @access public
     */
    private $country = null;

    /**
     * Set to a valid regular expression for searching in the class methods.
     * Can be set from $regex param also
     *
     * @var string|array
     * @access public
     */
    private $searchRegex = null;

    /**
     * Set to a valid regular expression for replacement in the class methods.
     * Can be set from $regex param also
     *
     * @var string|array
     * @access public
     */
    private $replaceRegex = null;

    /**
     * Some class methods use the $type param to determine which validation to perform in the method
     *
     * @var string
     * @access public
     */
    private $type = null;

    /**
     * Holds an array of errors messages set in this class.
     * These are used for debugging purposes
     *
     * @var array
     * @access public
     */
    private $errors = array();

    private function __construct()
    {
        $this->__reset();
    }

    /**
     * Gets a reference to the Validation object instance
     *
     * @return Fuse_Global_Sanitize
     * @access public
     * @static
     *
     */
    public static function getInstance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * @param $check
     * @param null $country
     * @return bool|mixed
     */
    public function phone($check, $country = null)
    {
        $this->__reset();
        $this->check = $check;
        $this->country = $country;
        if (is_array($check)) {
            $this->_extract($check);
        }
        if (empty($country)) {
            $this->country = 'au';
        }

        switch ($this->country) {
            case 'nz':
                // remove spaces
                // add "0" if there are 8 digits stating with not "0"
                // add "0" if the number starts 2 (mobile) and there are 9 or 10 digits
                // remove "(+)64" and add "0" if "(+)64" exists and 8 digit is following after "(+)64"
                $this->searchRegex = array('/\s/','/^([1-9])(\d{7})$/','/^([2])(\d{8,9})$/', '/^(\+?64)(\d{8})$/', '/^(\+?64)([2])(\d{8,9})$/');
                $this->replaceRegex = array('', '0${1}${2}', '0${1}${2}', '0${2}', '0${2}${3}');
                break;
            case 'au':
                // remove spaces
                // add "0" if there are 9 digits stating with not "0"
                // remove "(+)61" and add "0" if "(+)61" exists and 9 digit is following after "(+)61"
                $this->searchRegex = array('/\s/','/^([1-9])(\d{8})$/', '/^(\+?61)(\d{9})$/');
                $this->replaceRegex = array('', '0${1}${2}', '0${2}');
                break;
        }
        return $this->_replace();
    }

    /**
     * @param $check
     * @param null $country
     * @return bool|mixed
     */
    public function postal($check, $country = null)
    {
        $this->__reset();
        $this->check = $check;
        $this->country = $country;
        if (is_array($check)) {
            $this->_extract($check);
        }
        if (empty($country)) {
            $this->country = 'au';
        }
        switch ($this->country) {
            case 'au':
            case 'nz':
                // add "0" to fill till 4 digits
                $this->searchRegex = array('/^\d{1}$/', '/^\d{2}$/', '/^\d{3}$/');
                $this->replaceRegex = array('000$0','00$0','0$0');
                break;
        }
        return $this->_replace();
    }

    /**
     * This function will try sanitising input date string, transform to the desired format, and return output.
     * If transforming is impossible, the function will return false
     * @param $check - date string
     * @param string $output_format - desired output format
     * @return false|string
     */
    public function date($check, $output_format = 'Y-m-d')
    {
        $check = trim($check);

        $delimiters = array('/', '-', '.');

        $part1 = null;
        $part2 = null;
        $part3 = null;
        $timestamp = null;

        foreach ($delimiters as $delimiter) {

            $parts = explode($delimiter, $check);

            if (is_array($parts) && count($parts) == 3) {
                list($part1, $part2, $part3) = $parts;
                break;
            }
        }

        if (is_null($part1) || is_null($part2) || is_null($part3)) {
            // can't even get it to 3 parts, nothing we can do
            return false;
        }

        if (checkdate($part2, $part1, $part3)) {
            return date($output_format, mktime(0, 0, 0, $part2, $part1, $part3));

        } elseif (checkdate($part2, $part3, $part1)) {
            return date($output_format, mktime(0, 0, 0, $part2, $part3, $part1));
        }

        return false;
    }

    /**
     * This function will try sanitising input email string, and return output.
     * If transforming is impossible, the function will return false
     * @param $check - email string
     * @return false|string
     */
    public function email($check)
    {
        $check = preg_split('/[;,]/',$check);
        $n=count($check);
        $check = array_map(function(&$check){
            $check = filter_var($check,FILTER_SANITIZE_EMAIL);
            if (Fuse_Global_Validation::getInstance()->email($check)) {
                return $check;
            }
        },$check);
        $check=array_filter($check);
        $m=count($check);
        $check=implode(';',$check);

        return $check;

    }

    /**
     * @param $check
     * @return mixed|string
     */
    public function gender($check)
    {
        $check = trim($check);
        $dictionary = array(
            'f' => Fuse_Global_Digital::EDM_TARGET_GENDER_FEMALE,
            'm' => Fuse_Global_Digital::EDM_TARGET_GENDER_MALE,
            'female' => Fuse_Global_Digital::EDM_TARGET_GENDER_FEMALE,
            'male' => Fuse_Global_Digital::EDM_TARGET_GENDER_MALE,
        );
        return Fuse_Global_Utility::getValue(strtolower($check), $dictionary, Fuse_Global_Digital::EDM_TARGET_GENDER_DEFAULT);
    }



    /**
     * Runs an user-defined validation.
     *
     * @param mixed $check
     *            value that will be validated in user-defined methods.
     * @param object $object
     *            class that holds validation method
     * @param string $method
     *            class method name for validation to run
     * @param array $args
     *            arguments to send to method
     * @return mixed user-defined class class method returns
     * @access public
     */
    public function userDefined($check, $object, $method, $args = null)
    {
        return call_user_func_array(array(
            &$object,
            $method
        ), array(
            $check,
            $args
        ));
    }

    /**
     * Return value sanitized or false
     * @access private
     * @return bool|mixed
     */
    private function _replace()
    {
        $result = preg_replace($this->searchRegex, $this->replaceRegex, $this->check);
        if (!is_null($result)) {
            $this->errors[] = false;
            return $result;
        } else {
            $this->errors[] = true;
            return false;
        }
    }

    /**
     * Get the values to use when value sent to validation method is
     * an array.
     *
     * @param array $params
     *            Parameters sent to validation method
     * @return void
     * @access protected
     */
    private function _extract($params)
    {
        extract($params, EXTR_OVERWRITE);

        if (isset($check)) {
            $this->check = $check;
        }
        if (isset($country)) {
            $this->country = mb_strtolower($country);
        }
        if (isset($searchRegex)) {
            $this->searchRegex = $searchRegex;
        }
        if (isset($replaceRegex)) {
            $this->replaceRegex = $replaceRegex;
        }
        if (isset($type)) {
            $this->type = $type;
        }
    }
    /**
     * Reset internal variables for another validation run.
     *
     * @return void
     * @access private
     */
    private function __reset()
    {
        $this->check = null;
        $this->country = null;
        $this->searchRegex = null;
        $this->replaceRegex = null;
        $this->type = null;
        $this->errors = array();
    }

    /**
     * Calls a method on this object with the given parameters.
     * Provides an OO wrapper
     * for `call_user_func_array`
     *
     * @param string $method
     *            Name of the method to call
     * @param array $params
     *            Parameter list to use when calling $method
     * @return mixed Returns the result of the method call
     * @access public
     */
    public function dispatchMethod($method, $params = array())
    {
        switch (count($params)) {
            case 0:
                return $this->{$method}();
            case 1:
                return $this->{$method}($params[0]);
            case 2:
                return $this->{$method}($params[0], $params[1]);
            case 3:
                return $this->{$method}($params[0], $params[1], $params[2]);
            case 4:
                return $this->{$method}($params[0], $params[1], $params[2], $params[3]);
            case 5:
                return $this->{$method}($params[0], $params[1], $params[2], $params[3], $params[4]);
            default:
                return call_user_func_array(array(
                    &$this,
                    $method
                ), $params);
                break;
        }
    }
}
