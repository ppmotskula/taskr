<?php
/**
 * @package My
 * @subpackage Magic
 * @author Peeter P. Mõtsküla <peeterpaul@motskula.net>
 * @copyright Copyright © 2010 Peeter P. Mõtsküla <peeterpaul@motskula.net>
 * @license http://opensource.org/licenses/bsd-license.html New BSD license
 * @version 0.1.0
 */
/**
 * Abstract parent for all magic overloader classes
 *
 * Class variables named $_magicName can be accessed (read, write, isset,
 * unset) from outside the class as $instance->name.
 *
 * If respective setName() or getName() methods are defined in the class,
 * then they are automatically used; otherwise the generic __set() or __get()
 * magic methods are used.
 *
 * If class variable array _magic is defined, then further magic variables
 * can be defined on the run: $instance->name will be stored in
 * $instance->_magic[name].
 *
 * Magic names must consist of letters only, start and end with lowercase
 * letter, contain no successive uppercase letters. 'magic' is reserved name.
 */
abstract class My_MagicAbstract
{
    /**
     * Constructor
     *
     * Populates magic properties from the array if given
     *
     * @param array $magic OPTIONAL
     */
    public function __construct(array $magic = NULL)
    {
        if (is_array($magic)) {
            $this->setMagic($magic);
        }
    }

    /**
     * Universal magic __set method
     *
     * Returns $value if successful, throws exception if unable to set value
     *
     * @param string $name
     * @param mixed $value
     * @return mixed
     * @throws Exception
     */
    public function __set($name, $value)
    {
        if ($this->_goodName($name)) {
            $method = 'set' . ucfirst($name);
            $property = '_magic' . ucfirst($name);
        } else {
            throw new Exception("Invalid property name '$name'");
        }

        if (method_exists($this, $method)) {
            $this->$method($value);
        } elseif (property_exists($this, $property)) {
            $this->$property = $value;
        } elseif (is_array($this->_magic)) {
            $this->_magic[$name] = $value;
        } else {
            throw new Exception("Undefined property '$name'");
        }

        return $value;
    }

    /**
     * Universal magic __get method
     *
     * Returns value of property $name if successful,
     * throws exception if property is unavailable
     *
     * @param string $name
     * @return mixed
     * @throws Exception
     */
    public function __get($name)
    {
        if ($this->_goodName($name)) {
            $method = 'get' . ucfirst($name);
            $property = '_magic' . ucfirst($name);
        } else {
            throw new Exception("Invalid property name '$name'");
        }

        if (method_exists($this, $method)) {
            return $this->$method();
        } elseif (property_exists($this, $property)) {
            return $this->$property;
        } elseif (isset($this->_magic[$name])) {
            return $this->_magic[$name];
        } else {
            throw new Exception("Undefined property '$name'");
        }
    }

    /**
     * Universal magic __isset method
     *
     * Returns TRUE if property $name is set,
     * throws exception if property is unavailable
     *
     * @param string $name
     * @return bool
     * @throws Exception
     */
    public function __isset($name)
    {
        if ($this->_goodName($name)) {
            $method = 'get' . ucfirst($name);
            $property = '_magic' . ucfirst($name);
        } else {
            throw new Exception("Invalid property name '$name'");
        }

        if (method_exists($this, $method)) {
            return TRUE;
        } elseif (property_exists($this, $property)) {
            return isset($this->$property);
        } elseif (is_array($this->_magic)) {
            return isset($this->_magic[$name]);
        } else {
            throw new Exception("Undefined property '$name'");
        }
    }

    /**
     * Universal magic __unset method
     *
     * Unsets property $name,
     * throws exception if property is unavailable or cannot be unset
     *
     * @param string $name
     * @throws Exception
     */
    public function __unset($name)
    {
        if ($this->_goodName($name)) {
            $method = 'get' . ucfirst($name);
            $property = '_magic' . ucfirst($name);
        } else {
            throw new Exception("Invalid property name '$name'");
        }

        if (
            method_exists($this, $method)
            || property_exists($this, $property)
        ) {
            throw new Exception("Cannot unset hardcoded property '$name'");
        } elseif (is_array($this->_magic)) {
            unset($this->_magic[$name]);
        } else {
            throw new Exception("Undefined property '$name'");
        }
    }

    /**
     * Sets multiple magic properties from array
     *
     * Returns reference to the class instance
     *
     * @param string $name
     * @return MagicAbstract
     */
    public function setMagic(array $magic)
    {
        foreach ($magic as $name => $value) {
            $this->__set($name, $value);
        }

        return $this;
    }

    /**
     * Returns all magic properties as an associative array
     *
     * @return array
     */
    public function getMagic()
    {
        $result = array();
        $properties = get_object_vars($this);

        if (property_exists($this, '_magic') && is_array($this->_magic)) {
            foreach ($this->_magic as $key => $value) {
                $result[$key] = $value;
            }
        }

        foreach ($properties as $name => $value) {
            if (
                '_magic' == substr($name, 0, 6)
                && $this->_goodName($key = (
                    strtolower(substr($name, 6, 1)) . substr($name, 7)))
            ) {
                $method = 'get' . ucfirst($key);

                if (method_exists($this, $method)) {
                    $result[$key] = $this->$method();
                } else {
                    $result[$key] = $value;
                }
            }
        }

        $methods = get_class_methods($this);

        foreach ($methods as $method) {
            if (
                'get' == substr($method, 0, 3)
                && $this->_goodName(substr($method, 3))
            ) {
                $key = lcfirst(substr($method, 3));
                $result[$key] = $this->$method();
            }
        }

        return $result;
    }

    /**
     * @ignore (internal)
     *
     * Returns TRUE if $name is acceptable "magic" name
     *
     * @param string $name
     * @return bool
     */
    protected function _goodName($name)
    {
        $result = (
            preg_match('/^[a-z](?:[A-Z]?[a-z]+)*$/', $name)
            && 'magic' != $name
        );
        return $result;
    }

}
