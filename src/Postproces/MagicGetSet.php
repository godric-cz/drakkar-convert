<?php

namespace Drakkar\Postproces;

use Exception;
use ReflectionClass;
use ReflectionMethod;

// TODO problém by byl s autocomplete, který funguje na základě @property anotací k třídě i podle phpdocu
// další možnost je udělat do php-cs-fixeru plugin, který u tříd dědících z Nette\SmartObject ty anotace dělá / aktualizuje automaticky podle get/set/is metod

trait MagicGetSet {
    private static $_magic_getters;
    private static $_magic_setters;

    function __get($name) {
        if (!isset(static::$_magic_getters)) {
            $this->_prepare();
        }

        $getter = static::$_magic_getters[$name] ?? null;

        if ($getter) {
            return $this->{$getter}();
        } else {
            throw new Exception("Non-existent getter for '$name'");
        }
    }

    function __set($name, $value) {
        if (!isset(static::$_magic_setters)) {
            $this->_prepare();
        }

        $setter = static::$_magic_setters[$name] ?? null;

        if ($setter) {
            return $this->{$setter}($value);
        } else {
            throw new Exception("Non-existent setter for '$name'");
        }
    }

    private function _prepare() {
        $class = new ReflectionClass($this);
        $methods = $class->getMethods(ReflectionMethod::IS_PUBLIC);

        static::$_magic_getters = [];
        static::$_magic_setters = [];

        foreach ($methods as $method) {
            $prefix = substr($method->name, 0, 3);

            // TODO jen metody s 0 resp právě 1 povinným parametrem

            if ($prefix == 'get') {
                $propertyName = lcfirst(substr($method->name, 3));
                static::$_magic_getters[$propertyName] = $method->name;
            } elseif ($prefix == 'set') {
                $propertyName = lcfirst(substr($method->name, 3));
                static::$_magic_setters[$propertyName] = $method->name;
            }
        }
    }
}
