<?php

interface ArrayAccess
{
    /**
     * Проверяет, существует ли элемент по указанному ключу.
     *
     * @param mixed $offset Ключ.
     * @return bool Возвращает true, если элемент существует, иначе false.
     */
    public function offsetExists($offset): bool;

    /**
     * Получает элемент по указанному ключу.
     *
     * @param mixed $offset Ключ.
     * @return mixed Значение элемента по ключу.
     */
    public function offsetGet($offset);

    /**
     * Устанавливает значение по указанному ключу.
     *
     * @param mixed $offset Ключ.
     * @param mixed $value Значение.
     * @return void
     */
    public function offsetSet($offset, $value): void;

    /**
     * Удаляет элемент по указанному ключу.
     *
     * @param mixed $offset Ключ.
     * @return void
     */
    public function offsetUnset($offset): void;
}
