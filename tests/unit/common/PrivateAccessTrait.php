<?php

namespace unit\common;

use ReflectionClass;
use ReflectionException;

trait PrivateAccessTrait
{
    /**
     * Calls a private/protected method
     * @param object $object Instance of the class under test
     * @param string $methodName Methode name
     * @param array $arguments Arguments to pass
     * @return mixed
     * @throws ReflectionException
     */
    protected function invokeMethod(object $object, string $methodName, array $arguments = []): mixed
    {
        $reflection = new ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $arguments);
    }

    /**
     * Retrieves the value of a private/protected property of an object
     *
     * @param object $object Instance of the class under test
     * @param string $property Property name
     * @return mixed
     * @throws ReflectionException
     */
    protected function getPrivateProperty(object $object, string $property): mixed
    {
        $reflection = new ReflectionClass($object);
        $prop = $reflection->getProperty($property);
        $prop->setAccessible(true);

        return $prop->getValue($object);
    }

    /**
     * Sets the value of a private or protected property of an object
     *
     * @param object $object Instance of the class under test
     * @param string $property Propery name
     * @param mixed $value Value to be assigned
     * @return void
     * @throws ReflectionException
     */
    protected function setPrivateProperty(object $object, string $property, mixed $value): void
    {
        $reflection = new ReflectionClass($object);
        try {
            $prop = $reflection->getProperty($property);
        } catch (ReflectionException $e) {
            $prop = null;
        }

        if (!$prop) {  // Inheritance handling: fall back to the parent class if the property does not exist

            $reflection = $reflection->getParentClass();

            if ($reflection->hasProperty($property)) {
                $prop = $reflection->getProperty($property);
            }
        }

        if (!$prop) {
            throw new ReflectionException("Property '$property' not found");
        }

        $prop->setAccessible(true);
        $prop->setValue($object, $value);
    }

}