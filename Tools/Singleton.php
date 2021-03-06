<?php

namespace lightningsdk\core\Tools;

use lightningsdk\core\Model\ObjectDataStorage;

/**
 * Class Singleton
 *
 * A base class for singleton tools.
 */
class Singleton {
    use ObjectDataStorage;

    /**
     * A static instance of the singleton.
     *
     * @var Singleton
     */
    protected static $instances = [];

    /**
     * A list of overidden classes for reference.
     *
     * @var null
     */
    protected static $overrides = null;

    /**
     * Initialize or return an instance of the requested class.
     * @param boolean $create
     *   Whether to create the instance if it doesn't exist.
     *
     * @return Singleton
     */
    public static function getInstance($create = true) {
        $class = static::getStaticName();
        if (empty(static::$instances[$class]) && $create) {
            // There may be additional args passed to this function.
            $args = func_get_args();
            // Replace the first argument with the class name.
            $args[0] = $class;
            self::$instances[$class] = call_user_func_array('static::getNewInstance', $args);
        }
        return !empty(self::$instances[$class]) ? self::$instances[$class] : null;
    }

    /**
     * Get the new instance by creating a new object of the inherited class.
     *
     * @param string
     *   The class to create.
     *
     * @return object
     *   The new instance.
     */
    private static function getNewInstance($class) {
        // Load the class if it's not already loaded.
        if (!class_exists($class)) {
            ClassLoader::classAutoloader($class);
        }

        // There may be additional args passed to this function.
        $args = func_get_args();
        array_shift($args);
        return call_user_func_array([$class, 'createInstance'], $args);
    }

    protected static function createInstance() {
        return new static();
    }

    /**
     * Remove the static instance from memory.
     */
    protected static function destroyInstance() {
        $class = static::getStaticName();
        unset(self::$instances[$class]);
    }

    /**
     * Set the singleton instance.
     *
     * @param string $object
     *   The new instance.
     */
    public static function setInstance($object) {
        $class = static::getStaticName();
        self::$instances[$class] = $object;
    }

    /**
     * Create a new singleton.
     *
     * @return object
     *   The new instance.
     */
    public static function resetInstance() {
        $class = static::getStaticName();
        return self::$instances[$class] = self::getNewInstance($class);
    }

    protected static function getStaticName() {
        // TODO: Verify if this is necessary
        $class = get_called_class();
        $class = preg_replace('/\\Overridable$/', '', $class);
        if (!isset(self::$overrides)) {
            self::$overrides = array_flip(Configuration::get('classes', []));
        }
        return !empty(self::$overrides[$class]) ? self::$overrides[$class] : $class;
    }
}
